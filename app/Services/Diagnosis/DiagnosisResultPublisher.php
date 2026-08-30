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

    public const MODALITY_LABELS = [
        'guided' => 'LAUDA 360 Guiado',
        'assisted' => 'LAUDA 360 Asistido',
        'managed' => 'LAUDA 360 Gestionado',
    ];

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

            $modality = isset($data['final_modality'])
                && $data['final_modality'] !== ''
                ? (string) $data['final_modality']
                : null;

            $label = $modality !== null
                ? self::labelForModality($modality)
                : null;

            if ($modality !== null && $label === null) {
                throw ValidationException::withMessages([
                    'final_modality' => [
                        'La modalidad seleccionada no es válida.',
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
                'final_modality' => $modality,
                'final_modality_label' => $label,
            ])->save();

            AuditService::log('diagnosis_result_review_saved', $locked, [
                'assessment_id' => $locked->id,
                'reviewed_by_user_id' => $reviewer->id,
                'automatic_modality' => $locked->recommended_modality,
                'draft_modality' => $locked->final_modality,
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

            $modality = (string) $data['final_modality'];
            $label = self::labelForModality($modality);

            if ($label === null) {
                throw ValidationException::withMessages([
                    'final_modality' => ['La modalidad seleccionada no es válida.'],
                ]);
            }

            $locked->forceFill([
                'status' => 'reviewed',
                'reviewed_by_user_id' => $reviewer->id,
                'review_summary' => trim((string) $data['review_summary']),
                'review_priorities' => array_values($data['review_priorities']),
                'final_modality' => $modality,
                'final_modality_label' => $label,
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
                'automatic_modality' => $locked->recommended_modality,
                'final_modality' => $locked->final_modality,
                'review_required' => (bool) $locked->review_required,
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

    public static function labelForModality(string $modality): ?string
    {
        return self::MODALITY_LABELS[$modality] ?? null;
    }
}
