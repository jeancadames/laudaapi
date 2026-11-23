<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\BusinessContract;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteCheckout;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\BusinessIdentity\CentralMembership;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class BusinessPackagePurchases
{
    public function __construct(
        private readonly BusinessPackagePricing $pricing,
        private readonly BusinessCatalogService $catalogs,
        private readonly BusinessQuoteAcceptanceService $acceptance,
        private readonly BusinessQuoteCheckoutService $billing,
    ) {}

    public function pendingId(Company $company): ?string
    {
        if (! config('business_commercial.checkout_enabled') || ! Schema::hasTable('service_quote_checkouts')) {
            return null;
        }
        $query = $this->companyPurchases($company)->join('invoices as i', 'i.id', '=', 'c.invoice_id')
            ->whereIn('i.status', ['issued', 'overdue', 'paid']);
        if (Schema::hasTable('business_contracts')) {
            $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('business_contracts')
                ->whereColumn('business_contracts.service_quote_checkout_id', 'c.id'));
        }

        return $query->latest('c.id')->value('service_commercial_quotes.public_id');
    }

    public function lastActivatedId(Company $company): ?string
    {
        if (! config('business_commercial.checkout_enabled') || ! Schema::hasTable('business_contracts')) {
            return null;
        }

        return $this->companyPurchases($company)
            ->join('business_contracts as contract', 'contract.service_quote_checkout_id', '=', 'c.id')
            ->latest('contract.activated_at')->latest('contract.id')->value('service_commercial_quotes.public_id');
    }

    private function companyPurchases(Company $company): Builder
    {
        return ServiceCommercialQuote::query()
            ->join('service_quote_acceptances as a', 'a.service_commercial_quote_id', '=', 'service_commercial_quotes.id')
            ->join('service_quote_checkouts as c', 'c.service_quote_acceptance_id', '=', 'a.id')
            ->where('c.company_id', $company->id)->where('c.subscriber_id', $company->subscriber_id)
            ->whereNotNull('service_commercial_quotes.snapshot->self_service->proposal_ref');
    }

    /** Returns the immutable quote and whether this request created its invoice. */
    public function confirm(User $user, Company $company, string $reference, string $key): array
    {
        $this->billing->ensureAvailable();
        try {
            $claims = json_decode(Crypt::decryptString($reference), true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->expired();
        }
        if (($claims['purpose'] ?? null) !== 'business_package_v1' || ($claims['company_id'] ?? null) !== $company->id
            || ($claims['subscriber_id'] ?? null) != $company->subscriber_id) {
            $this->expired();
        }

        return DB::transaction(function () use ($user, $company, $claims, $reference, $key) {
            ServiceCatalog::where('code', 'business')->lockForUpdate()->firstOrFail();
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($company->subscriber_id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('subscriber_user')->where('user_id', $user->id)->where('subscriber_id', $company->subscriber_id)->lockForUpdate()->get();
            app(CentralMembership::class)->authorize($user, $company, true);
            if ($claims['subscriber_id'] != $company->subscriber_id) {
                $this->expired();
            }
            $existing = ServiceCommercialQuote::where('company_id', $company->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->subscriber_id != $company->subscriber_id || data_get($existing->snapshot, 'self_service.proposal_ref') !== $reference) {
                    $this->problem(409, 'key_conflict', 'La clave pertenece a otra operación.');
                }

                return [$existing, false];
            }
            if ($pending = $this->pendingId($company)) {
                $this->problem(409, 'existing_purchase', 'Ya hay una compra en curso.', ['purchase_id' => $pending]);
            }
            if ($claims['expires_at'] <= CarbonImmutable::now('UTC')->timestamp) {
                $this->expired();
            }
            $version = $this->catalogs->current();
            if (! $version) {
                $this->problem(503, 'catalog_unavailable', 'El catálogo todavía no está disponible.');
            }
            $selection = $claims['selection'];
            Service::whereIn('id', array_column($version->payload['products'], 'service_id'))->orderBy('id')->lockForUpdate()->get();
            $catalog = $this->pricing->catalog($version, $company);
            $available = array_column($catalog['products'], 'code');
            $preview = array_diff($selection['products'], $available) ? null : $this->pricing->preview($version, $company, $selection);
            if (! $preview) {
                $this->problem(422, 'invalid_selection', 'Hay aplicaciones o niveles que ya no están disponibles.',
                    ['fields' => ['products' => ['Revisa la selección antes de continuar.']]]);
            }
            if ($claims['version_id'] !== $version->id || ! hash_equals($claims['summary_hash'], BusinessRenewalTerms::hash(Arr::except($preview, ['proposal_ref', 'expires_at'])))) {
                $this->problem(409, 'price_changed', 'Las condiciones cambiaron. Revisa el nuevo resumen.', ['replacement' => $preview]);
            }
            $preview['proposal_ref'] = $reference;
            $preview['expires_at'] = CarbonImmutable::createFromTimestampUTC($claims['expires_at'])->toIso8601String();
            try {
                $quote = $this->catalogs->issue($version, $version->lock_version, $company, $selection, $key, $user->id, packagePreview: $preview);
                $hash = BusinessQuoteAcceptanceService::fingerprint($quote);
                $this->acceptance->accept($user, $quote->public_id, $hash, $key);
                $this->billing->checkout($user, $quote->public_id, $hash, $key);
            } catch (\DomainException $e) {
                $this->problem(409, 'transition_required', 'Revisa las condiciones de tu empresa antes de contratar. '.$e->getMessage());
            } catch (HttpExceptionInterface $e) {
                if ($e->getStatusCode() !== 409) {
                    throw $e;
                }
                $this->problem(409, 'transition_required', $e->getMessage());
            }

            return [$quote, true];
        }, 3);
    }

    public function quote(Company $company, string $publicId): ServiceCommercialQuote
    {
        $this->billing->ensureAvailable();
        $quote = ServiceCommercialQuote::where('public_id', $publicId)->where('company_id', $company->id)
            ->where('subscriber_id', $company->subscriber_id)->whereNotNull('snapshot->self_service->proposal_ref')->first();
        if (! $quote) {
            $this->problem(404, 'purchase_not_found', 'No se encontró la compra.');
        }
        return $quote;
    }

    public function details(User $actor, Company $company, string $publicId): array
    {
        $quote = $this->quote($company, $publicId);
        $receipt = $this->billing->acceptance($quote);
        $checkout = ServiceQuoteCheckout::where('service_quote_acceptance_id', $receipt->id)->firstOrFail();
        $bill = $this->billing->invoiceDetails($checkout, $receipt);
        $payment = ['status' => 'preparing', 'action' => null];
        if (! in_array($bill['status'], ['issued', 'overdue', 'paid'], true)) {
            $payment = ['status' => 'failed', 'message' => 'Esta factura no está disponible para pago.', 'action' => null];
        } elseif ($bill['balance_cents'] === 0) {
            $hold = app(BusinessImplementationService::class)->paymentHold($checkout, $bill);
            $payment['status'] = $hold === null ? 'paid' : 'processing';
        } elseif (Schema::hasTable('business_contracts') && BusinessContract::where('service_quote_checkout_id', $checkout->id)->exists()) {
            $payment['status'] = 'reversed';
        } elseif ($transfer = app(BusinessTransferProofs::class)->form($quote, $checkout)) {
            $action = ['label' => 'Enviar comprobante', 'url' => route('business.quotes.checkout.show', $publicId, false)];
            $payment = match ($transfer['status']) {
                'pending' => ['status' => 'processing', 'action' => null],
                'rejected' => ['status' => 'failed', 'message' => $transfer['reason'], 'action' => $action],
                default => ['status' => 'payable', 'action' => $action],
            };
        } elseif ($bill['paid_cents'] > 0) {
            $payment['status'] = 'processing';
        }
        $summary = $quote->snapshot['self_service'];
        $access = app(BusinessPackageAccess::class)->details($actor, $company, $quote, $checkout, $payment['status']);

        $previous = isset($summary['replaces_contract_id'])
            ? $this->companyPurchases($company)->join('business_contracts as previous', 'previous.service_quote_checkout_id', '=', 'c.id')
                ->where('previous.id', $summary['replaces_contract_id'])->value('service_commercial_quotes.public_id') : null;

        return ['purchase_id' => $publicId, 'invoice_number' => $bill['number'], 'currency' => $summary['currency'],
            'previous_package_url' => $previous ? route('business.package.manage', $previous, false) : null,
            'manage_url' => Schema::hasTable('business_package_changes') && BusinessContract::where('service_quote_checkout_id', $checkout->id)->exists()
                ? route('business.package.manage', $publicId, false) : null,
            'summary' => $summary, 'balance_cents' => $bill['balance_cents'], 'payment' => $payment, 'access' => $access];
    }

    private function expired(): never
    {
        $this->problem(409, 'proposal_expired', 'Vuelve a calcular el paquete antes de confirmarlo.');
    }

    private function problem(int $status, string $code, string $message, array $extra = []): never
    {
        throw new HttpResponseException(response()->json(compact('code', 'message') + $extra, $status,
            ['Cache-Control' => 'private, no-store']));
    }
}
