<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAccessRequest;
use App\Services\Ecosystem\EcosystemHubService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AppGatewayController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        EcosystemHubService $hubService
    ) {
        $user = $request->user();

        abort_unless($user, 403);

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        if (($user->role ?? null) === 'subscriber') {
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
                    return Inertia::render('App/Hub', [
                        'company' => [
                            'id' => $company->id,
                            'name' =>
                                $company->name
                                ?? $company->business_name
                                ?? 'Mi empresa',
                            'subscriber_id' => $company->subscriber_id,
                        ],
                        'groups' =>
                            $hubService->groupsFor($user, $company),
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
