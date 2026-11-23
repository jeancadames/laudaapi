<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BusinessCatalogService
{
    public function __construct(
        private readonly BusinessCatalogRules $rules,
        private readonly BusinessQuoteCalculator $calculator,
    ) {}

    public function current(): ?ServiceCatalogVersion
    {
        // A retired effective version must not resurrect an older offer.
        $version = ServiceCatalogVersion::query()
            ->where('service_catalog_id', ServiceCatalog::where('code', 'business')->value('id'))
            ->whereNotNull('published_at')->where('effective_at', '<=', CarbonImmutable::now('UTC'))
            ->orderByDesc('effective_at')->orderByDesc('version')->first();

        return $version?->status === 'published' ? $version : null;
    }

    public function revise(ServiceCatalogVersion $source, int $lockVersion, int $actor): ServiceCatalogVersion
    {
        return DB::transaction(function () use ($source, $lockVersion, $actor) {
            $locked = $this->lock($source, $lockVersion);
            $next = ServiceCatalogVersion::where('service_catalog_id', $locked->service_catalog_id)->max('version') + 1;
            $draft = ServiceCatalogVersion::create([
                'service_catalog_id' => $locked->service_catalog_id, 'version' => $next,
                'payload' => $locked->payload, 'status' => 'draft', 'created_by' => $actor,
            ]);
            $this->audit($draft, 'business.catalog.revised', [], $draft->toArray(), $actor);

            return $draft;
        });
    }

    public function save(ServiceCatalogVersion $version, int $lockVersion, array $payload, int $actor): ServiceCatalogVersion
    {
        // HTTP middleware converts empty optional text to null; keep the catalog's string representation.
        if (array_key_exists('terms', $payload) && $payload['terms'] === null) {
            $payload['terms'] = '';
        }
        if (is_array($payload['products'] ?? null)) {
            foreach ($payload['products'] as $code => $product) {
                if (is_array($product) && array_key_exists('description', $product) && $product['description'] === null) {
                    $payload['products'][$code]['description'] = '';
                }
            }
        }
        $this->rules->validate($payload);

        return DB::transaction(function () use ($version, $lockVersion, $payload, $actor) {
            $locked = $this->lock($version, $lockVersion);
            $this->rules->require($locked->status === 'draft', 'status', 'Cree otra revisión para cambiar una versión publicada.');
            $before = $locked->toArray();
            $locked->update(['payload' => $payload, 'lock_version' => $locked->lock_version + 1]);
            $this->audit($locked, 'business.catalog.saved', $before, $locked->toArray(), $actor);

            return $locked;
        });
    }

    public function publish(ServiceCatalogVersion $version, int $lockVersion, ?string $effectiveAt, int $actor): ServiceCatalogVersion
    {
        return DB::transaction(function () use ($version, $lockVersion, $effectiveAt, $actor) {
            $locked = $this->lock($version, $lockVersion);
            $this->rules->require($locked->status === 'draft', 'status', 'Solo se publica un borrador.');
            $payload = $locked->payload;
            $payload['tax_rate'] ??= 0;
            $this->rules->validate($payload, true);
            $ids = [];
            foreach ($payload['products'] as $code => $product) {
                if (! $product['available']) {
                    continue;
                }
                $service = Service::whereKey($product['service_id'])->first();
                $this->rules->require($service !== null && $service->service_key === $code && $service->active, "products.$code.service_id", 'Vincule el Service activo de esta solución, comprobado en el hub.');
                $ids[] = $service->id;
            }
            $this->rules->require(count(array_unique($ids)) === count($ids), 'products', 'Cada solución requiere un Service distinto.');
            foreach ($payload['discounts'] as $discount) {
                $this->rules->require($discount['company_id'] === null || Company::whereKey($discount['company_id'])->exists(), 'discounts', 'La empresa de la condición particular no existe.');
            }
            $effective = $effectiveAt === null ? CarbonImmutable::now('UTC') : CarbonImmutable::instance($this->rules->date($effectiveAt, 'effective_at'));
            $this->rules->require($effectiveAt === null || $effective->greaterThanOrEqualTo(CarbonImmutable::now('UTC')), 'effective_at', 'La vigencia no puede ser retroactiva.');
            $latest = ServiceCatalogVersion::where('service_catalog_id', $locked->service_catalog_id)->whereNotNull('published_at')->max('effective_at');
            $this->rules->require($latest === null || $effective->greaterThan(CarbonImmutable::parse($latest)), 'effective_at', 'La nueva publicación debe ser posterior a las vigencias ya programadas.');
            $before = $locked->toArray();
            $locked->update(['payload' => $payload, 'status' => 'published', 'effective_at' => $effective->utc(),
                'published_at' => CarbonImmutable::now('UTC'), 'published_by' => $actor, 'lock_version' => $locked->lock_version + 1]);
            $this->audit($locked, 'business.catalog.published', $before, $locked->toArray(), $actor);

            return $locked;
        });
    }

    public function retire(ServiceCatalogVersion $version, int $lockVersion, int $actor): ServiceCatalogVersion
    {
        return DB::transaction(function () use ($version, $lockVersion, $actor) {
            $locked = $this->lock($version, $lockVersion);
            $this->rules->require($locked->status === 'published', 'status', 'Solo se retira una versión publicada.');
            $before = $locked->toArray();
            $locked->update(['status' => 'retired', 'retired_at' => CarbonImmutable::now('UTC'), 'lock_version' => $locked->lock_version + 1]);
            $this->audit($locked, 'business.catalog.retired', $before, $locked->toArray(), $actor);

            return $locked;
        });
    }

    public function preview(ServiceCatalogVersion $version, array $selection, ?Company $company): array
    {
        $quote = $this->calculator->calculate($version->payload, $selection, CarbonImmutable::now('UTC'), $company?->id);
        return $this->withCommercialContext($quote, $version, $company);
    }

    private function withCommercialContext(array $quote, ServiceCatalogVersion $version, ?Company $company): array
    {
        $quote['catalog_version_id'] = $version->id;
        $quote['catalog_version'] = $version->version;
        $quote['catalog_lock_version'] = $version->lock_version;
        $quote['catalog_effective_at'] = $version->effective_at?->toIso8601String();
        foreach ($quote['price_sources'] as &$source) {
            $source['catalog_version_id'] = $version->id;
        }
        unset($source);
        $quote['active_services'] = $company ? SubscriptionItem::query()
            ->whereHas('subscription', fn ($query) => $query->where('subscriber_id', $company->subscriber_id)->whereIn('status', ['active', 'trialing']))
            ->activeOrTrialing()->with('service:id,title,service_key')->get(['id', 'service_id', 'amount', 'meta'])
            ->map(fn ($item) => ['subscription_item_id' => $item->id, 'service_id' => $item->service_id, 'name' => $item->service?->title, 'amount' => $item->amount])->all() : [];
        $quote['transition_requires_review'] = $company && ($quote['active_services'] !== []
            || Subscription::where('subscriber_id', $company->subscriber_id)->whereNotIn('status', ['cancelled', 'expired'])->count() > 1
            || Invoice::whereIn('subscription_id', Subscription::where('subscriber_id', $company->subscriber_id)->select('id'))->exists()
            || Invoice::where('company_id', $company->id)->where('billing_snapshot->source', 'standalone_service_checkout')->where('status', '!=', 'void')->exists());
        $quote['transition_review'] = $quote['transition_requires_review'] ? app(BusinessTransitionService::class)->preview($company, $quote['products']) : null;
        $quote['new_charge_start'] = null;
        $quote['commercial_status'] = 'simulation';

        return $quote;
    }

    public function issue(ServiceCatalogVersion $version, int $lockVersion, Company $company, array $selection, string $key, int $actor, ?array $transition = null, ?array $packagePreview = null): ServiceCommercialQuote
    {
        return DB::transaction(function () use ($version, $lockVersion, $company, $selection, $key, $actor, $transition, $packagePreview) {
            // Uniform lock order: catalogue, company, quote. A repeated issuance
            // returns its original snapshot even after retirement or expiry.
            ServiceCatalog::whereKey($version->service_catalog_id)->lockForUpdate()->firstOrFail();
            $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $fingerprint = $selection;
            ksort($fingerprint);
            $fingerprint['addons'] ??= [];
            ksort($fingerprint['addons']);
            $hashInput = [$version->id, $lockVersion, $fingerprint];
            if ($packagePreview !== null) {
                $hashInput[] = hash('sha256', $packagePreview['proposal_ref']);
            }
            if ($transition !== null) {
                $hashInput[] = BusinessTransitionService::hash($transition);
            }
            $hash = hash('sha256', json_encode($hashInput, JSON_THROW_ON_ERROR));
            $existing = ServiceCommercialQuote::where('company_id', $company->id)->where('idempotency_key', $key)->first();
            if ($existing) {
                $this->rules->require(hash_equals($existing->request_hash, $hash), 'idempotency_key', 'La clave ya corresponde a otra cotización.');

                return $existing;
            }
            $locked = $this->lock($version, $lockVersion);
            $this->rules->require($this->current()?->id === $locked->id, 'version', 'Solo se emiten cotizaciones de la versión publicada vigente.');
            $this->rules->capacityPolicy($locked->payload['capacity_policy'] ?? null);
            $this->rules->require((bool) $company->active && $company->subscriber_id !== null, 'company', 'Se requiere una empresa activa vinculada a Subscriber.');
            $subscriber = Subscriber::whereKey($company->subscriber_id)->lockForUpdate()->first();
            $this->rules->require($subscriber !== null && (bool) $subscriber->active, 'company', 'El Subscriber debe estar activo.');
            $this->rules->require($company->currency === $locked->payload['currency'] && $subscriber->currency === $locked->payload['currency'], 'company', 'La moneda de Company y Subscriber debe coincidir con la cotización; no se convierte automáticamente.');
            $snapshot = $packagePreview === null ? $this->preview($locked, $selection, $company)
                : $this->withCommercialContext(app(BusinessPackagePricing::class)->contractSnapshot($locked, $company, $packagePreview), $locked, $company);
            $services = Service::whereIn('id', array_column($snapshot['products'], 'service_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($snapshot['products'] as $code => $product) {
                $service = $services->get($product['service_id']);
                $this->rules->require($service !== null && $service->active && $service->service_key === $code, 'products', 'La solución dejó de estar disponible para nuevas contrataciones.');
            }
            if ($transition !== null) {
                app(BusinessImplementationService::class)->authorizeAdmin(User::findOrFail($actor));
                $snapshot['transition'] = app(BusinessTransitionService::class)->proposal($company, $snapshot['products'], $transition);
            } else {
                $this->rules->require(isset($packagePreview['replaces_contract_id']) || ! $snapshot['transition_requires_review'], 'company', 'Esta empresa requiere reconocer sus servicios y períodos pagados antes de emitir una nueva contratación (F4).');
            }
            unset($snapshot['transition_review']);
            $snapshot['commercial_status'] = 'quoted';
            $issued = CarbonImmutable::now('UTC');
            $valid = $issued->addDays($locked->payload['quote_valid_days']);
            $snapshot['issued_at'] = $issued->toIso8601String();
            $snapshot['valid_until'] = $valid->toIso8601String();
            $quote = ServiceCommercialQuote::create([
                'public_id' => (string) Str::uuid(), 'service_catalog_version_id' => $locked->id,
                'company_id' => $company->id, 'subscriber_id' => $company->subscriber_id, 'created_by' => $actor,
                'idempotency_key' => $key, 'request_hash' => $hash, 'snapshot' => $snapshot,
                'issued_at' => $issued, 'valid_until' => $valid,
            ]);
            AuditLog::create(['event' => 'business.quote.issued', 'model_type' => ServiceCommercialQuote::class,
                'model_id' => $quote->id, 'user_id' => $actor, 'data' => ['public_id' => $quote->public_id, 'catalog_version_id' => $locked->id]]);

            return $quote;
        }, 3);
    }

    private function lock(ServiceCatalogVersion $version, int $lockVersion): ServiceCatalogVersion
    {
        $catalog = ServiceCatalog::whereKey($version->service_catalog_id)->where('code', 'business')->lockForUpdate()->firstOrFail();
        $locked = ServiceCatalogVersion::whereKey($version->id)->where('service_catalog_id', $catalog->id)->lockForUpdate()->firstOrFail();
        $this->rules->require($locked->lock_version === $lockVersion, 'lock_version', 'Otra persona cambió esta versión. Recargue antes de continuar.');

        return $locked;
    }

    private function audit(ServiceCatalogVersion $version, string $event, array $before, array $after, int $actor): void
    {
        AuditLog::create(['event' => $event, 'model_type' => ServiceCatalogVersion::class, 'model_id' => $version->id,
            'user_id' => $actor, 'data' => ['before' => $before, 'after' => $after]]);
    }
}
