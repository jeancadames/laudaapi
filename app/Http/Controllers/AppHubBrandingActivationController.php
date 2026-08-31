<?php

namespace App\Http\Controllers;

use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Services\Diagnosis\TransformationCapabilityActivationService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AppHubBrandingActivationController extends Controller
{
    public function store(
        Request $request,
        SubscriberResolver $subscriberResolver,
        CompanyContextResolver $companyResolver,
        TenantAccessService $tenantAccessService,
        TransformationCapabilityActivationService $activations
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user, 403);
        abort_unless(($user->role ?? null) === 'subscriber', 403);

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
            'Solo un owner o administrador del tenant puede activar esta capacidad.'
        );

        $company = $companyResolver->resolve(
            $user,
            $subscriberId
        );

        abort_unless($company, 404);

        $assessmentIds = DiagnosisAccessRequest::query()
            ->where('meta->company_id', $company->id)
            ->whereNotNull('diagnosis_assessment_id')
            ->pluck('diagnosis_assessment_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $assessment = DiagnosisAssessment::query()
            ->where('is_active', true)
            ->where(function ($query) use ($company, $assessmentIds): void {
                $query->where('organization_id', $company->id);

                if ($assessmentIds->isNotEmpty()) {
                    $query->orWhereIn('id', $assessmentIds->all());
                }
            })
            ->latest('id')
            ->first();

        $roadmap = $assessment
            ? DiagnosisDetailedRoadmap::query()
                ->where('diagnosis_assessment_id', $assessment->id)
                ->where(
                    'status',
                    DiagnosisDetailedRoadmap::STATUS_PUBLISHED
                )
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->first()
            : null;

        if ($assessment && $roadmap) {
            $activations->activateFromRoadmap(
                $company,
                $assessment,
                $roadmap,
                'branding_identity',
                $user
            );
        } else {
            $activations->activateManually(
                $company,
                'branding_identity',
                $user
            );
        }

        return back()->with(
            'success',
            'Evaluación de Branding e Identidad Digital iniciada.'
        );
    }
}
