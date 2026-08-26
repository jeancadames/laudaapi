<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\TransformationImplementationPlan;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosisDetailedRoadmapController extends Controller
{
    public function show(
        DiagnosisAssessment $assessment,
        DiagnosisDetailedRoadmapCommercialService $commercialService
    ): Response {
        Gate::authorize('view', $assessment);

        abort_unless(
            $commercialService->hasPaidAccess($assessment),
            403,
            'El Roadmap Detallado requiere pago confirmado.'
        );

        $roadmap = DiagnosisDetailedRoadmap::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where(
                'status',
                DiagnosisDetailedRoadmap::STATUS_PUBLISHED
            )
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $implementationPlan = TransformationImplementationPlan::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->whereIn('status', [
                TransformationImplementationPlan::STATUS_PRESENTED,
                TransformationImplementationPlan::STATUS_ACCEPTED,
                TransformationImplementationPlan::STATUS_ACTIVE,
                TransformationImplementationPlan::STATUS_COMPLETED,
            ])
            ->whereNotNull('presented_at')
            ->orderByDesc('version')
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
                'implementation_plan_url' => $implementationPlan
                    ? route(
                        'diagnosis.implementation_plan.show',
                        $assessment
                    )
                    : null,
                'diagnosis_url' => route(
                    'diagnosis.show',
                    $assessment
                ),
            ]
        );
    }
}
