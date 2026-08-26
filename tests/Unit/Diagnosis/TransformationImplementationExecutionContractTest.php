<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationExecutionContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_migrations_separate_phase_and_capability_execution(): void
    {
        $phase = file_get_contents($this->project(
            'database/migrations/2026_08_24_162000_create_transformation_implementation_phase_executions_table.php'
        ));

        $capability = file_get_contents($this->project(
            'database/migrations/2026_08_24_162100_create_transformation_implementation_capability_executions_table.php'
        ));

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_phase_executions'",
            $phase
        );

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_capability_executions'",
            $capability
        );

        foreach ([
            "'status'",
            "'progress_percentage'",
            "'assigned_user_id'",
            "'started_at'",
            "'blocked_at'",
            "'completed_at'",
            "'blocking_reason'",
            "'evidence_snapshot'",
        ] as $required) {
            $this->assertStringContainsString($required, $phase);
            $this->assertStringContainsString($required, $capability);
        }
    }

    public function test_mysql_constraint_names_are_explicit_and_short(): void
    {
        $all = file_get_contents($this->project(
            'database/migrations/2026_08_24_162000_create_transformation_implementation_phase_executions_table.php'
        ))."\n".file_get_contents($this->project(
            'database/migrations/2026_08_24_162100_create_transformation_implementation_capability_executions_table.php'
        ));

        foreach ([
            'tip_pe_phase_fk',
            'tip_pe_assigned_fk',
            'tip_pe_created_fk',
            'tip_pe_updated_fk',
            'tip_pe_phase_uq',
            'tip_pe_status_assigned_idx',
            'tip_ce_capability_fk',
            'tip_ce_assigned_fk',
            'tip_ce_created_fk',
            'tip_ce_updated_fk',
            'tip_ce_capability_uq',
            'tip_ce_status_assigned_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $all);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_execution_uses_operational_states_without_go_live(): void
    {
        $phaseModel = file_get_contents($this->project(
            'app/Models/TransformationImplementationPhaseExecution.php'
        ));

        $capModel = file_get_contents($this->project(
            'app/Models/TransformationImplementationCapabilityExecution.php'
        ));

        foreach ([
            "STATUS_PENDING = 'pending'",
            "STATUS_IN_PROGRESS = 'in_progress'",
            "STATUS_BLOCKED = 'blocked'",
            "STATUS_COMPLETED = 'completed'",
            "STATUS_CANCELLED = 'cancelled'",
        ] as $state) {
            $this->assertStringContainsString($state, $phaseModel);
            $this->assertStringContainsString($state, $capModel);
        }

        $this->assertStringNotContainsString('go_live', $phaseModel);
        $this->assertStringNotContainsString('go_live', $capModel);
    }

    public function test_execution_can_only_initialize_from_accepted_plan(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        ));

        $this->assertStringContainsString("\$phase->plan->status !== 'accepted'", $service);
        $this->assertStringContainsString('!$phase->plan->accepted_at', $service);
        $this->assertStringContainsString(
            'La ejecución solo puede inicializarse desde un Plan de Implementación aceptado.',
            $service
        );
    }

    public function test_phase_progress_is_derived_from_capability_execution(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        ));

        $this->assertStringContainsString('refreshPhase', $service);
        $this->assertStringContainsString('progress_percentage', $service);
        $this->assertStringContainsString('$capabilityExecutions->avg', $service);
        $this->assertStringContainsString('$allCompleted', $service);
        $this->assertStringContainsString('$hasBlocked', $service);
    }

    public function test_phase_only_completes_when_all_capabilities_complete(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        ));

        $this->assertStringContainsString('assertPhaseCompleted', $service);
        $this->assertStringContainsString(
            'La fase solo puede cerrarse cuando todas sus capacidades estén completadas.',
            $service
        );
    }

    public function test_r2g_does_not_create_go_live_subscription_or_billing(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationExecutionService.php'
        ));

        foreach ([
            'Company::',
            'Subscriber::',
            'Subscription::',
            'Invoice::',
            'Payment::',
            'createSubscription',
            'startSubscription',
            'go_live',
            'invoice_reference',
            'payment_reference',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $service);
        }
    }
}
