<?php

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVersion;
use App\Models\ServiceCommercialQuote;
use App\Models\ServiceQuoteAcceptance;
use App\Models\Subscriber;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Services\BusinessIdentity\CentralMembership;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class BusinessQuoteAcceptanceService
{
    public function __construct(private readonly CentralMembership $membership) {}

    public function ensureAvailable(): void
    {
        abort_unless(config('business_commercial.enabled'), 404);
        abort_unless(Schema::hasTable('service_commercial_quotes') && Schema::hasTable('service_quote_acceptances'), 503,
            'Las cotizaciones todavía no están disponibles.');
    }

    public function visibleQuotes(User $user): Builder
    {
        $this->ensureAvailable();
        abort_unless($user->hasVerifiedEmail(), 403);

        return ServiceCommercialQuote::query()->whereIn('company_id', Company::query()->select('companies.id')->where('active', true)
            ->whereColumn('companies.subscriber_id', 'service_commercial_quotes.subscriber_id')
            ->whereIn('subscriber_id', DB::table('subscriber_user')->join('subscribers', 'subscribers.id', '=', 'subscriber_user.subscriber_id')
                ->where('subscriber_user.user_id', $user->id)->where('subscriber_user.active', true)
                ->where('subscribers.active', true)->whereIn('subscriber_user.role', ['owner', 'admin'])->select('subscribers.id')));
    }

    public function quote(User $user, string $publicId): ServiceCommercialQuote
    {
        $quote = $this->visibleQuotes($user)->where('public_id', $publicId)->firstOrFail();
        $this->authorize($user, $quote);

        return $quote;
    }

    private function authorize(User $user, ServiceCommercialQuote $quote): Company
    {
        $company = Company::findOrFail($quote->company_id);
        abort_unless((int) $company->subscriber_id === (int) $quote->subscriber_id, 403);
        $this->membership->authorize($user, $company, true);

        return $company;
    }

    public function summary(ServiceCommercialQuote $quote): array
    {
        $acceptance = $quote->acceptance;

        return [
            'public_id' => $quote->public_id, 'company_id' => $quote->company_id,
            'company_name' => Company::whereKey($quote->company_id)->value('name'),
            'offer_name' => $quote->snapshot['offer']['name'], 'level_name' => $quote->snapshot['level']['name'],
            'currency' => $quote->snapshot['currency'], 'timezone' => $quote->snapshot['timezone'],
            'recurring_cents' => $quote->snapshot['recurring_cents'], 'billing_cycle' => $quote->snapshot['billing_cycle'],
            'issued_at' => $quote->issued_at->toIso8601String(), 'valid_until' => $quote->valid_until->toIso8601String(),
            'status' => $acceptance ? 'accepted' : ($this->unavailableReason($quote) === null ? 'available' : 'unavailable'),
            'url' => route('business.quotes.show', $quote->public_id, false),
        ];
    }

    public function details(ServiceCommercialQuote $quote): array
    {
        $acceptance = $quote->acceptance;
        $reason = $acceptance ? null : $this->unavailableReason($quote);

        return [...$this->summary($quote),
            'snapshot' => $acceptance?->snapshot ?? $quote->snapshot,
            'snapshot_hash' => self::fingerprint($quote),
            'can_accept' => $acceptance === null && $reason === null, 'unavailable_reason' => $reason,
            'accept_url' => route('business.quotes.accept', $quote->public_id, false),
            'checkout_url' => config('business_commercial.checkout_enabled')
                ? route('business.quotes.checkout.show', $quote->public_id, false) : null,
            'acceptance' => $acceptance ? [
                'accepted_at' => $acceptance->accepted_at->toIso8601String(), 'accepted_name' => $acceptance->accepted_name,
                'snapshot_hash' => $acceptance->snapshot_hash,
            ] : null,
        ];
    }

    public function accept(User $user, string $publicId, string $hash, string $key): ServiceQuoteAcceptance
    {
        $quote = $this->quote($user, $publicId);
        $version = ServiceCatalogVersion::findOrFail($quote->service_catalog_version_id);

        return DB::transaction(function () use ($user, $quote, $version, $hash, $key): ServiceQuoteAcceptance {
            // Same catalogue/company order as issuance and retirement; identity is rechecked under locks.
            ServiceCatalog::whereKey($version->service_catalog_id)->lockForUpdate()->firstOrFail();
            Company::whereKey($quote->company_id)->lockForUpdate()->firstOrFail();
            Subscriber::whereKey($quote->subscriber_id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('subscriber_user')->where('subscriber_id', $quote->subscriber_id)->where('user_id', $user->id)->lockForUpdate()->get();
            $quote = ServiceCommercialQuote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $this->authorize($user, $quote);
            abort_unless(hash_equals(self::fingerprint($quote), $hash), 409, 'Las condiciones no coinciden. Recarga la cotización antes de aceptar.');

            $keyOwner = ServiceQuoteAcceptance::where('company_id', $quote->company_id)->where('idempotency_key', $key)->first();
            abort_if($keyOwner && (int) $keyOwner->service_commercial_quote_id !== (int) $quote->id, 409,
                'Esta solicitud ya corresponde a otra cotización.');
            $existing = ServiceQuoteAcceptance::where('service_commercial_quote_id', $quote->id)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->snapshot_hash, $hash), 409, 'Las condiciones aceptadas no coinciden.');

                return $existing;
            }

            Service::whereIn('id', array_column($quote->snapshot['products'], 'service_id'))->orderBy('id')->lockForUpdate()->get();
            $reason = $this->unavailableReason($quote);
            abort_if($reason !== null, 409, $reason ?? 'Cotización no disponible.');
            $acceptance = ServiceQuoteAcceptance::create([
                'service_commercial_quote_id' => $quote->id, 'company_id' => $quote->company_id,
                'subscriber_id' => $quote->subscriber_id, 'accepted_by' => $user->id, 'accepted_name' => $user->name,
                'idempotency_key' => $key, 'snapshot_hash' => $hash, 'snapshot' => $quote->snapshot,
                'accepted_at' => CarbonImmutable::now('UTC'),
            ]);
            AuditLog::create([
                'event' => 'business.quote.accepted', 'model_type' => ServiceQuoteAcceptance::class,
                'model_id' => $acceptance->id, 'user_id' => $user->id,
                'data' => ['quote_public_id' => $quote->public_id, 'company_id' => $quote->company_id, 'snapshot_hash' => $hash],
            ]);

            return $acceptance;
        }, 3);
    }

    public function unavailableReason(ServiceCommercialQuote $quote): ?string
    {
        $now = CarbonImmutable::now('UTC');
        if ($quote->issued_at->greaterThan($now) || $quote->valid_until->lessThanOrEqualTo($now)) {
            return 'Esta cotización está fuera de vigencia. Solicita una nueva.';
        }
        if (ServiceCatalogVersion::whereKey($quote->service_catalog_version_id)->value('status') !== 'published') {
            return 'La oferta fue retirada. Solicita una nueva cotización.';
        }
        $snapshot = $quote->snapshot;
        $company = Company::findOrFail($quote->company_id);
        $subscriber = Subscriber::findOrFail($quote->subscriber_id);
        if ($company->currency !== $snapshot['currency'] || $subscriber->currency !== $snapshot['currency']) {
            return 'La moneda de la empresa cambió. Solicita una revisión de la cotización.';
        }
        if (($snapshot['commercial_status'] ?? null) !== 'quoted' || empty($snapshot['terms']) || empty($snapshot['taxes'])) {
            return 'Las condiciones comerciales están incompletas. Solicita una nueva cotización.';
        }
        $services = Service::whereIn('id', array_column($snapshot['products'], 'service_id'))->get()->keyBy('id');
        foreach ($snapshot['products'] as $code => $product) {
            $service = $services->get($product['service_id']);
            if (! $service || ! $service->active || $service->service_key !== $code) {
                return 'Una solución dejó de estar disponible. Solicita una nueva cotización.';
            }
        }
        if (isset($snapshot['transition'])) {
            return app(BusinessTransitionService::class)->reason($quote);
        }
        if (SubscriptionItem::whereHas('subscription', fn ($query) => $query->where('subscriber_id', $quote->subscriber_id)
            ->whereIn('status', ['active', 'trialing']))->activeOrTrialing()->exists()) {
            return 'La empresa tiene servicios activos. Se requiere revisar la transición antes de aceptar.';
        }

        return null;
    }

    public static function fingerprint(ServiceCommercialQuote $quote): string
    {
        return hash('sha256', json_encode(self::canonical([
            'public_id' => $quote->public_id, 'company_id' => (int) $quote->company_id,
            'subscriber_id' => (int) $quote->subscriber_id, 'catalog_version_id' => (int) $quote->service_catalog_version_id,
            'issued_at' => $quote->issued_at->toIso8601String(), 'valid_until' => $quote->valid_until->toIso8601String(),
            'snapshot' => $quote->snapshot,
        ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = self::canonical($item);
            }
        }

        return $value;
    }
}
