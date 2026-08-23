<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisResultPublisher
{
    public const MODALITY_LABELS = [
        'guided' => 'LAUDA 360 Guiado',
        'assisted' => 'LAUDA 360 Asistido',
        'managed' => 'LAUDA 360 Gestionado',
    ];

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

            AuditService::log('diagnosis_result_published', $locked, [
                'assessment_id' => $locked->id,
                'reviewed_by_user_id' => $reviewer->id,
                'automatic_modality' => $locked->recommended_modality,
                'final_modality' => $locked->final_modality,
                'review_required' => (bool) $locked->review_required,
            ]);

            return $locked->fresh(['user', 'reviewedBy']);
        });
    }

    public static function labelForModality(string $modality): ?string
    {
        return self::MODALITY_LABELS[$modality] ?? null;
    }
}
