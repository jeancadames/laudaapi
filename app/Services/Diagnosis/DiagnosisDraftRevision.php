<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use Illuminate\Validation\ValidationException;

/** Optimistic draft version; checked on the fresh row while holding its lock. */
class DiagnosisDraftRevision
{
    public function forAssessment(DiagnosisAssessment $assessment): string
    {
        return hash('sha256', json_encode($assessment->only([
            'id', 'user_id', 'organization_id', 'is_active', 'status',
            'current_step', 'answers', 'notes', 'updated_at',
            'business_profile_completed_at',
            ...DiagnosisBusinessProfileService::FIELDS,
        ]), JSON_THROW_ON_ERROR));
    }

    public function assertCurrent(DiagnosisAssessment $assessment, string $expected): void
    {
        if (! hash_equals($this->forAssessment($assessment), $expected)) {
            throw ValidationException::withMessages([
                'expected_revision' => 'Hay una versión más reciente del diagnóstico. Tus respuestas se conservan en esta pantalla. Revísalas antes de cargar la versión guardada.',
            ]);
        }
    }
}
