<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\BusinessContract;
use App\Models\BusinessTransition;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ServiceCommercialQuote;
use App\Models\StandaloneServiceSettlement;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Deferred consolidation: the quote is the proposal, its receipt is consent, this ledger is execution. */
final class BusinessTransitionService
{
    public const MODE = 'after_paid_periods';

    public function __construct(private readonly BusinessQuoteInvoiceAmounts $amounts) {}

    private function rows(Builder $query): Collection
    {
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->orderBy('id')->get();
    }

    private function require(bool $valid, string $message): void
    {
        abort_unless($valid, 409, $message);
    }

    public function available(): bool
    {
        return config('business_commercial.transition_enabled') && Schema::hasTable('business_transitions')
            && Schema::hasTable('business_transition_items') && Schema::hasTable('business_transition_subscriptions');
    }

    public function preview(Company $company, array $products): array
    {
        if (! $this->available()) {
            return ['eligible' => false, 'reason' => 'La transición Business todavía no está habilitada.'];
        }
        try {
            return ['eligible' => true, 'reason' => null, ...$this->inventory($company, $products)];
        } catch (HttpExceptionInterface $error) {
            return ['eligible' => false, 'reason' => $error->getMessage()];
        }
    }

    /** Only explicit invoice periods or the original standalone settlement calendar are recognized. */
    public function inventory(Company $company, array $products, ?int $quoteId = null): array
    {
        $this->require($this->available(), 'La transición Business todavía no está habilitada.');
        $this->require(! BusinessContract::where('subscriber_id', $company->subscriber_id)->exists(), 'Ya existe un contrato Business; revisa su renovación.');
        $subscriptions = $this->rows(Subscription::where('subscriber_id', $company->subscriber_id)->whereNotIn('status', ['cancelled', 'expired']));
        $this->require($subscriptions->isNotEmpty(), 'No hay suscripciones abiertas para consolidar.');
        $items = $this->rows(SubscriptionItem::whereHas('subscription', fn ($q) => $q->where('subscriber_id', $company->subscriber_id)));
        $serviceIds = array_map('intval', array_column($products, 'service_id'));
        $sourceItems = $items->whereIn('subscription_id', $subscriptions->modelKeys());
        foreach ($items as $item) {
            if (! $subscriptions->contains('id', $item->subscription_id)) {
                $this->require(! in_array($item->status, ['active', 'trialing', 'pending'], true), 'Hay servicios vigentes en suscripciones cerradas; revisa su origen.');
            }
        }
        $before = ['subscriptions' => [], 'items' => [], 'invoices' => [], 'settlements' => [], 'bundles' => []];
        foreach ($subscriptions as $subscription) {
            $this->require($subscription->status === 'active' && $subscription->currency === $company->currency
                && ! $subscription->ends_at && ! $subscription->cancelled_at && ! $subscription->provider && ! $subscription->provider_subscription_id,
                'La suscripción #'.$subscription->id.' tiene estado, moneda, cancelación o proveedor que requiere revisión.');
            $before['subscriptions'][] = $subscription->only(['id', 'subscriber_id', 'status', 'currency', 'billing_cycle', 'current_period_start',
                'current_period_end', 'starts_at', 'ends_at', 'cancelled_at', 'trial_ends_at', 'provider', 'provider_subscription_id',
                'subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount']);
        }
        $this->require($sourceItems->isNotEmpty(), 'No hay servicios pagados que consolidar.');
        foreach ($sourceItems as $item) {
            $this->require($item->status === 'active' && in_array((int) $item->service_id, $serviceIds, true) && $item->currency === $company->currency,
                'El servicio #'.$item->id.' no está activo o está fuera del alcance de esta cotización.');
            foreach (($item->meta['entitlement_claims'] ?? []) as $claim) {
                $this->require(($claim['source'] ?? null) === 'standalone_settlement', 'Hay cobertura de Transformación u otro origen que requiere revisión separada.');
            }
            $this->require(! str_starts_with((string) ($item->meta['source'] ?? ''), 'transformation'), 'La cobertura de Transformación requiere revisión separada.');
            $before['items'][] = $item->only(['id', 'subscription_id', 'service_id', 'service_plan_id', 'status', 'currency', 'billing_model', 'billing_cycle', 'quantity', 'unit_price', 'amount', 'block_size', 'unit_name', 'included_units', 'overage_unit_price', 'meta']);
        }
        $companyIds = Company::where('subscriber_id', $company->subscriber_id)->pluck('id');
        $allSubscriptionIds = Subscription::where('subscriber_id', $company->subscriber_id)->pluck('id');
        $ownInvoice = $quoteId ? DB::table('service_quote_checkouts as c')->join('service_quote_acceptances as a', 'a.id', '=', 'c.service_quote_acceptance_id')->where('a.service_commercial_quote_id', $quoteId)->value('c.invoice_id') : null;
        $invoices = $this->rows(Invoice::where(function ($q) use ($allSubscriptionIds, $companyIds): void {
            $q->whereIn('subscription_id', $allSubscriptionIds)->orWhere(fn ($q) => $q->whereIn('company_id', $companyIds)
                ->whereIn('billing_snapshot->source', ['standalone_service_checkout', 'business_quote_checkout', 'business_renewal']));
        })->when($ownInvoice, fn ($q) => $q->where('id', '!=', $ownInvoice)));
        $settlements = $this->rows(StandaloneServiceSettlement::where('subscriber_id', $company->subscriber_id));
        $periods = [];
        $usedSettlements = [];
        foreach ($invoices as $invoice) {
            $payments = $this->rows($invoice->payments()->getQuery());
            if ($invoice->status === 'void' && $this->amounts->cents($invoice->amount_paid) === 0 && $payments->isEmpty()) {
                continue;
            }
            $this->require((int) $invoice->company_id === (int) $company->id && $invoice->currency === $company->currency,
                'Hay facturas de otra empresa o moneda; revisa la consolidación del suscriptor.');
            $lines = $this->rows($invoice->items()->getQuery());
            $total = $this->amounts->cents($invoice->total);
            $paid = 0;
            foreach ($payments as $payment) {
                $this->require((int) $payment->company_id === (int) $company->id && $payment->currency === $company->currency
                    && $payment->paid_at !== null && $this->amounts->cents($payment->amount) > 0 && $payment->paid_at->lessThanOrEqualTo(now()),
                    'La factura '.$invoice->number.' tiene pagos pendientes o incoherentes.');
                $paid += $this->amounts->cents($payment->amount);
            }
            foreach ($lines as $line) {
                $this->require($line->quantity > 0 && $this->amounts->cents($line->unit_price) * $line->quantity === $this->amounts->cents($line->line_subtotal)
                    && $this->amounts->cents($line->line_total) === $this->amounts->cents($line->line_subtotal) - $this->amounts->cents($line->discount_amount) + $this->amounts->cents($line->tax_amount),
                    'El detalle de la factura '.$invoice->number.' requiere conciliación.');
            }
            $sum = fn (string $column) => $lines->sum(fn ($line) => $this->amounts->cents($line->$column));
            $this->require($invoice->status === 'paid' && $total >= 0 && $paid === $total && $paid === $this->amounts->cents($invoice->amount_paid)
                && $lines->isNotEmpty() && $sum('line_subtotal') === $this->amounts->cents($invoice->subtotal)
                && $sum('discount_amount') === $this->amounts->cents($invoice->discount_total)
                && $sum('tax_amount') === $this->amounts->cents($invoice->tax_total)
                && $sum('line_total') === $total && $total === $sum('line_subtotal') - $sum('discount_amount') + $sum('tax_amount'),
                'La factura '.$invoice->number.' requiere pago completo y conciliación sin saldos a favor antes de transicionar.');
            $before['invoices'][] = [...$invoice->only(['id', 'subscription_id', 'company_id', 'number', 'currency', 'status', 'period_start', 'period_end',
                'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'billing_snapshot']),
                'lines' => $lines->map(fn ($line) => $line->only(['id', 'service_id', 'quantity', 'unit_price', 'line_subtotal', 'discount_amount', 'tax_amount', 'line_total']))->all(),
                'payments' => $payments->map(fn ($payment) => $payment->only(['id', 'company_id', 'currency', 'amount', 'paid_at']))->all()];
            if (($invoice->billing_snapshot['source'] ?? null) === 'standalone_service_checkout') {
                $ledger = $settlements->where('invoice_id', $invoice->id);
                $this->require($ledger->count() === 1 && $lines->count() === 1, 'La factura individual requiere un único settlement y detalle verificable.');
                $settlement = $ledger->first();
                $item = $sourceItems->firstWhere('id', $settlement->subscription_item_id);
                $line = $lines->first();
                $claim = $item?->meta['entitlement_claims']['standalone_settlement:'.$settlement->id] ?? null;
                $this->require($settlement->status === StandaloneServiceSettlement::STATUS_ACTIVATED && $item !== null
                    && (int) $settlement->company_id === (int) $company->id && (int) $settlement->subscription_id === (int) $item->subscription_id
                    && (int) $settlement->service_id === (int) $item->service_id && (int) $line->service_id === (int) $item->service_id
                    && (int) $settlement->invoice_item_id === (int) $line->id && $settlement->currency === $company->currency
                    && $settlement->settled_at !== null && in_array($settlement->billing_cycle, ['monthly', 'yearly'], true)
                    && $this->amounts->cents($settlement->amount_due) === $total && $this->amounts->cents($settlement->amount_paid) === $total
                    && ($claim['source'] ?? null) === 'standalone_settlement', 'La evidencia de activación individual requiere revisión.');
                foreach (['company_id', 'subscriber_id', 'service_id', 'activation_request_id', 'activation_request_service_id'] as $field) {
                    $this->require((int) ($invoice->billing_snapshot[$field] ?? 0) === (int) $settlement->$field, 'Las referencias de la factura individual no coinciden con su settlement.');
                }
                $this->require(($invoice->billing_snapshot['billing_cycle'] ?? null) === $settlement->billing_cycle, 'El ciclo de la factura individual no coincide con su settlement.');
                $start = CarbonImmutable::instance($settlement->settled_at)->utc();
                $this->require($start->equalTo(CarbonImmutable::parse(data_get($settlement->evidence_snapshot, 'settlement.fully_paid_at', '1900-01-01')))
                    && $payments->isNotEmpty() && $start->equalTo($payments->max('paid_at')), 'El inicio pagado del servicio individual no coincide con su evidencia.');
                // Preserve the legacy activation calendar (including its overflow), not the Business renewal calendar.
                $end = $settlement->billing_cycle === 'yearly' ? $start->addYearWithOverflow() : $start->addMonthWithOverflow();
                $this->require(($invoice->period_start === null && $invoice->period_end === null)
                    || ($invoice->period_start?->equalTo($start) && $invoice->period_end?->equalTo($end)), 'Las fechas de la factura individual contradicen su activación.');
                $before['settlements'][] = $settlement->only(['id', 'activation_request_service_id', 'activation_request_id', 'subscriber_id', 'company_id', 'service_id',
                    'invoice_id', 'invoice_item_id', 'subscription_id', 'subscription_item_id', 'status', 'billing_cycle', 'currency', 'amount_due', 'amount_paid', 'settled_at', 'evidence_snapshot']);
                $usedSettlements[] = $settlement->id;
                $periods[] = $this->period($item, $invoice, $start, $end, 'standalone_calendar');
            } else {
                $this->require($subscriptions->contains('id', $invoice->subscription_id) && $invoice->period_start !== null && $invoice->period_end !== null
                    && ! in_array($invoice->billing_snapshot['source'] ?? null, ['business_quote_checkout', 'business_renewal'], true),
                    'La factura '.$invoice->number.' no identifica un período de suscripción verificable.');
                foreach ($lines as $line) {
                    $item = $sourceItems->first(fn ($item) => (int) $item->subscription_id === (int) $invoice->subscription_id && (int) $item->service_id === (int) $line->service_id);
                    $this->require($item !== null, 'La factura contiene conceptos fuera de los servicios que se consolidarán.');
                    $periods[] = $this->period($item, $invoice, CarbonImmutable::instance($invoice->period_start)->utc(), CarbonImmutable::instance($invoice->period_end)->utc(), 'invoice_period');
                }
            }
        }
        foreach ($settlements as $settlement) {
            $this->require(in_array($settlement->id, $usedSettlements, true), 'Hay settlements adicionales o pendientes que requieren revisión.');
        }
        foreach ($sourceItems as $item) {
            $recognized = array_filter($periods, fn ($p) => $p['subscription_item_id'] === $item->id);
            $this->require($recognized !== [], 'El servicio #'.$item->id.' no tiene un período pagado verificable.');
            foreach (array_keys($item->meta['entitlement_claims'] ?? []) as $key) {
                $this->require(in_array($key, array_map(fn ($id) => 'standalone_settlement:'.$id, $usedSettlements), true), 'Hay claims sin evidencia de pago reconocida.');
            }
        }
        foreach ($subscriptions as $subscription) {
            $ends = array_column(array_filter($periods, fn ($p) => $p['subscription_id'] === $subscription->id), 'ends_at');
            $this->require($ends !== [] && $subscription->current_period_end !== null
                && $subscription->current_period_end->lessThanOrEqualTo(CarbonImmutable::parse(max($ends))), 'La suscripción tiene cobertura posterior o no respaldada por las facturas reconocidas.');
        }
        if (Schema::hasTable('subscription_bundle_discount_applications')) {
            $query = DB::table('subscription_bundle_discount_applications')->whereIn('subscription_id', $subscriptions->modelKeys())->where('active', true)->orderBy('id');
            $before['bundles'] = (DB::transactionLevel() ? $query->lockForUpdate() : $query)->get()->map(fn ($row) => (array) $row)->all();
        }
        $before = json_decode(json_encode($before, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return ['mode' => self::MODE, 'currency' => $company->currency, 'subscriber_id' => (int) $company->subscriber_id, 'company_id' => (int) $company->id,
            'not_before' => max(array_column($periods, 'ends_at')), 'credit_cents' => 0, 'refund_cents' => 0, 'proration_cents' => 0,
            'periods' => $periods, 'subscriptions' => $subscriptions->map(fn ($s) => ['id' => $s->id, 'billing_cycle' => $s->billing_cycle])->all(),
            'source_hash' => self::hash($before), 'evidence' => $before];
    }

    private function period(SubscriptionItem $item, Invoice $invoice, CarbonImmutable $start, CarbonImmutable $end, string $basis): array
    {
        $this->require($end->greaterThan($start), 'El período pagado contiene fechas inválidas.');

        return ['subscription_id' => $item->subscription_id, 'subscription_item_id' => $item->id, 'service_id' => $item->service_id,
            'name' => $item->service?->title ?? 'Servicio #'.$item->service_id, 'invoice_id' => $invoice->id, 'invoice_number' => $invoice->number,
            'paid_cents' => $this->amounts->cents($invoice->amount_paid), 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'basis' => $basis];
    }

    public static function hash(array $data): string
    {
        $sort = function (array $value) use (&$sort): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function proposal(Company $company, array $products, array $input): array
    {
        $this->require(($input['mode'] ?? null) === self::MODE && ($input['confirmed'] ?? false) === true, 'Confirma explícitamente la transición al vencer lo pagado.');
        $inventory = $this->inventory($company, $products);
        $this->require(hash_equals($inventory['source_hash'], (string) ($input['source_hash'] ?? '')), 'Los servicios o pagos cambiaron. Vuelve a simular la transición.');
        $target = (int) ($input['target_subscription_id'] ?? 0);
        $this->require(in_array($target, array_column($inventory['subscriptions'], 'id'), true), 'Elige una suscripción de origen para conservar.');

        return [...$inventory, 'target_subscription_id' => $target];
    }

    public function reason(ServiceCommercialQuote $quote, bool $activation = false): ?string
    {
        $plan = $quote->snapshot['transition'] ?? null;
        try {
            $this->require(is_array($plan) && ($plan['mode'] ?? null) === self::MODE, 'Falta la propuesta de transición.');
            $current = $this->inventory(Company::findOrFail($quote->company_id), $quote->snapshot['products'], $quote->id);
            $target = (int) ($plan['target_subscription_id'] ?? 0);
            $this->require(hash_equals(self::hash($plan), self::hash([...$current, 'target_subscription_id' => $target])) && in_array($target, array_column($current['subscriptions'], 'id'), true),
                'Los servicios, facturas o pagos de origen cambiaron. Solicita una nueva propuesta de transición.');
            if ($activation) {
                $this->require(CarbonImmutable::now('UTC')->greaterThanOrEqualTo(CarbonImmutable::parse($plan['not_before'])), 'La transición podrá activarse desde '.$plan['not_before'].'; todavía hay períodos pagados vigentes.');
            }

            return null;
        } catch (HttpExceptionInterface $error) {
            return $error->getMessage();
        }
    }

    /** Called inside activation's transaction, after all gates and before creating the new contract. */
    public function retireSources(ServiceCommercialQuote $quote): Subscription
    {
        $this->require(DB::transactionLevel() > 0, 'La transición requiere una transacción.');
        $reason = $this->reason($quote, true);
        $this->require($reason === null, $reason ?? 'La transición requiere revisión.');
        $plan = $quote->snapshot['transition'];
        foreach ($plan['evidence']['items'] as $source) {
            SubscriptionItem::findOrFail($source['id'])->update(['status' => 'cancelled']);
        }
        foreach ($plan['subscriptions'] as $source) {
            if ($source['id'] !== $plan['target_subscription_id']) {
                Subscription::findOrFail($source['id'])->update(['status' => 'expired', 'ends_at' => now()]);
            }
        }
        foreach ($plan['evidence']['settlements'] as $source) {
            StandaloneServiceSettlement::findOrFail($source['id'])->update(['status' => StandaloneServiceSettlement::STATUS_SUPERSEDED]);
        }
        if ($plan['evidence']['bundles'] !== []) {
            DB::table('subscription_bundle_discount_applications')->whereIn('id', array_column($plan['evidence']['bundles'], 'id'))
                ->update(['active' => false, 'superseded_at' => now(), 'updated_at' => now()]);
        }

        return Subscription::findOrFail($plan['target_subscription_id']);
    }

    public function record(ServiceCommercialQuote $quote, BusinessContract $contract, array $items, int $actor): void
    {
        $plan = $quote->snapshot['transition'];
        $execution = BusinessTransition::create(['service_commercial_quote_id' => $quote->id, 'business_contract_id' => $contract->id,
            'subscription_id' => $contract->subscription_id, 'executed_by' => $actor, 'executed_at' => $contract->activated_at,
            'source_hash' => $plan['source_hash'], 'snapshot' => $plan]);
        foreach ($plan['subscriptions'] as $source) {
            DB::table('business_transition_subscriptions')->insert(['business_transition_id' => $execution->id,
                'subscription_id' => $source['id'], 'retained' => $source['id'] === $plan['target_subscription_id']]);
        }
        foreach ($plan['evidence']['items'] as $source) {
            $destination = collect($items)->first(fn ($item) => (int) $item->service_id === (int) $source['service_id']);
            DB::table('business_transition_items')->insert(['business_transition_id' => $execution->id,
                'subscription_item_id' => $source['id'], 'destination_item_id' => $destination->id]);
        }
        AuditLog::create(['event' => 'business.transition.executed', 'model_type' => BusinessTransition::class, 'model_id' => $execution->id,
            'user_id' => $actor, 'data' => ['quote_public_id' => $quote->public_id, 'source_hash' => $plan['source_hash'],
                'retained_subscription_id' => $contract->subscription_id, 'not_before' => $plan['not_before'], 'executed_at' => $contract->activated_at->toIso8601String()]]);
        $companyIds = Company::where('subscriber_id', $quote->subscriber_id)->pluck('id')->all();
        $userIds = DB::table('subscriber_user')->where('subscriber_id', $quote->subscriber_id)->pluck('user_id')->all();
        DB::afterCommit(function () use ($companyIds, $userIds): void {
            foreach ($companyIds as $companyId) {
                foreach ($userIds as $userId) {
                    Cache::forget("subscriber.dashboard.stats.company.{$companyId}.user.{$userId}");
                }
            }
        });

    }
}
