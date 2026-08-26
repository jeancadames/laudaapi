<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAccessRequest;
use App\Services\Entitlements\SubscriberEntitlements;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppGatewayController extends Controller
{
    public function __invoke(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        SubscriberEntitlements $entitlements,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user, 403);

        if (($user->role ?? null) === 'admin') {
            return redirect()->route('dashboard');
        }

        if (($user->role ?? null) === 'subscriber') {
            $subscriberId = (int) ($subscriberResolver->resolve($user) ?? 0);

            if ($subscriberId > 0) {
                $company = $companyResolver->resolve($user, $subscriberId);

                if (
                    $company
                    && $entitlements->erpServicesForSubscriber($subscriberId)->isNotEmpty()
                ) {
                    return redirect()->route('erp.dashboard');
                }
            }

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
        }

        return redirect()->route('home');
    }
}
