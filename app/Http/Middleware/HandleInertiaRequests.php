<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\DgiiCompanySetting;
use App\Models\Service;
use App\Services\Dgii\DgiiCertificateRequirements;
use App\Services\Dgii\DgiiTokenManager;
use App\Services\Entitlements\SubscriberEntitlements;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $isErp = str_starts_with($request->path(), 'erp');

        $resolvedSubscriberId = $user ? $this->resolveSubscriberId($user) : null;

        // ✅ Instancia única (evita duplicidad nav + token)
        $entitlements = ($user && $isErp && $resolvedSubscriberId)
            ? app(SubscriberEntitlements::class)
            : null;

        $shared = parent::share($request);

        $user = $request->user();
        $company = null;

        if ($user?->company_id) {
            $company = \App\Models\Company::find($user->company_id);
        }

        $currency = $company?->currency ?? 'DOP';

        $dateFormat = match ($currency) {
            'USD', 'EUR' => 'MM/dd/yyyy',
            default => 'dd/MM/yyyy',
        };

        $locale = match ($currency) {
            'USD' => 'en-US',
            'EUR' => 'en-GB', // o el que tú quieras
            default => 'es-DO',
        };

        return array_merge(parent::share($request), [
            'name' => config('app.name'),

            'quote' => [
                'message' => trim($message),
                'author'  => trim($author),
            ],

            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
            ],

            'auth' => [
                'user' => $user ? [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'role'          => $user->role,
                    'subscriber_id' => $resolvedSubscriberId,
                    'must_change_password' => (bool) $request->user()->must_change_password,
                    'password_changed_at' => $request->user()->password_changed_at,
                ] : null,
            ],

            'menu' => ($user && $user->role === 'subscriber')
                ? fn() => $this->subscriberMenu($user)
                : null,

            // ✅ Pasamos $entitlements para evitar re-instancia
            'nav' => fn() => $this->navPayload($request, $user, $resolvedSubscriberId, $entitlements),

            // ✅ Pasamos $entitlements para evitar re-instancia + queries duplicadas
            'dgiiToken' => fn() => $this->dgiiTokenPayload($request, $user, $resolvedSubscriberId, $entitlements),

            // ✅ NUEVO: requerido para el switch en el layout (badge header)
            'cert_requirements' => fn() => $this->certRequirementsPayload($request, $user, $resolvedSubscriberId),

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',

            ...$shared,
            'appSettings' => [
                'currency' => $currency,
                'dateFormat' => $dateFormat,
                'locale' => $locale,
                'timezone' => $company?->timezone ?? 'America/Santo_Domingo',
            ],
        ]);
    }

    private function navPayload(Request $request, $user, ?int $resolvedSubscriberId, ?SubscriberEntitlements $entitlements = null): array
    {
        if (!$user) return ['erp' => []];

        if (!str_starts_with($request->path(), 'erp')) {
            return ['erp' => []];
        }

        $subscriberId = (int) ($resolvedSubscriberId ?? 0);
        if ($subscriberId <= 0) return ['erp' => []];

        $entitlements ??= app(SubscriberEntitlements::class);

        return [
            'erp' => $entitlements->erpSidebarTree($subscriberId),
        ];
    }

    /**
     * ✅ Payload global: requisitos de certificados (solo /erp)
     * Esto evita que el layout dependa del controller de la vista actual.
     */
    private function certRequirementsPayload(Request $request, $user, ?int $resolvedSubscriberId): ?array
    {
        if (!$user) return null;

        if (!str_starts_with($request->path(), 'erp')) {
            return null;
        }

        $subscriberId = (int) ($resolvedSubscriberId ?? 0);
        if ($subscriberId <= 0) return null;

        $company = $this->resolveCompanyForUser($user, $subscriberId);
        if (!$company) return null;

        /** @var DgiiCertificateRequirements $req */
        $req = app(DgiiCertificateRequirements::class);

        return $req->checkForCompany((int) $company->id);
    }

    /**
     * ✅ Payload para badge del token DGII (solo /erp)
     */
    private function dgiiTokenPayload(Request $request, $user, ?int $resolvedSubscriberId, ?SubscriberEntitlements $entitlements = null): ?array
    {
        if (!$user) return null;

        if (!str_starts_with($request->path(), 'erp')) {
            return null;
        }

        $subscriberId = (int) ($resolvedSubscriberId ?? 0);
        if ($subscriberId <= 0) return null;

        $entitlements ??= app(SubscriberEntitlements::class);

        $details = $entitlements->dgiiCapabilitiesDetails($subscriberId);

        if (!$details['enabled']) {
            return ['enabled' => false];
        }

        // ✅ bloquear el switch si está pending (PAGO)
        $canToggleAuto = ($details['enabled_by_item_status'] ?? null) !== 'pending';

        $base = [
            'enabled' => true,
            'enabled_by' => $details['enabled_by'],
            'enabled_services' => $details['enabled_services'],
            'enabled_by_item_status' => $details['enabled_by_item_status'],
            'can_toggle_auto' => $canToggleAuto,

            // ✅ si no hay setting, auto=false
            'auto' => false,

            'status' => 'expired',
            'secondsLeft' => 0,
            'expiresAt' => null,
            'lastError' => null,
            'lastRequestedAt' => null,
        ];

        $company = $this->resolveCompanyForUser($user, $subscriberId);
        if (!$company) return $base;

        $setting = DgiiCompanySetting::query()
            ->where('company_id', $company->id)
            ->first();

        if (!$setting) return $base;

        /** @var DgiiTokenManager $tm */
        $tm = app(DgiiTokenManager::class);

        $status = $tm->getTokenStatus($setting);

        // ✅ Fuente de verdad del modo auto
        $status['auto'] = (bool) $setting->dgii_token_auto;

        return array_merge(
            [
                'enabled' => true,
                'enabled_by' => $details['enabled_by'],
                'enabled_services' => $details['enabled_services'],
                'enabled_by_item_status' => $details['enabled_by_item_status'],
                'can_toggle_auto' => $canToggleAuto,
            ],
            $status
        );
    }

    /**
     * ✅ Resolver company de forma estable y reutilizable:
     * - si user->company_id existe => esa
     * - si no => la más reciente del subscriber
     */
    private function resolveCompanyForUser($user, int $subscriberId): ?Company
    {
        return Company::query()
            ->select(['id'])
            ->when(
                !empty($user->company_id),
                fn($q) => $q->where('id', (int) $user->company_id),
                fn($q) => $q->where('subscriber_id', $subscriberId)->orderByDesc('id')
            )
            ->first();
    }

    private function resolveSubscriberId($user): ?int
    {
        $sid = (int) ($user->subscriber_id ?? 0);
        if ($sid > 0) return $sid;

        $sid = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->orderByDesc('id')
            ->value('subscriber_id');

        if ($sid > 0) return $sid;

        if (!empty($user->company_id)) {
            $sid = (int) Company::query()
                ->where('id', (int) $user->company_id)
                ->value('subscriber_id');

            if ($sid > 0) return $sid;
        }

        $sid = (int) Company::query()
            ->where('owner_user_id', $user->id)
            ->value('subscriber_id');

        return $sid > 0 ? $sid : null;
    }

    private function subscriberMenu($user): array
    {
        $main = Service::query()
            ->whereNull('parent_id')
            ->where('active', true)
            ->whereJsonContains('roles', 'subscriber')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($service) {
                return [
                    'title'    => $service->title,
                    'href'     => $service->href,
                    'icon'     => $service->icon,
                    'badge'    => $service->badge,
                    'children' => $service->children()
                        ->where('active', true)
                        ->whereJsonContains('roles', 'subscriber')
                        ->orderBy('sort_order')
                        ->get(['title', 'href', 'icon', 'badge'])
                        ->values(),
                ];
            })
            ->values();

        return [
            'main'   => $main,
            'footer' => [
                [
                    'title' => 'Mi Suscripción',
                    'href'  => '/subscriber/subscription',
                    'icon'  => 'BadgeCheck',
                ],
            ],
        ];
    }
}
