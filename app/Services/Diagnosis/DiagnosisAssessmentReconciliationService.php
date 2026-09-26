<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use Illuminate\Support\Facades\DB;

class DiagnosisAssessmentReconciliationService
{
    public function candidates(?int $organizationId = null)
    {
        $query = DiagnosisAssessment::query()
            ->whereNotNull('published_at')
            ->where('is_active', false)
            ->whereNotNull('superseded_by_assessment_id');

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return $query
            ->orderBy('organization_id')
            ->orderBy('id')
            ->get()
            ->filter(function (DiagnosisAssessment $official) {
                $working = DiagnosisAssessment::query()
                    ->find($official->superseded_by_assessment_id);

                if ($working === null) {
                    return false;
                }

                if (
                    (int) $working->organization_id
                    !== (int) $official->organization_id
                ) {
                    return false;
                }

                return $working->published_at === null;
            })
            ->values();
    }

    public function describe(
        DiagnosisAssessment $official
    ): array {
        $working = DiagnosisAssessment::query()
            ->find($official->superseded_by_assessment_id);

        return [
            'organization_id' => $official->organization_id,
            'organization_name' => $official->organization_name,

            'official_assessment_id' => $official->id,
            'official_status' => $official->status,
            'official_published_at' => $official->published_at,
            'official_is_active' => (bool) $official->is_active,
            'official_inactivated_at' => $official->inactivated_at,
            'official_superseded_by' =>
                $official->superseded_by_assessment_id,

            'working_assessment_id' => $working?->id,
            'working_status' => $working?->status,
            'working_published_at' => $working?->published_at,
            'working_is_active' =>
                $working === null
                    ? null
                    : (bool) $working->is_active,

            'changes' => [
                'official.is_active' => true,
                'official.inactivated_at' => null,
                'official.superseded_by_assessment_id' => null,
            ],
        ];
    }

    public function reconcile(
        DiagnosisAssessment $official
    ): array {
        return DB::transaction(function () use ($official) {
            $locked = DiagnosisAssessment::query()
                ->lockForUpdate()
                ->findOrFail($official->id);

            if ($locked->published_at === null) {
                return [
                    'changed' => false,
                    'reason' => 'official_not_published',
                ];
            }

            if ($locked->superseded_by_assessment_id === null) {
                return [
                    'changed' => false,
                    'reason' => 'no_superseding_assessment',
                ];
            }

            $working = DiagnosisAssessment::query()
                ->lockForUpdate()
                ->find($locked->superseded_by_assessment_id);

            if ($working === null) {
                return [
                    'changed' => false,
                    'reason' => 'working_assessment_missing',
                ];
            }

            if (
                (int) $working->organization_id
                !== (int) $locked->organization_id
            ) {
                return [
                    'changed' => false,
                    'reason' => 'organization_mismatch',
                ];
            }

            if ($working->published_at !== null) {
                return [
                    'changed' => false,
                    'reason' => 'replacement_already_published',
                ];
            }

            $locked->forceFill([
                'is_active' => true,
                'inactivated_at' => null,
                'superseded_by_assessment_id' => null,
            ])->save();

            return [
                'changed' => true,
                'official_assessment_id' => $locked->id,
                'working_assessment_id' => $working->id,
            ];
        });
    }
}
