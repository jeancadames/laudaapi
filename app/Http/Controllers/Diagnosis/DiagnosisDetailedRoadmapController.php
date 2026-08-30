<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosisDetailedRoadmapController extends Controller
{
    public function show(
        DiagnosisAssessment $assessment,
        DiagnosisDeliverableValidationService $validations
    ): Response {
        Gate::authorize('view', $assessment);

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

        $implementationPlan =
            TransformationImplementationPlan::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->whereIn('status', [
                    TransformationImplementationPlan::STATUS_PRESENTED,
                    TransformationImplementationPlan::STATUS_ACCEPTED,
                    TransformationImplementationPlan::STATUS_ACTIVE,
                    TransformationImplementationPlan::STATUS_COMPLETED,
                ])
                ->whereNotNull('presented_at')
                ->orderByDesc('version')
                ->first();

        $brandingRecommended = (bool) (
            data_get(
                $roadmap->roadmap ?? [],
                'transformation_capabilities.branding_identity.recommended',
                false
            )
        );

        $brandingActivation =
            TransformationCapabilityActivation::query()
                ->where(
                    'diagnosis_assessment_id',
                    $assessment->id
                )
                ->where(
                    'capability_key',
                    'branding_identity'
                )
                ->first();

        return Inertia::render(
            'Diagnosis/DetailedRoadmap',
            [
                'assessment' => [
                    'id' => $assessment->id,
                    'organization_name' =>
                        $assessment->organization_name,
                    'maturity_score' =>
                        $assessment->maturity_score,
                    'capacity_score' =>
                        $assessment->capacity_score,
                    'urgency_score' =>
                        $assessment->urgency_score,
                ],
                'roadmap' => [
                    'id' => $roadmap->id,
                    'version' => $roadmap->version,
                    'content' =>
                        $roadmap->roadmap ?? [],
                    'published_at' =>
                        $roadmap->published_at?->toISOString(),
                ],
                'expanded_report_url' => route(
                    'diagnosis.expanded_report.show',
                    $assessment
                ),
                'implementation_plan_url' =>
                    $implementationPlan
                        ? route(
                            'diagnosis.implementation_plan.show',
                            $assessment
                        )
                        : null,
                'branding_activation' => [
                    'recommended' =>
                        $brandingRecommended,
                    'available' =>
                        $brandingRecommended
                        && $brandingActivation === null,
                    'activated' =>
                        $brandingActivation !== null,
                    'status' =>
                        $brandingActivation?->status,
                    'activated_at' =>
                        $brandingActivation
                            ?->activated_at
                            ?->toISOString(),
                    'endpoint' =>
                        $brandingRecommended
                            ? route(
                                'diagnosis.capabilities.branding_identity.activate',
                                $assessment
                            )
                            : null,
                ],
                'validation' => $validations->stateFor($roadmap),
                'validation_endpoints' => [
                    'review' => route(
                        'diagnosis.deliverable.review',
                        [$assessment, 'roadmap']
                    ),
                    'validate' => route(
                        'diagnosis.deliverable.validate',
                        [$assessment, 'roadmap']
                    ),
                    'request_adjustment' => route(
                        'diagnosis.deliverable.adjustment',
                        [$assessment, 'roadmap']
                    ),
                ],
                'diagnosis_url' => route(
                    'diagnosis.show',
                    $assessment
                ),
            ]
        );
    }
}
