<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientExecutionProgressContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_client_controller_reuses_existing_execution_relations(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'phases.execution'",
            "'phases.capabilities.execution'",
            '$clientPhases =',
            '$executionSummary =',
            "'execution_summary' => \$executionSummary",
            "'phases' => \$clientPhases",
            "'pending_count' =>",
            "'in_progress_count' =>",
            "'blocked_count' =>",
            "'completed_count' =>",
            "'cancelled_count' =>",
            "'progress_percentage' =>",
            "'started_at' =>",
            "'completed_at' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_execution_payload_blocks_private_operational_fields(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'blocking_reason' =>",
            "'internal_notes' =>",
            "'evidence_snapshot' =>",
            "'assigned_user_id' =>",
            "'source_snapshot' =>",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_ui_is_read_only_and_shows_progress(): void
    {
        $page = $this->read(
            'resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        foreach ([
            'Progreso de implementación',
            'Avance general',
            'Avance de esta fase',
            'function executionStatusLabel(',
            'function progressPercent(',
            'phase.execution',
            '.execution',
            'Esta capacidad está bloqueada.',
            'Completar una capacidad no significa',
            'Go-Live',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        foreach ([
            'blocking_reason',
            'internal_notes',
            'evidence_snapshot',
            'assigned_user_id',
            'source_snapshot',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $page
            );
        }
    }

    public function test_existing_execution_domain_remains_source_of_truth(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        );

        foreach ([
            'public function initializePhase(',
            'public function startCapability(',
            'public function updateCapabilityProgress(',
            'public function blockCapability(',
            'public function completeCapability(',
            'public function refreshPhase(',
            "'progress_percentage' => \$progress",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }
}
