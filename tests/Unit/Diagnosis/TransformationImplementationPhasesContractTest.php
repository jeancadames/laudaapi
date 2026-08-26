<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPhasesContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_migrations_create_only_phase_and_capability_structure(): void
    {
        $phases = file_get_contents($this->project(
            'database/migrations/2026_08_24_153000_create_transformation_implementation_phases_table.php'
        ));

        $capabilities = file_get_contents($this->project(
            'database/migrations/2026_08_24_153100_create_transformation_implementation_phase_capabilities_table.php'
        ));

        $this->assertStringContainsString("Schema::create('transformation_implementation_phases'", $phases);
        $this->assertStringContainsString("'transformation_implementation_plan_id'", $phases);
        $this->assertStringContainsString("'sequence'", $phases);
        $this->assertStringContainsString("'name'", $phases);
        $this->assertStringContainsString("'objective'", $phases);
        $this->assertStringContainsString("'source_snapshot'", $phases);

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_phase_capabilities'",
            $capabilities
        );
        $this->assertStringContainsString("'capability_key'", $capabilities);
        $this->assertStringContainsString("'capability_label'", $capabilities);
        $this->assertStringContainsString("'capability_summary'", $capabilities);

        foreach ([
            'price',
            'amount',
            'invoice',
            'milestone',
            'subscription',
            'go_live',
        ] as $forbidden) {
            $this->assertStringNotContainsString("'$forbidden'", $phases);
            $this->assertStringNotContainsString("'$forbidden'", $capabilities);
        }
    }

    public function test_mysql_constraint_names_are_explicit_and_short(): void
    {
        $files = [
            file_get_contents($this->project(
                'database/migrations/2026_08_24_153000_create_transformation_implementation_phases_table.php'
            )),
            file_get_contents($this->project(
                'database/migrations/2026_08_24_153100_create_transformation_implementation_phase_capabilities_table.php'
            )),
        ];

        $all = implode("\n", $files);

        foreach ([
            'tip_phase_plan_fk',
            'tip_phase_created_fk',
            'tip_phase_updated_fk',
            'tip_phase_plan_seq_uq',
            'tip_pc_phase_fk',
            'tip_pc_phase_cap_uq',
            'tip_pc_phase_seq_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $all);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_models_define_plan_phase_capability_hierarchy(): void
    {
        $plan = file_get_contents($this->project('app/Models/TransformationImplementationPlan.php'));
        $phase = file_get_contents($this->project('app/Models/TransformationImplementationPhase.php'));
        $capability = file_get_contents(
            $this->project('app/Models/TransformationImplementationPhaseCapability.php')
        );

        $this->assertStringContainsString('function phases(', $plan);
        $this->assertStringContainsString('function plan(', $phase);
        $this->assertStringContainsString('function capabilities(', $phase);
        $this->assertStringContainsString('function phase(', $capability);
    }

    public function test_service_only_accepts_capabilities_from_roadmap_snapshot(): void
    {
        $service = file_get_contents(
            $this->project('app/Services/Diagnosis/TransformationImplementationPhaseService.php')
        );

        $this->assertStringContainsString('createPhaseFromRoadmap', $service);
        $this->assertStringContainsString('snapshotContainsToken', $service);
        $this->assertStringContainsString(
            'no pertenece al snapshot del Roadmap de este plan',
            $service
        );
        $this->assertStringContainsString('DB::transaction', $service);
    }

    public function test_r2c_does_not_create_commercial_or_subscription_entities(): void
    {
        $service = file_get_contents(
            $this->project('app/Services/Diagnosis/TransformationImplementationPhaseService.php')
        );

        foreach ([
            'Company::',
            'Subscriber::',
            'Subscription::',
            'Invoice::',
            'Payment::',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $service);
        }
    }
}
