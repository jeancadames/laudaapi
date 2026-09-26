<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisResultPublisher
{
    public function __construct(
        private readonly DiagnosisFreeDeliverablesOrchestrator $deliverables,
        private readonly DiagnosisDeliverableValidationService $validations,
        private readonly DiagnosisAssessmentCompletenessService $completeness
    ) {
    }

    public function saveDraft(
        DiagnosisAssessment $assessment,
        User $reviewer,
        array $data
    ): DiagnosisAssessment {
        return DB::transaction(function () use (
            $assessment,
            $reviewer,
            $data
        ): DiagnosisAssessment {
            $locked = DiagnosisAssessment::query()
                ->whereKey($assessment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'assessment' => [
                        'Solo puede guardarse como borrador un diagnóstico enviado y todavía no publicado.',
                    ],
                ]);
            }

            $locked->forceFill([
                'reviewed_by_user_id' => $reviewer->id,
                'review_summary' => isset($data['review_summary'])
                    ? trim((string) $data['review_summary'])
                    : null,
                'review_priorities' => array_values(
                    $data['review_priorities'] ?? []
                ),
            ])->save();

            AuditService::log('diagnosis_result_review_saved', $locked, [
                'assessment_id' => $locked->id,
                'reviewed_by_user_id' => $reviewer->id,
                'commercial_modality_changed' => false,
            ]);

            return $locked->fresh(['user', 'reviewedBy']);
        });
    }

    public function publish(
        DiagnosisAssessment $assessment,
        User $reviewer,
        array $data
    ): DiagnosisAssessment {
        return DB::transaction(function () use (
            $assessment,
            $reviewer,
            $data
        ): DiagnosisAssessment {
            $locked = DiagnosisAssessment::query()
                ->whereKey($assessment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->validations->assertAssessmentOpenForPublication(
                $locked
            );

            /*
             * Defensive publication gate.
             *
             * Even if an assessment reaches the administrative review
             * through an unexpected path, it cannot become official or
             * generate deliverables without its vital business profile.
             */
            $this->completeness->assertComplete($locked);

            if (!in_array($locked->status, ['submitted', 'reviewed'], true)) {
                throw ValidationException::withMessages([
                    'assessment' => [
                        'El diagnóstico debe estar enviado antes de publicar un resultado.',
                    ],
                ]);
            }

            $locked->forceFill([
                'status' => 'reviewed',
                'reviewed_by_user_id' => $reviewer->id,
                'review_summary' => trim((string) $data['review_summary']),
                'review_priorities' => array_values($data['review_priorities']),
                'reviewed_at' => now(),
                'published_at' => now(),
            ])->save();

            $deliverables = $this->deliverables->generateAndPresent(
                $locked,
                $reviewer
            );

            /*
             * Publication is the point at which this diagnosis becomes
             * the official diagnosis for the organization.
             *
             * Keep drafts/reassessments accessible while they are being
             * prepared, but archive previous published assessments only
             * after the new result and its deliverables were generated
             * successfully inside this transaction.
             */
            /*
             * Supersession is restricted to assessments explicitly linked
             * to this tenant's LAUDA 360 lifecycle. organization_id alone
             * is not a sufficient boundary for changing lifecycle state.
             */
            $tenantAssessmentIds = DiagnosisAccessRequest::query()
                ->where(
                    'meta->source',
                    InitialDiagnosisCommercialService::SOURCE
                )
                ->where(
                    'meta->company_id',
                    $locked->organization_id
                )
                ->whereNotNull('diagnosis_assessment_id')
                ->pluck('diagnosis_assessment_id')
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $superseded = DiagnosisAssessment::query()
                ->whereIn('id', $tenantAssessmentIds->all())
                ->whereKeyNot($locked->id)
                ->where('organization_id', $locked->organization_id)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->lockForUpdate()
                ->get();

            foreach ($superseded as $priorAssessment) {
                $priorAssessment->forceFill([
                    'is_active' => false,
                    'inactivated_at' => now(),
                    'superseded_by_assessment_id' => $locked->id,
                ])->save();
            }

            $locked->forceFill([
                'is_active' => true,
                'inactivated_at' => null,
                'superseded_by_assessment_id' => null,
            ])->save();

            if ($superseded->isNotEmpty()) {
                AuditService::log(
                    'diagnosis_assessment_superseded',
                    $locked,
                    [
                        'organization_id' => $locked->organization_id,
                        'new_assessment_id' => $locked->id,
                        'superseded_assessment_ids' => $superseded
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->values()
                            ->all(),
                        'superseded_at_publication' => true,
                    ],
                    ['user_id' => $reviewer->id]
                );
            }

            AuditService::log('diagnosis_result_published', $locked, [
                'assessment_id' => $locked->id,
                'reviewed_by_user_id' => $reviewer->id,
                'review_required' => (bool) $locked->review_required,
                'commercial_modality_required' => false,
                'commercial_modality_changed' => false,
                'free_deliverables_generated' => [
                    'expanded_report_id' =>
                        $deliverables['expanded_report']->id,
                    'roadmap_id' =>
                        $deliverables['roadmap']->id,
                    'implementation_plan_id' =>
                        $deliverables['implementation_plan']->id,
                ],
            ]);

            return $locked->fresh(['user', 'reviewedBy']);
        });
    }
}
