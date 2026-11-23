<?php

namespace App\Services\Billing;

use App\Models\BusinessContract;
use App\Models\BusinessRenewalAcceptance;
use App\Models\BusinessRenewalPeriod;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ServiceCatalog;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteCheckout;
use App\Models\ServiceQuoteAcceptance;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BusinessIdentity\CentralMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class BusinessPackageChanges
{
    public function __construct(
        private readonly BusinessPackagePurchases $purchases,
        private readonly BusinessCatalogService $catalogs,
        private readonly BusinessPackagePricing $pricing,
    ) {}

    private function contract(Company $company, string $purchase): BusinessContract
    {
        abort_unless(Schema::hasTable('business_package_changes'), 503, 'La administración de paquetes todavía no está disponible.');
        $quote = $this->purchases->quote($company, $purchase);

        return BusinessContract::where('company_id', $company->id)->where('subscriber_id', $company->subscriber_id)
            ->where('service_quote_checkout_id', ServiceQuoteCheckout::where('service_quote_acceptance_id', $quote->acceptance()->firstOrFail()->id)->value('id'))
            ->firstOrFail();
    }

    private function saved(BusinessContract $contract): ?array
    {
        $row = DB::table('business_package_changes')->where('business_contract_id', $contract->id)->first();
        if (! $row) {
            return null;
        }
        $next = $row->replacement_quote_id ? ServiceCommercialQuote::findOrFail($row->replacement_quote_id) : null;

        return ['products' => json_decode($row->products, true, flags: JSON_THROW_ON_ERROR),
            'effective_at' => CarbonImmutable::parse($row->effective_at, 'UTC')->toIso8601String(),
            'total_cents' => $next?->snapshot['self_service']['recurring']['total_cents'] ?? 0,
            'purchase_url' => $next ? route('business.package.build', ['compra' => $next->public_id], false) : null,
            'terms_hash' => $row->terms_hash];
    }

    private function initialPaymentHold(BusinessContract $contract, bool $lock = false): ?string
    {
        $checkout = ServiceQuoteCheckout::findOrFail($contract->service_quote_checkout_id);
        $receipt = ServiceQuoteAcceptance::findOrFail($checkout->service_quote_acceptance_id);
        $bill = app(BusinessQuoteCheckoutService::class)->invoiceDetails($checkout, $receipt);

        return app(BusinessImplementationService::class)->paymentHold($checkout, $bill, $lock);
    }

    /** A paid renewal can extend access even when its start date is still in the future. */
    private function paidThrough(BusinessContract $contract): CarbonImmutable
    {
        $hold = $this->initialPaymentHold($contract, true);
        abort_if($hold !== null, 409, $hold ?? '');
        $end = $contract->period_ends_at;
        if (! Schema::hasTable('business_renewal_periods')) {
            return $end;
        }
        $periods = BusinessRenewalPeriod::where('business_contract_id', $contract->id)->orderBy('period_number')->get();
        $committed = BusinessRenewalAcceptance::where('business_contract_id', $contract->id)->whereNotIn('id', $periods->pluck('business_renewal_acceptance_id'))->exists();
        abort_if($committed, 409, 'Hay una renovación aceptada pendiente de facturar. Revisa esa renovación con LAUDA.');
        foreach ($periods as $period) {
            $invoice = app(BusinessRenewalService::class)->invoiceDetails($contract, $period);
            abort_unless($invoice['covered'], 409, 'Hay una factura de renovación pendiente de conciliación.');
            $end = $period->period_ends_at;
        }

        return $end;
    }

    public function page(Company $company, string $purchase): array
    {
        $contract = $this->contract($company, $purchase);
        $version = $this->catalogs->current();
        $current = $contract->snapshot['self_service']['selection']
            + BusinessContractPeriods::packageSummary($contract, $this->initialPaymentHold($contract) === null);
        $names = [];
        foreach ($contract->snapshot['products'] as $code => $product) {
            $names[$code] = $product['name'] ?? $code;
        }
        $available = $version ? $this->pricing->catalog($version, $company)['products'] : [];
        foreach ($available as $product) {
            $names[$product['code']] = $product['name'];
        }

        return ['current' => $current, 'names' => $names, 'available' => array_column($available, 'code'),
            'company' => $company->name, 'timezone' => $contract->timezone,
            'change' => $this->saved($contract), 'back_url' => route('business.package.build', ['compra' => $purchase], false),
            'preview_url' => route('business.package.change.preview', $purchase, false),
            'confirm_url' => route('business.package.change.confirm', $purchase, false)];
    }

    public function review(User $user, Company $company, string $purchase, array $products, ?string $acceptedHash): array
    {
        return DB::transaction(function () use ($user, $company, $purchase, $products, $acceptedHash) {
            ServiceCatalog::where('code', 'business')->lockForUpdate()->first();
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($company->subscriber_id)->lockForUpdate()->firstOrFail();
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('subscriber_user')->where('subscriber_id', $company->subscriber_id)->where('user_id', $actor->id)->lockForUpdate()->get();
            app(CentralMembership::class)->authorize($actor, $company, true);
            $contract = $this->contract($company, $purchase);
            if ($saved = $this->saved($contract)) {
                sort($products);
                abort_unless($acceptedHash && hash_equals($saved['terms_hash'], $acceptedHash) && $products === $saved['products'], 409, 'Ya se registró un cambio para este paquete.');

                return $saved;
            }
            $subscription = Subscription::whereKey($contract->subscription_id)->lockForUpdate()->firstOrFail();
            abort_unless($subscription->status === 'active' && ! $subscription->ends_at && ! $subscription->cancelled_at, 409, 'La suscripción requiere revisión.');
            $effective = $this->paidThrough($contract);
            abort_unless($effective->isFuture(), 409, 'El período contratado ya terminó.');
            $selection = $contract->snapshot['self_service']['selection'];
            sort($products);
            $original = $selection['products'];
            sort($original);
            abort_if($original === $products, 422, 'Selecciona qué productos quieres cambiar.');
            $selection['products'] = $products;
            $summary = null;
            if ($products) {
                $version = $this->catalogs->current();
                abort_unless($version, 409, 'No hay un catálogo publicado para calcular este cambio.');
                $available = array_column($this->pricing->catalog($version, $company)['products'], 'code');
                abort_if(array_diff($products, $available), 422, 'Una aplicación seleccionada ya no está disponible.');
                $summary = $this->pricing->preview($version, $company, $selection);
                abort_unless($summary, 422, 'Esta combinación no está disponible para el nivel contratado.');
                $summary['one_time'] = array_fill_keys(array_keys($summary['one_time']), 0);
                $summary['due_today'] = $summary['recurring'];
                $summary['due_today_cents'] = $summary['recurring']['total_cents'];
                $summary['replaces_contract_id'] = $contract->id;
                $summary['effective_at'] = $effective->toIso8601String();
            }
            $review = ['products' => $products, 'effective_at' => $effective->toIso8601String(),
                'total_cents' => $summary['due_today_cents'] ?? 0];
            $review['terms_hash'] = BusinessRenewalTerms::hash([$contract->id, $review, $summary ? Arr::except($summary, ['proposal_ref', 'expires_at']) : null]);
            if ($acceptedHash === null) {
                return $review;
            }
            abort_unless(hash_equals($review['terms_hash'], $acceptedHash), 409, 'Cambió la fecha o el precio. Revisa el resumen otra vez.');
            $next = null;
            if ($summary) {
                $conflict = self::replacementConflict($contract);
                abort_if($conflict, 409, $conflict ?? '');
                $key = (string) Str::uuid();
                $next = $this->catalogs->issue($version, $version->lock_version, $company, $selection, $key, $actor->id, packagePreview: $summary);
            }
            DB::table('business_package_changes')->insert(['business_contract_id' => $contract->id,
                'requested_by' => $actor->id, 'effective_at' => $effective->utc(), 'products' => json_encode($products, JSON_THROW_ON_ERROR),
                'terms_hash' => $acceptedHash, 'replacement_quote_id' => $next?->id, 'created_at' => now(), 'updated_at' => now()]);
            $subscription->forceFill(['ends_at' => $effective])->save();
            if ($next) {
                $hash = BusinessQuoteAcceptanceService::fingerprint($next);
                app(BusinessQuoteAcceptanceService::class)->accept($actor, $next->public_id, $hash, $key);
                app(BusinessQuoteCheckoutService::class)->checkout($actor, $next->public_id, $hash, $key);
            }

            return $this->saved($contract);
        }, 3);
    }

    /** Reconcile stored subscription statuses after the access deadline (also enforced on every launch). */
    public static function finishDue(?int $contractId = null): void
    {
        if (! Schema::hasTable('business_package_changes')) {
            return;
        }
        DB::table('business_package_changes as change_request')
            ->join('business_contracts as contract', 'contract.id', '=', 'change_request.business_contract_id')
            ->join('subscriptions as sub', 'sub.id', '=', 'contract.subscription_id')
            ->whereColumn('sub.ends_at', 'change_request.effective_at')->where('sub.ends_at', '<=', now())->where('sub.status', 'active')
            ->when($contractId !== null, fn ($q) => $q->where('contract.id', $contractId))
            ->select('sub.id as subscription_id', 'contract.id as contract_id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row): void {
                        $updated = Subscription::whereKey($row->subscription_id)->where('status', 'active')
                            ->where('ends_at', '<=', now())->update(['status' => 'expired']);
                        if ($updated) {
                            DB::table('subscription_items')->where('subscription_id', $row->subscription_id)
                                ->whereIn('id', DB::table('business_contract_items')->where('business_contract_id', $row->contract_id)->select('subscription_item_id'))
                                ->whereIn('status', ['active', 'trialing'])->update(['status' => 'cancelled', 'updated_at' => now()]);
                        }
                    }, 3);
                }
            }, 'sub.id', 'subscription_id');
    }

    /** A replacement may coexist only with its own preceding chain of Business purchases. */
    private static function replacementConflict(BusinessContract $source, ?int $newCheckout = null): ?string
    {
        $subs = $checkouts = [];
        $current = $source;
        do {
            $subs[] = $current->subscription_id;
            $checkouts[] = $current->service_quote_checkout_id;
            $parent = DB::table('business_package_changes as ch')
                ->join('service_quote_acceptances as a', 'a.service_commercial_quote_id', '=', 'ch.replacement_quote_id')
                ->join('service_quote_checkouts as c', 'c.service_quote_acceptance_id', '=', 'a.id')
                ->where('c.id', $current->service_quote_checkout_id)->value('ch.business_contract_id');
            $current = $parent ? BusinessContract::whereKey($parent)->where('company_id', $source->company_id)
                ->where('subscriber_id', $source->subscriber_id)->firstOrFail() : null;
        } while ($current && ! in_array($current->subscription_id, $subs));
        if ($newCheckout) {
            $checkouts[] = $newCheckout;
        }
        $other = Subscription::where('subscriber_id', $source->subscriber_id)->whereNotIn('id', $subs)->whereNotIn('status', ['expired', 'cancelled'])->exists()
            || ServiceQuoteCheckout::where('subscriber_id', $source->subscriber_id)->whereNotIn('id', $checkouts)
                ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void')->orWhere('amount_paid', '>', 0))->exists()
            || Invoice::where('company_id', $source->company_id)->where('billing_snapshot->source', 'standalone_service_checkout')
                ->where(fn ($q) => $q->where('status', '!=', 'void')->orWhere('amount_paid', '>', 0))->exists();

        return $other ? 'Hay otras contrataciones que requieren revisión antes del cambio.' : null;
    }

    public static function replacementHold(ServiceCommercialQuote $quote, ?int $checkout = null): ?string
    {
        $source = BusinessContract::whereKey($quote->snapshot['self_service']['replaces_contract_id'])
            ->where('company_id', $quote->company_id)->where('subscriber_id', $quote->subscriber_id)->firstOrFail();
        $change = DB::table('business_package_changes')->where('business_contract_id', $source->id)->where('replacement_quote_id', $quote->id)->first();
        abort_unless($change && Subscription::findOrFail($source->subscription_id)->ends_at?->equalTo($change->effective_at), 409, 'La referencia al paquete anterior requiere revisión.');
        if ($checkout && CarbonImmutable::parse($change->effective_at, 'UTC')->isFuture()) {
            return 'Podrás activar el nuevo paquete al terminar el período que ya pagaste.';
        }

        return self::replacementConflict($source, $checkout);
    }
}
