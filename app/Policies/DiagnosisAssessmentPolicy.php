<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\DiagnosisAssessment;
use App\Models\User;

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

        if ((int) $assessment->user_id === (int) $user->id) {
            return true;
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

        return $user->activeSubscribers()
            ->whereKey($company->subscriber_id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
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
