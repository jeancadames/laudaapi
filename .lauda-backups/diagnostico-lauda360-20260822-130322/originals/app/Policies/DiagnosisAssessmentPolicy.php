<?php

namespace App\Policies;

use App\Models\DiagnosisAssessment;
use App\Models\User;

class DiagnosisAssessmentPolicy
{
    public function view(User $user, DiagnosisAssessment $assessment): bool
    {
        return (int) $assessment->user_id === (int) $user->id;
    }

    public function update(User $user, DiagnosisAssessment $assessment): bool
    {
        return $this->view($user, $assessment) && $assessment->isEditable();
    }

    public function submit(User $user, DiagnosisAssessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }
}
