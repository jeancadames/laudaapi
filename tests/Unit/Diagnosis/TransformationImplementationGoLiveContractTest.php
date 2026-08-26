<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationGoLiveContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_migration_creates_versioned_go_live_attempts_per_capability(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_163000_create_transformation_implementation_capability_go_lives_table.php'
        ));

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_capability_go_lives'",
            $migration
        );

        foreach ([
            "'transformation_implementation_phase_capability_id'",
            "'transformation_implementation_capability_execution_id'",
            "'attempt'",
            "'status'",
            "'readiness_snapshot'",
            "'scheduled_at'",
            "'went_live_at'",
            "'rolled_back_at'",
            "'rollback_reason'",
        ] as $required) {
            $this->assertStringContainsString($required, $migration);
        }

        $this->assertStringContainsString(
            "['transformation_implementation_phase_capability_id', 'attempt']",
            $migration
        );
    }

    public function test_mysql_constraint_names_are_explicit_and_short(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_163000_create_transformation_implementation_capability_go_lives_table.php'
        ));

        foreach ([
            'tip_gl_capability_fk',
            'tip_gl_execution_fk',
            'tip_gl_created_fk',
            'tip_gl_updated_fk',
            'tip_gl_live_by_fk',
            'tip_gl_cap_attempt_uq',
            'tip_gl_status_scheduled_idx',
            'tip_gl_cap_status_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $migration);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_go_live_has_explicit_lifecycle_states(): void
    {
        $model = file_get_contents($this->project(
            'app/Models/TransformationImplementationCapabilityGoLive.php'
        ));

        foreach ([
            "STATUS_DRAFT = 'draft'",
            "STATUS_READY = 'ready'",
            "STATUS_SCHEDULED = 'scheduled'",
            "STATUS_LIVE = 'live'",
            "STATUS_ROLLED_BACK = 'rolled_back'",
            "STATUS_CANCELLED = 'cancelled'",
        ] as $state) {
            $this->assertStringContainsString($state, $model);
        }
    }

    public function test_completed_execution_is_required_before_go_live_preparation(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        ));

        $this->assertStringContainsString(
            'TransformationImplementationCapabilityExecution::STATUS_COMPLETED',
            $service
        );
        $this->assertStringContainsString(
            'La capacidad debe estar completada al 100% antes de preparar su Go-Live.',
            $service
        );
    }

    public function test_readiness_is_required_before_activation(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        ));

        $this->assertStringContainsString('markReady', $service);
        $this->assertStringContainsString('readiness_snapshot', $service);
        $this->assertStringContainsString(
            'No se puede ejecutar el Go-Live sin readiness validado.',
            $service
        );
    }

    public function test_live_and_rollback_are_distinct_auditable_events(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        ));

        $this->assertStringContainsString('function goLive(', $service);
        $this->assertStringContainsString('went_live_at', $service);
        $this->assertStringContainsString('went_live_by_user_id', $service);

        $this->assertStringContainsString('function rollback(', $service);
        $this->assertStringContainsString('rolled_back_at', $service);
        $this->assertStringContainsString('rollback_reason', $service);
    }

    public function test_only_one_active_go_live_can_exist_per_capability(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        ));

        $this->assertStringContainsString('$alreadyLive', $service);
        $this->assertStringContainsString(
            'La capacidad ya tiene otro Go-Live activo.',
            $service
        );
    }

    public function test_r2h_does_not_start_subscription_or_bill(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationGoLiveService.php'
        ));

        foreach ([
            'Company::',
            'Subscriber::',
            'Subscription::',
            'Invoice::',
            'Payment::',
            'createSubscription',
            'startSubscription',
            'invoice_reference',
            'payment_reference',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $service);
        }
    }
}
