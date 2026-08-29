<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAccessRequest;
use App\Services\Ecosystem\EcosystemHubService;
use App\Services\Ecosystem\TransformationControlPanelService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

                    return Inertia::render('App/Hub', [
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
