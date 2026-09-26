<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiagnosisAssessmentDeletionService
{
    /**
     * Dependencies that make a diagnosis historically significant.
     *
     * diagnosis_access_requests is intentionally excluded because its FK
     * uses SET NULL and the request itself must remain as commercial/audit
     * history after a safe draft assessment is deleted.
     */
    private const DEPENDENCIES = [
        'diagnosis_deliverable_validations',
        'diagnosis_detailed_roadmap_orders',
        'diagnosis_detailed_roadmaps',
        'diagnosis_expanded_report_orders',
        'diagnosis_expanded_reports',
        'transformation_capability_activations',
        'transformation_capability_decisions',
        'transformation_implementation_definitions',
        'transformation_implementation_plans',
        'transformation_implementation_requests',
    ];

    public function blockers(DiagnosisAssessment $assessment): array
    {
        $blockers = [];

        if ($assessment->published_at !== null) {
            $blockers[] = 'published';
        }

        if ($assessment->submitted_at !== null) {
            $blockers[] = 'submitted';
        }

        if ($assessment->reviewed_at !== null) {
            $blockers[] = 'reviewed';
        }

        if ($assessment->superseded_by_assessment_id !== null) {
            $blockers[] = 'superseded';
        }

        if (
            DiagnosisAssessment::query()
                ->where(
                    'superseded_by_assessment_id',
                    $assessment->id
                )
                ->exists()
        ) {
            $blockers[] = 'supersedes_another_assessment';
        }

        foreach (self::DEPENDENCIES as $table) {
            if (
                DB::table($table)
                    ->where(
                        'diagnosis_assessment_id',
                        $assessment->id
                    )
                    ->exists()
            ) {
                $blockers[] = $table;
            }
        }

        return $blockers;
    }

    public function assertCanDelete(
        DiagnosisAssessment $assessment
    ): void {
        $blockers = $this->blockers($assessment);

        if ($blockers === []) {
            return;
        }

        throw ValidationException::withMessages([
            'assessment' => [
                'El diagnóstico no puede eliminarse porque tiene historial o dependencias: '
                . implode(', ', $blockers)
                . '.',
            ],
        ]);
    }

    public function delete(DiagnosisAssessment $assessment): void
    {
        DB::transaction(function () use ($assessment): void {
            $locked = DiagnosisAssessment::query()
                ->lockForUpdate()
                ->findOrFail($assessment->id);

            $this->assertCanDelete($locked);

            $locked->delete();
        });
    }
}
