<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisResultPublisher
{
    public function __construct(
        private readonly DiagnosisFreeDeliverablesOrchestrator $deliverables
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
