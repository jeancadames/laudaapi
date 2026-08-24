<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisBusinessProfileBackendContractTest extends TestCase
{
    public function test_update_request_accepts_profile_before_answers(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Requests/Diagnosis/UpdateDiagnosisAssessmentRequest.php'
            )
        );

        $this->assertStringContainsString(
            "'answers' => ['present', 'array']",
            $source
        );

        $this->assertStringContainsString(
            'DiagnosisBusinessProfileService',
            $source
        );
    }

    public function test_submit_request_keeps_51_answer_requirement(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Requests/Diagnosis/SubmitDiagnosisAssessmentRequest.php'
            )
        );

        $this->assertStringContainsString(
            'Debe completar todas las preguntas',
            $source
        );

        $this->assertStringContainsString(
            'DiagnosisBusinessProfileService',
            $source
        );
    }

    public function test_controller_persists_profile_and_keeps_scoring(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
            )
        );

        $this->assertStringContainsString(
            'businessProfileOptions',
            $source
        );

        $this->assertStringContainsString(
            'business_profile_completed_at',
            $source
        );

        $this->assertStringContainsString(
            '$scoring->calculate($answers)',
            $source
        );
    }

    public function test_admin_receives_profile_catalog(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
            )
        );

        $this->assertStringContainsString(
            "'businessProfileOptions'",
            $source
        );

        $this->assertStringContainsString(
            "'lauda360_business_profile'",
            $source
        );
    }
}
