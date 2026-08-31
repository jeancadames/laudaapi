<?php

namespace App\Http\Controllers;

use App\Models\TransformationCapabilityActivation;
use App\Services\Diagnosis\TransformationCapabilityActivationService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AppHubBrandingLifecycleController extends Controller
{
    public function start(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationCapabilityActivationService $activations
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user, 403);

        abort_unless(
            ($user->role ?? null) === 'subscriber',
            403
        );

        $subscriberId = (int) (
            $subscriberResolver->resolve($user)
            ?? 0
        );

        abort_unless($subscriberId > 0, 403);

        $tenantAccess =
            $tenantAccessService->resolve(
                $user,
                $subscriberId
            );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN
            && (bool) (
                $tenantAccess['tenant_admin']
                ?? false
            ),
            403
        );

        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless($company, 404);

        $activation =
            TransformationCapabilityActivation::query()
                ->where(
                    'company_id',
                    $company->id
                )
                ->where(
                    'capability_key',
                    'branding_identity'
                )
                ->where(
                    'status',
                    '!=',
                    TransformationCapabilityActivation::STATUS_CANCELLED
                )
                ->orderByDesc('activated_at')
                ->orderByDesc('id')
                ->first();

        abort_unless($activation, 404);

        $activations->start(
            $activation,
            $user
        );

        return back()->with(
            'success',
            'Evaluación de Branding e Identidad Digital en progreso.'
        );
    }
}
