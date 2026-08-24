<?php

namespace App\Http\Controllers\Diagnosis;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;
use App\Services\Diagnosis\DiagnosisExpandedReportCommercialService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosisExpandedReportController extends Controller
{
    public function show(DiagnosisAssessment $assessment, DiagnosisExpandedReportCommercialService $commercialService): Response
    {
        Gate::authorize('view', $assessment);

        abort_unless(
            $commercialService->hasPaidAccess($assessment),
            403,
            'El Informe Ampliado requiere confirmación de pago.'
        );

        $report = DiagnosisExpandedReport::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('status', DiagnosisExpandedReport::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->firstOrFail();

        $roadmapCommercialService = app(

            \App\Services\Diagnosis\DiagnosisDetailedRoadmapCommercialService::class

        );


        $expandedReportOrder = $assessment->expandedReportOrder()

            ->with('invoice:id,status')

            ->first();


        $detailedRoadmapCommercial = $roadmapCommercialService

            ->state($assessment);


        $detailedRoadmapCommercialPreview = $detailedRoadmapCommercial

            ? null

            : $roadmapCommercialService->commercialSnapshot(

                $expandedReportOrder,

                now()

            );


        $roadmapPaidAccess = $roadmapCommercialService

            ->hasPaidAccess($assessment);


        $detailedRoadmap = $roadmapPaidAccess

            ? $assessment->detailedRoadmaps()

                ->where(

                    'status',

                    \App\Models\DiagnosisDetailedRoadmap::STATUS_PUBLISHED

                )

                ->whereNotNull('published_at')

                ->orderByDesc('version')

                ->first()

            : null;


        return Inertia::render('Diagnosis/ExpandedReport', [
            'detailed_roadmap_commercial' => $detailedRoadmapCommercial,
                'detailed_roadmap_commercial_preview' => $detailedRoadmapCommercialPreview,
                'detailed_roadmap_request_url' => route(
                    'diagnosis.detailed_roadmap.request',
                    $assessment
                ),
                'detailed_roadmap' => $detailedRoadmap
                ? [
                    'id' => $detailedRoadmap->id,
                    'version' => $detailedRoadmap->version,
                    'published_at' => $detailedRoadmap->published_at?->toISOString(),
                ]
                : null,
            'assessment' => [
                'id' => $assessment->id,
                'organization_name' => $assessment->organization_name,
                'maturity_score' => $assessment->maturity_score,
                'capacity_score' => $assessment->capacity_score,
                'urgency_score' => $assessment->urgency_score,
            ],
            'report' => [
                'id' => $report->id,
                'version' => $report->version,
                'sections' => $report->sections ?? [],
                'published_at' => $report->published_at?->toISOString(),
            ],
            'diagnosis_url' => route('diagnosis.show', $assessment),
        ]);
    }
}
