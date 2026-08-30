<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Services\Diagnosis\DiagnosisDeliverableValidationService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosisExpandedReportController extends Controller
{
    public function show(
        DiagnosisAssessment $assessment,
        DiagnosisDeliverableValidationService $validations
    ): Response {
        Gate::authorize('view', $assessment);

        $report = DiagnosisExpandedReport::query()
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->where(
                'status',
                DiagnosisExpandedReport::STATUS_PUBLISHED
            )
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $detailedRoadmap = DiagnosisDetailedRoadmap::query()
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
            ->first();

        return Inertia::render(
            'Diagnosis/ExpandedReport',
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
                'report' => [
                    'id' => $report->id,
                    'version' => $report->version,
                    'sections' => $report->sections ?? [],
                    'published_at' =>
                        $report->published_at?->toISOString(),
                ],
                'detailed_roadmap' => $detailedRoadmap
                    ? [
                        'id' => $detailedRoadmap->id,
                        'version' => $detailedRoadmap->version,
                        'published_at' =>
                            $detailedRoadmap->published_at
                                ?->toISOString(),
                    ]
                    : null,
                'validation' => $validations->stateFor($report),
                'validation_endpoints' => [
                    'review' => route(
                        'diagnosis.deliverable.review',
                        [$assessment, 'expanded-report']
                    ),
                    'validate' => route(
                        'diagnosis.deliverable.validate',
                        [$assessment, 'expanded-report']
                    ),
                    'request_adjustment' => route(
                        'diagnosis.deliverable.adjustment',
                        [$assessment, 'expanded-report']
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
