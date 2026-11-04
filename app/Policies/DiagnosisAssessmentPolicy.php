<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\User;
use App\Services\Diagnosis\InitialDiagnosisCommercialService;

class DiagnosisAssessmentPolicy
{
    public function view(User $user, DiagnosisAssessment $assessment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! (bool) $assessment->is_active) {
            return false;
        }

        $assigned = (int) $user->id > 0
            && (int) $assessment->user_id === (int) $user->id;

        // Historical standalone invitations/assignments have no Company context.
        // They remain individual grants; tenant membership cannot represent them.
        if ($assessment->organization_id === null) {
            return $assigned;
        }

        $companyId = (int) ($assessment->organization_id ?? 0);

        if ($companyId <= 0) {
            return false;
        }

        $company = Company::query()
            ->whereKey($companyId)
            ->where('active', true)
            ->first();

        if (! $company) {
            return false;
        }

        if ($user->activeSubscribers()
            ->whereKey($company->subscriber_id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists()) {
            return true;
        }

        if (! $assigned) {
            return false;
        }

        // A real historical invitation can remain an independent individual grant
        // after a Company was linked. Native App Hub assignment is never a bypass.
        return DiagnosisAccessRequest::query()
            ->where('diagnosis_assessment_id', $assessment->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [
                DiagnosisAccessRequest::STATUS_APPROVED,
                DiagnosisAccessRequest::STATUS_INVITED,
                DiagnosisAccessRequest::STATUS_ACTIVE,
            ])
            ->where(function ($query): void {
                $query->whereNotNull('invitation_sent_at')
                    ->orWhereNotNull('invitation_accepted_at');
            })
            ->get(['meta'])
            ->contains(fn ($workflow): bool => data_get($workflow->meta, 'source') !== InitialDiagnosisCommercialService::SOURCE);
    }

    public function update(User $user, DiagnosisAssessment $assessment): bool
    {
        return ! $user->isAdmin()
            && $this->view($user, $assessment)
            && $assessment->isEditable();
    }

    public function submit(User $user, DiagnosisAssessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }
}
