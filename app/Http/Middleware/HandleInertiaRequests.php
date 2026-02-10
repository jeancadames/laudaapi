<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Service;
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

        // ✅ Resolver subscriber_id “real” (sirve para ERP y para mostrarlo en FE)
        $resolvedSubscriberId = $user ? $this->resolveSubscriberId($user) : null;

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

                    // ✅ aquí YA llega bien aunque users.subscriber_id sea null
                    'subscriber_id' => $resolvedSubscriberId,
                ] : null,
            ],

            /**
             * ✅ Menú “catálogo por roles” para subscriber (como lo tienes hoy).
             */
            'menu' => ($user && $user->role === 'subscriber')
                ? fn() => $this->subscriberMenu($user)
                : null,

            /**
             * ✅ Navegación ERP por entitlements.
             * Solo se calcula cuando la URL empieza con /erp para reducir carga.
             */
            'nav' => fn() => $this->navPayload($request, $user, $resolvedSubscriberId),

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',
        ]);
    }

    private function navPayload(Request $request, $user, ?int $resolvedSubscriberId): array
    {
        if (!$user) return ['erp' => []];

        // Solo en /erp calculamos ERP nav (performance)
        if (!str_starts_with($request->path(), 'erp')) {
            return ['erp' => []];
        }

        $subscriberId = (int) ($resolvedSubscriberId ?? 0);
        if ($subscriberId <= 0) return ['erp' => []];

        /** @var SubscriberEntitlements $entitlements */
        $entitlements = app(SubscriberEntitlements::class);

        // ✅ OJO: erpSidebarTree retorna ['groups' => ...]
        return [
            'erp' => $entitlements->erpSidebarTree($subscriberId),
        ];
    }

    /**
     * ✅ Resolver subscriber_id “real” aunque users.subscriber_id sea null.
     * Orden:
     * 1) users.subscriber_id
     * 2) pivot subscriber_user (active=1)
     * 3) company->subscriber_id (por company_id / owner_user_id)
     */
    private function resolveSubscriberId($user): ?int
    {
        // 1) Campo directo en users
        $sid = (int) ($user->subscriber_id ?? 0);
        if ($sid > 0) return $sid;

        // 2) Pivot subscriber_user
        $sid = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->orderByDesc('id')
            ->value('subscriber_id');

        if ($sid > 0) return $sid;

        // 3) Company (igual a tu lógica de My Services)
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

    /**
     * Construye el menú dinámico “catálogo” para subscribers basado en services.roles
     * (NO es entitlements; es catálogo visible).
     */
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
