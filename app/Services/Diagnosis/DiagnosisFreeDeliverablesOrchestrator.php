<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisDetailedRoadmap;
use App\Models\DiagnosisExpandedReport;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisFreeDeliverablesOrchestrator
{
    public function __construct(
        private readonly DiagnosisExpandedReportService $expandedReports,
        private readonly DiagnosisDetailedRoadmapService $roadmaps,
        private readonly TransformationImplementationPlanService $plans,
        private readonly TransformationImplementationPlanAutogenerator $autogenerator
    ) {
    }

    public function generateAndPresent(
        DiagnosisAssessment $assessment,
        User $actor
    ): array {
        return DB::transaction(function () use ($assessment, $actor): array {
            $locked = DiagnosisAssessment::query()
                ->whereKey($assessment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->status !== 'reviewed'
                || $locked->published_at === null
            ) {
                throw ValidationException::withMessages([
                    'assessment' => [
                        'Los entregables gratuitos requieren un Diagnóstico oficial publicado.',
                    ],
                ]);
            }

            $report = DiagnosisExpandedReport::query()
                ->where('diagnosis_assessment_id', $locked->id)
                ->where('status', DiagnosisExpandedReport::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->first();

            if (! $report) {
                $report = $this->expandedReports->createOrGetDraft(
                    $locked,
                    $actor
                );

                if (! $report->isPublished()) {
                    $report = $this->expandedReports->publish(
                        $report,
                        $actor
                    );
                }
            }

            $roadmap = DiagnosisDetailedRoadmap::query()
                ->where('diagnosis_assessment_id', $locked->id)
                ->where('status', DiagnosisDetailedRoadmap::STATUS_PUBLISHED)
                ->whereNotNull('published_at')
                ->orderByDesc('version')
                ->first();

            if (! $roadmap) {
                $roadmap = $this->roadmaps->createOrGetDraft(
                    $locked,
                    $actor
                );

                if (! $roadmap->isPublished()) {
                    $roadmap = $this->roadmaps->publish(
                        $roadmap,
                        $actor
                    );
                }
            }

            $plan = $this->plans->latestForAssessment($locked);

            if (
                ! $plan
                || in_array(
                    $plan->status,
                    [
                        TransformationImplementationPlan::STATUS_COMPLETED,
                        TransformationImplementationPlan::STATUS_CANCELLED,
                    ],
                    true
                )
            ) {
                $plan = $this->plans->createDraftFromPublishedRoadmap(
                    $roadmap,
                    $actor
                );
            }

            if (
                $plan->status === TransformationImplementationPlan::STATUS_DRAFT
                && ! $plan->phases()->exists()
            ) {
                $plan = $this->autogenerator->generate(
                    $plan,
                    $actor->id
                );
            }

            if ($plan->status === TransformationImplementationPlan::STATUS_DRAFT) {
                $plan = $this->plans->markPresented(
                    $plan,
                    $actor
                );
            }

            AuditService::log(
                'diagnosis_free_deliverables_generated_and_presented',
                $locked,
                [
                    'assessment_id' => $locked->id,
                    'expanded_report_id' => $report->id,
                    'expanded_report_version' => $report->version,
                    'roadmap_id' => $roadmap->id,
                    'roadmap_version' => $roadmap->version,
                    'implementation_plan_id' => $plan->id,
                    'implementation_plan_version' => $plan->version,
                    'implementation_plan_status' => $plan->status,
                    'actor_user_id' => $actor->id,
                    'purchase_required' => false,
                    'payment_required' => false,
                    'modality_required' => false,
                    'subscription_created' => false,
                ]
            );

            return [
                'expanded_report' => $report,
                'roadmap' => $roadmap,
                'implementation_plan' => $plan,
            ];
        }, 3);
    }
}
