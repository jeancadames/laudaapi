<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\BusinessContract;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\BusinessIdentity\CentralIdentityService;
use App\Services\Entitlements\BusinessContractCoverage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class BusinessContractActivationService
{
    public function __construct(
        private readonly BusinessImplementationService $implementation,
        private readonly BusinessQuoteAcceptanceService $quotes,
        private readonly BusinessQuoteInvoiceAmounts $amounts,
    ) {}

    public function ensureAvailable(): void
    {
        $this->implementation->ensureAvailable();
        abort_unless(config('business_commercial.activation_enabled'), 404);
        foreach (['business_contracts', 'business_contract_items', 'business_identity_links', 'business_identity_members'] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'La activación Business todavía no está disponible.');
        }
    }

    private function quote(User $actor, string $publicId, bool $admin): ServiceCommercialQuote
    {
        $this->ensureAvailable();
        if ($admin) {
            $this->implementation->authorizeAdmin($actor);

            return ServiceCommercialQuote::where('public_id', $publicId)->firstOrFail();
        }

        return $this->quotes->quote($actor, $publicId);
    }

    private function readiness(ServiceCommercialQuote $quote, bool $lock = false): array
    {
        $solutions = [];
        foreach ($quote->snapshot['products'] as $code => $product) {
            $serviceQuery = Service::whereKey($product['service_id']);
            $linkQuery = DB::table('business_identity_links')->where('service', $code)->where('central_company_id', $quote->company_id);
            if ($lock) {
                $serviceQuery->lockForUpdate();
                $linkQuery->lockForUpdate();
            }
            $service = $serviceQuery->first();
            $link = $linkQuery->first();
            $reason = null;
            if (! $service || ! $service->active || ! $service->billable || $service->service_key !== $code) {
                $reason = 'La solución no está disponible para activación.';
            } elseif (! config('business_identity.enabled')) {
                $reason = 'La vinculación Business debe estar habilitada.';
            } elseif (! $link || ! $link->active || ! $link->local_company_id || (int) $link->central_subscriber_id !== (int) $quote->subscriber_id) {
                $reason = 'Falta confirmar el vínculo de la empresa con esta solución.';
            }
            if ($reason === null) {
                try {
                    app(CentralIdentityService::class)->peer($code);
                } catch (HttpExceptionInterface $error) {
                    $reason = 'La conexión segura de esta solución requiere configuración.';
                }
            }
            $member = null;
            if ($reason === null) {
                $members = DB::table('business_identity_members as member')
                    ->join('subscriber_user as membership', 'membership.user_id', '=', 'member.central_user_id')
                    ->join('users', 'users.id', '=', 'member.central_user_id')
                    ->where('member.link_id', $link->id)->where('member.active', true)->whereNotNull('member.ready_at')
                    ->where('membership.subscriber_id', $quote->subscriber_id)->where('membership.active', true)
                    ->whereIn('membership.role', ['owner', 'admin'])->whereNotNull('users.email_verified_at')
                    ->orderBy('member.central_user_id');
                if ($lock) {
                    $members->lockForUpdate();
                }
                $member = $members->first(['member.central_user_id', 'member.generation', 'member.ready_at']);
                if (! $member) {
                    $reason = 'Un propietario o administrador debe completar la vinculación bilateral.';
                }
            }
            $solutions[$code] = ['name' => $product['name'], 'ready' => $reason === null, 'reason' => $reason,
                'link_id' => $link?->id, 'local_company_id' => $link?->local_company_id,
                'ready_user_id' => $member?->central_user_id, 'generation' => $member?->generation, 'ready_at' => $member?->ready_at];
        }

        return $solutions;
    }

    public function guidedHold(ServiceCommercialQuote $quote, int $checkoutId): ?string
    {
        if (! $quote->isGuidedPackage() || ! config('business_commercial.activation_enabled')
            || ! Schema::hasTable('business_contracts') || ! Schema::hasTable('business_contract_items')
            || ! Schema::hasTable('business_identity_links') || ! Schema::hasTable('business_identity_members')) {
            return 'La activación guiada todavía no está disponible.';
        }
        foreach ($this->readiness($quote) as $solution) {
            if (! $solution['ready']) {
                return $solution['reason'];
            }
        }

        return $this->transitionReason($quote, $checkoutId);
    }

    private function transitionReason(ServiceCommercialQuote $quote, int $checkoutId): ?string
    {
        if (isset($quote->snapshot['self_service']['replaces_contract_id'])) {
            return BusinessPackageChanges::replacementHold($quote, $checkoutId);
        }
        if (isset($quote->snapshot['transition'])) {
            return app(BusinessTransitionService::class)->reason($quote, true);
        }
        if (BusinessContract::where('subscriber_id', $quote->subscriber_id)->exists()) {
            return 'Ya existe un contrato Business. Se requiere revisar su renovación o transición.';
        }
        if (SubscriptionItem::whereHas('subscription', fn ($q) => $q->where('subscriber_id', $quote->subscriber_id))
            ->whereIn('status', ['active', 'trialing', 'pending'])->exists()) {
            return 'Hay servicios contratados o pendientes. Se requiere revisar su transición antes de activar Business.';
        }
        if (Invoice::where('company_id', $quote->company_id)->where('billing_snapshot->source', 'standalone_service_checkout')
            ->where(fn ($q) => $q->where('status', '!=', 'void')->orWhere('amount_paid', '>', 0))->exists()
            || DB::table('service_quote_checkouts as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
                ->where('c.subscriber_id', $quote->subscriber_id)->where('c.id', '!=', $checkoutId)
                ->where(fn ($q) => $q->where('i.status', '!=', 'void')->orWhere('i.amount_paid', '>', 0))->exists()) {
            return 'Hay otras contrataciones facturadas. Se requiere revisar su transición.';
        }
        $subscriptions = Subscription::where('subscriber_id', $quote->subscriber_id)->whereNotIn('status', ['cancelled', 'expired'])->get();
        if ($subscriptions->count() > 1) {
            return 'Hay varias suscripciones abiertas. Se requiere revisar su consolidación.';
        }
        $subscription = $subscriptions->first();
        if ($subscription && ($subscription->status !== 'active' || $subscription->currency !== $quote->snapshot['currency']
            || $subscription->ends_at !== null || $subscription->cancelled_at !== null
            || $subscription->items()->exists() || Invoice::where('subscription_id', $subscription->id)->exists()
            || (float) $subscription->total_amount !== 0.0 || (float) $subscription->tax_amount !== 0.0
            || (float) $subscription->subtotal_amount !== 0.0 || (float) $subscription->discount_amount !== 0.0)) {
            return 'La suscripción existente requiere revisión antes de incorporar el contrato Business.';
        }

        return null;
    }

    public function details(User $actor, string $publicId, bool $admin = false): array
    {
        $quote = $this->quote($actor, $publicId, $admin);
        [$receipt, $checkout, $invoice, $order] = $this->implementation->contract($quote);
        $contract = BusinessContract::where('service_quote_checkout_id', $checkout->id)->first();
        $solutions = $this->readiness($quote);
        $reasons = array_filter([
            $this->implementation->identityHold($quote), $this->implementation->paymentHold($checkout, $invoice),
            $order?->status === 'completed' ? null : 'La empresa debe dar conformidad a la implementación antes de activar.',
            $contract ? null : $this->transitionReason($quote, $checkout->id),
        ]);
        foreach ($solutions as $solution) {
            if ($solution['reason']) {
                $reasons[] = $solution['name'].': '.$solution['reason'];
            }
        }
        $state = 'pending';
        if ($contract) {
            abort_unless($contract->snapshot === $receipt->snapshot && hash_equals($contract->snapshot_hash, $receipt->snapshot_hash), 409, 'El contrato requiere revisión.');
            $coverage = DB::table('subscription_items')->where('subscription_id', $contract->subscription_id)
                ->whereIn('status', ['active', 'trialing'])->where(fn ($q) => BusinessContractCoverage::constrain($q))->count();
            $subscription = Subscription::findOrFail($contract->subscription_id);
            $display = BusinessContractPeriods::display($contract);
            $state = CarbonImmutable::parse($display['ends_at'])->lessThanOrEqualTo(now()) ? 'expired'
                : ($reasons === [] && $coverage === count($solutions) && $subscription->status === 'active' ? 'active' : 'review');
        }
        $line = $this->amounts->fromSnapshot($receipt->snapshot)['lines']['recurring'];
        if ($contract && ($renewal = BusinessContractPeriods::latestStarted($contract))) {
            app(BusinessRenewalService::class)->invoiceDetails($contract, $renewal);
            $line = ['subtotal_cents' => $this->amounts->cents($renewal->subtotal),
                'tax_cents' => $this->amounts->cents($renewal->tax_total), 'total_cents' => $this->amounts->cents($renewal->total)];
        }

        return ['quote' => $this->quotes->details($quote), 'invoice' => $invoice, 'admin' => $admin,
            'solutions' => $solutions, 'reasons' => array_values($reasons), 'state' => $state,
            'can_activate' => $admin && ! $contract && $reasons === [], 'recurring' => $line,
            'renewal_url' => $contract && config('business_commercial.renewal_enabled')
                ? route($admin ? 'admin.business_renewal.show' : 'business.quotes.renewal.show', $publicId, false) : null,
            'current_period' => $contract ? BusinessContractPeriods::display($contract) : null,
            'implementation_version' => $order?->lock_version,
            'back_url' => route($admin ? 'admin.business_implementations.show' : 'business.quotes.implementation.show', $publicId, false),
            'action_url' => $admin ? route('admin.business_activation.store', $publicId, false) : null,
            'contract' => $contract ? ['id' => $contract->id, 'subscription_id' => $contract->subscription_id,
                'activated_at' => $contract->activated_at->toIso8601String(), 'period_ends_at' => $contract->period_ends_at->toIso8601String(),
                'activated_name' => User::whereKey($contract->activated_by)->value('name'),
                'technical_evidence' => $contract->technical_evidence, 'automatic_renewal' => false] : null];
    }

    public static function periodEnd(CarbonImmutable $effective, string $cycle): CarbonImmutable
    {
        abort_unless(in_array($cycle, ['monthly', 'yearly'], true), 409, 'Ciclo contractual inválido.');

        return $cycle === 'yearly' ? $effective->addYearNoOverflow() : $effective->addMonthNoOverflow();
    }

    public function activate(User $actor, string $publicId, array $data): BusinessContract
    {
        return $this->createContract($actor, $publicId, $data, false);
    }

    public function activateGuided(User $actor, ServiceCommercialQuote $quote, int $version, string $key): BusinessContract
    {
        abort_unless($quote->isGuidedPackage() && $actor->role === 'subscriber', 403);

        return $this->createContract($actor, $quote->public_id, [
            'snapshot_hash' => BusinessQuoteAcceptanceService::fingerprint($quote), 'implementation_version' => $version,
            'idempotency_key' => $key, 'technical_evidence' => array_fill_keys(array_keys($quote->snapshot['products']),
                'Vínculo bilateral y membresía verificados al confirmar la implementación guiada.'),
        ], true);
    }

    private function createContract(User $actor, string $publicId, array $data, bool $guided): BusinessContract
    {
        $quote = $this->quote($actor, $publicId, ! $guided);
        $notes = $data['technical_evidence'];
        ksort($notes);
        $hash = hash('sha256', json_encode([(int) $actor->id, $data['snapshot_hash'], (int) $data['implementation_version'], $notes], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($quote, $actor, $data, $notes, $hash, $guided): BusinessContract {
            Company::whereKey($quote->company_id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($quote->subscriber_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $quote = $this->quote($actor, $quote->public_id, ! $guided);
            abort_if($guided && (! $quote->isGuidedPackage() || $actor->role !== 'subscriber'), 403);
            // Subscriber is the mutex also used by standalone checkout and central activation.
            Subscription::where('subscriber_id', $quote->subscriber_id)->orderBy('id')->lockForUpdate()->get();
            [$receipt, $checkout, $invoice, $order] = $this->implementation->contract($quote, true);
            abort_unless(hash_equals($receipt->snapshot_hash, $data['snapshot_hash']), 409, 'El contrato cambió. Recarga la revisión.');
            $keyOwner = BusinessContract::where('company_id', $quote->company_id)->where('idempotency_key', $data['idempotency_key'])->first();
            abort_if($keyOwner && ((int) $keyOwner->service_quote_checkout_id !== (int) $checkout->id || ! hash_equals($keyOwner->request_hash, $hash)), 409, 'Esta solicitud ya se utilizó con otros datos.');
            $existing = BusinessContract::where('service_quote_checkout_id', $checkout->id)->first();
            if ($existing) {
                abort_unless($existing->snapshot === $receipt->snapshot && hash_equals($existing->snapshot_hash, $receipt->snapshot_hash), 409, 'El contrato requiere revisión.');

                return $existing;
            }
            abort_unless($order && $order->status === 'completed' && $order->lock_version === (int) $data['implementation_version'], 409, 'La implementación requiere conformidad vigente. Recarga la página.');
            $hold = $this->implementation->identityHold($quote) ?? $this->implementation->paymentHold($checkout, $invoice, true)
                ?? $this->transitionReason($quote, $checkout->id);
            abort_if($hold !== null, 409, $hold ?? 'El contrato requiere revisión.');
            $solutions = $this->readiness($quote, true);
            $expected = array_keys($solutions);
            sort($expected);
            abort_unless($expected === array_keys($notes), 422, 'Debes registrar evidencia técnica para cada solución contratada.');
            foreach ($solutions as $code => $solution) {
                abort_unless($solution['ready'], 409, $solution['reason'] ?? 'La solución requiere preparación.');
                abort_unless(is_string($notes[$code]) && mb_strlen(trim($notes[$code])) >= 10 && mb_strlen($notes[$code]) <= 2000, 422, 'Describe la comprobación técnica de cada solución (10 a 2000 caracteres).');
                $solutions[$code]['note'] = trim($notes[$code]);
            }
            $zone = $receipt->snapshot['timezone'];
            abort_unless(in_array($zone, timezone_identifiers_list(), true), 409, 'La zona horaria contractual requiere revisión.');
            $effective = CarbonImmutable::now($zone)->startOfSecond();
            $end = self::periodEnd($effective, $receipt->snapshot['billing_cycle']);
            $effective = $effective->utc();
            $end = $end->utc();
            $transition = isset($receipt->snapshot['transition']);
            if ($sourceId = $receipt->snapshot['self_service']['replaces_contract_id'] ?? null) {
                BusinessPackageChanges::finishDue((int) $sourceId);
            }
            $subscription = $transition ? app(BusinessTransitionService::class)->retireSources($quote)
                : Subscription::where('subscriber_id', $quote->subscriber_id)->where('status', 'active')->first();
            if (! $subscription) {
                $subscription = Subscription::create(['subscriber_id' => $quote->subscriber_id, 'created_by_user_id' => $actor->id,
                    'status' => 'active', 'currency' => $receipt->snapshot['currency'], 'billing_cycle' => $receipt->snapshot['billing_cycle']]);
            }
            $subscription->forceFill(['billing_cycle' => $receipt->snapshot['billing_cycle'], 'current_period_start' => $effective,
                'current_period_end' => $end, 'starts_at' => $effective, 'trial_ends_at' => null])->save();
            $items = [];
            foreach ($receipt->snapshot['products'] as $code => $product) {
                $item = $transition ? SubscriptionItem::where('subscription_id', $subscription->id)->where('service_id', $product['service_id'])->first() : null;
                $item ??= new SubscriptionItem;
                $item->fill(['subscription_id' => $subscription->id, 'service_id' => $product['service_id'],
                    'status' => 'active', 'billing_model' => 'flat', 'quantity' => 1, 'unit_price' => '0.00', 'amount' => '0.00',
                    'currency' => $receipt->snapshot['currency'], 'meta' => ['source' => 'business_contract', 'pricing' => 'included_in_contract',
                        'snapshot_hash' => $receipt->snapshot_hash, 'billing_cycle' => $receipt->snapshot['billing_cycle']]]);
                if ($transition) {
                    $item->fill(['block_size' => null, 'unit_name' => null, 'included_units' => null, 'overage_unit_price' => null]);
                    foreach (['service_plan_id' => null, 'billing_cycle' => $receipt->snapshot['billing_cycle']] as $column => $value) {
                        if (array_key_exists($column, $item->getAttributes())) {
                            $item->setAttribute($column, $value);
                        }
                    }
                }
                $item->save();
                $items[$code] = $item;
            }
            $amounts = $this->amounts->fromSnapshot($receipt->snapshot);
            $contract = BusinessContract::create(['service_quote_checkout_id' => $checkout->id, 'subscription_id' => $subscription->id,
                'company_id' => $quote->company_id, 'subscriber_id' => $quote->subscriber_id, 'activated_by' => $actor->id,
                'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash, 'snapshot_hash' => $receipt->snapshot_hash,
                'snapshot' => $receipt->snapshot, 'technical_evidence' => $solutions,
                'initial_invoice_total' => $this->amounts::decimal($amounts['total_cents']),
                'initial_invoice_subtotal' => $this->amounts::decimal($amounts['subtotal_cents']),
                'initial_invoice_tax' => $this->amounts::decimal($amounts['tax_cents']),
                'timezone' => $zone, 'activated_at' => $effective, 'period_ends_at' => $end]);
            foreach ($items as $code => $item) {
                DB::table('business_contract_items')->insert(['business_contract_id' => $contract->id, 'subscription_item_id' => $item->id,
                    'service_id' => $item->service_id, 'identity_link_id' => $solutions[$code]['link_id'], 'local_company_id' => $solutions[$code]['local_company_id']]);
            }
            if ($transition) {
                app(BusinessTransitionService::class)->record($quote, $contract, $items, (int) $actor->id);
            }
            app(SubscriptionTotalsService::class)->recalculate($subscription);
            AuditLog::create(['event' => 'business.contract.activated', 'model_type' => BusinessContract::class, 'model_id' => $contract->id,
                'user_id' => $actor->id, 'data' => ['snapshot_hash' => $receipt->snapshot_hash, 'invoice_id' => $checkout->invoice_id,
                    'subscription_id' => $subscription->id, 'implementation_order_id' => $order->id,
                    'activated_at' => $effective->toIso8601String(), 'period_ends_at' => $end->toIso8601String()]]);

            return $contract;
        }, 3);
    }
}
