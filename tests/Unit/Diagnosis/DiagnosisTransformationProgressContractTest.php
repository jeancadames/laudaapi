<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisTransformationProgressContractTest extends TestCase
{
    public function test_progress_service_contains_free_consultive_sequence(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/app/Services/Diagnosis/'
            .'DiagnosisTransformationProgressService.php'
        );

        foreach ([
            'request_submitted',
            'access_approved',
            'invitation_sent',
            'account_activated',
            'diagnosis_started',
            'diagnosis_submitted',
            'diagnosis_reviewed',
            'diagnosis_published',
            'expanded_report_preparation',
            'expanded_report_published',
            'roadmap_preparation',
            'roadmap_published',
            'implementation_plan',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'expanded_report_requested',
            'expanded_report_invoiced',
            'expanded_report_paid',
            'roadmap_requested',
            'roadmap_invoiced',
            'roadmap_paid',
            'DiagnosisExpandedReportOrder',
            'DiagnosisDetailedRoadmapOrder',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_progress_supports_all_visual_states(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/app/Services/Diagnosis/'
            .'DiagnosisTransformationProgressService.php'
        );

        foreach ([
            "'completed'",
            "'current'",
            "'pending'",
            "'blocked'",
            "'completed_count'",
            "'percentage'",
            "'next_action'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
