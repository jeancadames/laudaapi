<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExecutiveSummaryIntegrationContractTest extends TestCase
{
    public function test_submit_generates_editable_review_draft(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
            )
        );

        $this->assertStringContainsString(
            'DiagnosisExecutiveSummaryGenerator',
            $source
        );

        $this->assertStringContainsString(
            '$executiveSummary->generate(',
            $source
        );

        $this->assertStringContainsString(
            'blank($assessment->review_summary)',
            $source
        );

        $this->assertStringContainsString(
            'empty($assessment->review_priorities)',
            $source
        );

        $this->assertStringNotContainsString(
            'blank($assessment->final_modality)',
            $source
        );

        $this->assertStringNotContainsString(
            '$assessment->final_modality =',
            $source
        );
    }

    public function test_r1_a_does_not_publish_result(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Http/Controllers/Diagnosis/DigitalDiagnosisController.php'
            )
        );

        $submitStart = strpos(
            $source,
            'public function submit('
        );

        $this->assertNotFalse(
            $submitStart
        );

        $submit = substr(
            $source,
            $submitStart
        );

        $this->assertStringNotContainsString(
            "'status' => 'reviewed'",
            $submit
        );

        $this->assertStringNotContainsString(
            "'published_at' => now()",
            $submit
        );
    }
}
