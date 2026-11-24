<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAccessRequest;
use App\Services\Billing\BusinessPackagePurchases;
use App\Services\BusinessIdentity\CentralMembership;
use App\Services\Ecosystem\EcosystemHubService;
use App\Services\Ecosystem\TransformationControlPanelService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AppGatewayController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        EcosystemHubService $hubService,
        TenantAccessService $tenantAccessService,
        TransformationControlPanelService $transformationControlPanelService
    ) {
        $user = $request->user();

        abort_unless($user, 403);

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

        if (($user->role ?? null) === 'subscriber') {
            /*
             * S10-F4.8-A3 · inactive_membership_blocked
             *
             * Un usuario que ya pertenece a un tenant pero cuya membresía
             * fue desactivada NO es un usuario nuevo y nunca debe caer en
             * el onboarding para crear otra empresa.
             */
            $hasAnyTenantMembership = DB::table('subscriber_user')
                ->where('user_id', $user->id)
                ->exists();

            $hasActiveTenantMembership = DB::table('subscriber_user')
                ->where('user_id', $user->id)
                ->where('active', 1)
                ->exists();

            if ($hasAnyTenantMembership && ! $hasActiveTenantMembership) {
                auth()->guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with(
                        'error',
                        'Tu acceso a esta empresa está desactivado. Contacta al administrador de tu cuenta.'
                    );
            }

            $subscriberId = (int) (
                $subscriberResolver->resolve($user)
                ?? 0
            );

            if ($subscriberId > 0) {
                $company = $companyResolver->resolve(
                    $user,
                    $subscriberId
                );

                if ($company) {
                    if (
                        $request->session()->get('apphub.intent')
                        === \App\Services\Diagnosis\InitialDiagnosisCommercialService::INTENT
                    ) {
                        return redirect()->route(
                            'app.diagnosis.show'
                        );
                    }

                    $tenantAccess = $tenantAccessService->resolve(
                        $user,
                        $subscriberId
                    );

                    $groups = $hubService->groupsFor($user, $company);

                    $transformation360 = $transformationControlPanelService->forCompany($company);

                    if (! ($tenantAccess['can_browse_store'] ?? false)) {
                        $groups = collect($groups)
                            ->map(function (array $group): array {
                                $group['solutions'] = collect(
                                    $group['solutions'] ?? []
                                )
                                    ->filter(
                                        fn (array $solution): bool =>
                                            (bool) ($solution['entitled'] ?? false)
                                            && ! empty($solution['launch_url'])
                                    )
                                    ->values()
                                    ->all();

                                return $group;
                            })
                            ->filter(
                                fn (array $group): bool =>
                                    count($group['solutions'] ?? []) > 0
                            )
                            ->values()
                            ->all();
                    }

                    if (! $request->routeIs('app.control')) {
                        $entry = [];
                        if (config('business_commercial.enabled') && ($tenantAccess['can_manage_billing'] ?? false)
                            && in_array(app(CentralMembership::class)->role($user, $company), ['owner', 'admin'], true)) {
                            $purchases = app(BusinessPackagePurchases::class);
                            $pending = $purchases->pendingId($company);
                            $activated = $purchases->lastActivatedId($company);
                            $entry['business_self_service'] = [
                                'configurator_url' => route('business.package.build', absolute: false),
                                'last_purchase_url' => $activated ? route('business.package.build', ['compra' => $activated], false) : null,
                                'pending_purchase' => $pending ? [
                                    'id' => $pending, 'message' => 'Puedes retomar tu compra desde aquí.',
                                    'next_action' => ['type' => 'review', 'label' => 'Ver mi compra',
                                        'url' => route('business.package.build', ['compra' => $pending], false)],
                                ] : null,
                            ];
                        }
                        return Inertia::render('App/Home', [
                            'company' => [
                                'id' => $company->id,
                                'name' =>
                                    $company->name
                                    ?? $company->business_name
                                    ?? 'Mi empresa',
                                'subscriber_id' => $company->subscriber_id,
                            ],
                            ...$entry,
                            'groups' => $groups,
                            'tenant_access' => $tenantAccess,
                        ]);
                    }

                    if (! ($tenantAccess['can_browse_store'] ?? false)) {
                        return redirect()->route('app.gateway');
                    }

                    // «Agregar a mi paquete»: con un paquete activado se cambia ese paquete;
                    // sin paquete se arma uno nuevo.
                    $packageManageUrl = null;
                    if (config('business_commercial.enabled') && ($tenantAccess['can_manage_billing'] ?? false)
                        && Schema::hasTable('business_package_changes')
                        && in_array(app(CentralMembership::class)->role($user, $company), ['owner', 'admin'], true)) {
                        $activated = app(BusinessPackagePurchases::class)->lastActivatedId($company);
                        $packageManageUrl = $activated ? route('business.package.manage', $activated, false) : null;
                    }

                    return Inertia::render('App/Hub', [
                        'business_identity_url' => config('business_identity.enabled') ? route('business.identity.index') : null,
                        'business_package' => [
                            'configurator_url' => route('business.package.build', absolute: false),
                            'manage_url' => $packageManageUrl,
                        ],
                        'company' => [
                            'id' => $company->id,
                            'name' =>
                                $company->name
                                ?? $company->business_name
                                ?? 'Mi empresa',
                            'subscriber_id' => $company->subscriber_id,
                        ],
                        'groups' => $groups,
                        'tenant_access' => $tenantAccess,
                        'transformation360' => $transformation360,
                    ]);
                }
            }

            // Si el usuario llegó por T360, conserva ese flujo.
            $access = DiagnosisAccessRequest::query()
                ->with('assessment')
                ->where('user_id', $user->id)
                ->whereNotNull('diagnosis_assessment_id')
            ->whereHas(
                'assessment',
                fn ($query) =>
                    $query->where('is_active', true)
            )
                ->latest('id')
                ->first();

            if ($access?->assessment) {
                return redirect()->route(
                    'diagnosis.show',
                    $access->assessment
                );
            }

            // Registro directo sin empresa: onboarding central.
            return redirect()->route('app.onboarding.show');
        }

        // Usuarios legacy / invitaciones T360.
        $access = DiagnosisAccessRequest::query()
            ->with('assessment')
            ->where('user_id', $user->id)
            ->whereNotNull('diagnosis_assessment_id')
            ->whereHas(
                'assessment',
                fn ($query) =>
                    $query->where('is_active', true)
            )
            ->latest('id')
            ->first();

        if ($access?->assessment) {
            return redirect()->route(
                'diagnosis.show',
                $access->assessment
            );
        }

        return redirect()->route('home');
    }
}
