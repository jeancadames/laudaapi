<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Services\Diagnosis\TransformationCapabilityDecisionService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class BrandingIdentityDecisionController extends Controller
{
    public function decline(
        Request $request,
        DiagnosisAssessment $assessment,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationCapabilityDecisionService $decisions
    ): RedirectResponse {
        Gate::authorize('view', $assessment);

        $user = $request->user();

        abort_unless($user, 403);
        abort_if($user->isAdmin(), 403);

        $subscriberId = (int) (
            $subscriberResolver->resolve($user)
            ?? 0
        );

        abort_unless($subscriberId > 0, 403);

        $tenantAccess = $tenantAccessService->resolve(
            $user,
            $subscriberId
        );

        abort_unless(
            ($tenantAccess['mode'] ?? null)
                === TenantAccessService::SUBSCRIBER_ADMIN
            && (bool) ($tenantAccess['tenant_admin'] ?? false),
            403,
            'Solo un owner o administrador del tenant puede decidir sobre esta capacidad.'
        );

        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless($company, 404);

        $roadmap = DiagnosisDetailedRoadmap::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where(
                'status',
                DiagnosisDetailedRoadmap::STATUS_PUBLISHED
            )
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $decisions->declineFromRoadmap(
            $company,
            $assessment,
            $roadmap,
            'branding_identity',
            $user
        );

        return back()->with(
            'success',
            'Registramos “Ahora no”. Branding seguirá disponible para activarlo más adelante si lo deseas.'
        );
    }
}
