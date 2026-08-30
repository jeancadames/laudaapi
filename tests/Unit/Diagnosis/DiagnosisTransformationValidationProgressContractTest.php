<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisTransformationValidationProgressContractTest extends TestCase
{
    public function test_progress_tracks_tenant_review_and_validation_for_all_three_deliverables(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/DiagnosisTransformationProgressService.php'
        );

        foreach ([
            'expanded_report_reviewed',
            'expanded_report_validated',
            'roadmap_reviewed',
            'roadmap_validated',
            'implementation_plan',
            'implementation_plan_reviewed',
            'implementation_plan_validated',
            'DiagnosisDeliverableValidation::TYPE_EXPANDED_REPORT',
            'DiagnosisDeliverableValidation::TYPE_DETAILED_ROADMAP',
            'DiagnosisDeliverableValidation::TYPE_IMPLEMENTATION_PLAN',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }
}
