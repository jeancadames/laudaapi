<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationClientGoLiveContractTest extends TestCase
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

    public function test_client_reuses_latest_go_live_relation(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'phases.capabilities.latestGoLive'",
            '$goLive =',
            "'go_live' => \$goLive ? [",
            "'ready_at' =>",
            "'scheduled_at' =>",
            "'went_live_at' =>",
            "'rolled_back_at' =>",
            '$goLiveSummary =',
            "'go_live_summary' => \$goLiveSummary",
            "'without_go_live_count' =>",
            "'draft_count' =>",
            "'ready_count' =>",
            "'scheduled_count' =>",
            "'live_count' =>",
            "'rolled_back_count' =>",
            "'cancelled_count' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_go_live_payload_blocks_private_fields(): void
    {
        $source = $this->read(
            'app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "'readiness_snapshot' =>",
            "'evidence_snapshot' =>",
            "'rollback_reason' =>",
            "'internal_notes' =>",
            "'created_by_user_id' =>",
            "'updated_by_user_id' =>",
            "'went_live_by_user_id' =>",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_ui_separates_execution_from_go_live(): void
    {
        $page = $this->read(
            'resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        foreach ([
            'Estado de puesta en marcha',
            'Puesta en marcha de esta capacidad',
            'function goLiveStatusLabel(',
            'function goLiveDateLabel(',
            'Aún no preparada',
            'Lista para Go-Live',
            'Go-Live programado',
            'LIVE',
            'Revertida',
            'capability.go_live',
            '.ready_at',
            '.scheduled_at',
            '.went_live_at',
            '.rolled_back_at',
            'Estar LIVE tampoco',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        foreach ([
            'readiness_snapshot',
            'evidence_snapshot',
            'rollback_reason',
            'internal_notes',
            'went_live_by_user_id',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $page
            );
        }
    }

    public function test_go_live_domain_still_requires_completed_execution(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        );

        foreach ([
            'public function createAttempt(',
            'STATUS_COMPLETED',
            'progress_percentage',
            'public function markReady(',
            'public function schedule(',
            'public function goLive(',
            'public function rollback(',
            'public function cancel(',
            'public function isLive(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }
}
