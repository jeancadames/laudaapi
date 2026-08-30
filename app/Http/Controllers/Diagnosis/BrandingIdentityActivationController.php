<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Services\Diagnosis\TransformationCapabilityActivationService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BrandingIdentityActivationController extends Controller
{
    public function store(
        Request $request,
        DiagnosisAssessment $assessment,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationCapabilityActivationService $activations
    ): RedirectResponse {
        Gate::authorize('view', $assessment);

        $user = $request->user();

        abort_unless($user, 403);
        abort_if(
            $user->isAdmin(),
            403,
            'La activación gratuita corresponde al tenant.'
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
            403,
            'Solo un owner o administrador del tenant puede activar esta capacidad.'
        );

        $companyId =
            (int) ($assessment->organization_id ?? 0);

        abort_unless($companyId > 0, 422);

        $company = $companyResolver->resolve(
            $user,
            $subscriberId,
            $companyId
        );

        abort_unless(
            $company
            && (int) $company->id === $companyId,
            403,
            'El diagnóstico no pertenece a la empresa activa del tenant.'
        );

        $roadmap = DiagnosisDetailedRoadmap::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->where(
                'status',
                DiagnosisDetailedRoadmap::STATUS_PUBLISHED
            )
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $activations->activateFromRoadmap(
            $company,
            $assessment,
            $roadmap,
            'branding_identity',
            $user
        );

        return back()->with(
            'success',
            'Branding e Identidad Digital activado gratuitamente. Ya forma parte de su recorrido de Transformación 360.'
        );
    }
}
