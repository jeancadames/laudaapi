<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisOrphanWorkflowContractTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.ltrim($file, '/')
        ) ?: '';
    }

    private function methodSource(
        string $source,
        string $start,
        string $end
    ): string {
        $startPosition = strpos($source, $start);
        $endPosition = strpos(
            $source,
            $end,
            $startPosition ?: 0
        );

        if (
            $startPosition === false
            || $endPosition === false
        ) {
            return '';
        }

        return substr(
            $source,
            $startPosition,
            $endPosition - $startPosition
        );
    }

    public function test_active_workflow_without_assessment_is_not_pending_reassessment(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/InitialDiagnosisCommercialService.php'
        );

        $method = $this->methodSource(
            $source,
            'private function pendingNativeWorkflowForCompany',
            'private function activeNativeWorkflowForCompany'
        );

        $this->assertNotSame('', $method);

        foreach ([
            "->whereNull('diagnosis_assessment_id')",
            "->whereNotIn('status', [",
            'DiagnosisAccessRequest::STATUS_ACTIVE',
            'DiagnosisAccessRequest::STATUS_REJECTED',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $method
            );
        }

        $this->assertStringNotContainsString(
            "->where('status', '!=', DiagnosisAccessRequest::STATUS_REJECTED)",
            $method
        );
    }

    public function test_tenant_state_only_marks_real_pending_or_working_reassessment(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/InitialDiagnosisCommercialService.php'
        );

        foreach ([
            '$pending = $this->pendingNativeWorkflowForCompany($company);',
            '$pending !== null || $workingWorkflow !== null',
            '$pending === null',
            '$workingWorkflow === null',
            "'reassessment_pending' => \$reassessmentPending",
            "'can_request_new' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
