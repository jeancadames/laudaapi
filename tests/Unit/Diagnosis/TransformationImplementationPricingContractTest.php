<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPricingContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_migration_creates_phase_modality_estimates(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_160000_create_transformation_implementation_phase_estimates_table.php'
        ));

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_phase_estimates'",
            $migration
        );
        $this->assertStringContainsString("'transformation_implementation_phase_id'", $migration);
        $this->assertStringContainsString("'modality'", $migration);
        $this->assertStringContainsString("'price_amount'", $migration);
        $this->assertStringContainsString("'currency'", $migration);
        $this->assertStringContainsString("'estimated_duration_value'", $migration);
        $this->assertStringContainsString("'estimated_duration_unit'", $migration);
        $this->assertStringContainsString("'scope_snapshot'", $migration);
    }

    public function test_mysql_constraint_names_are_explicit_and_short(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_160000_create_transformation_implementation_phase_estimates_table.php'
        ));

        foreach ([
            'tip_est_phase_fk',
            'tip_est_created_fk',
            'tip_est_updated_fk',
            'tip_est_phase_modality_uq',
            'tip_est_mod_currency_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $migration);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_each_phase_has_one_estimate_per_modality(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_160000_create_transformation_implementation_phase_estimates_table.php'
        ));

        $this->assertStringContainsString(
            "['transformation_implementation_phase_id', 'modality']",
            $migration
        );
        $this->assertStringContainsString('tip_est_phase_modality_uq', $migration);
    }

    public function test_pricing_uses_existing_modality_catalog(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationPricingService.php'
        ));

        $this->assertStringContainsString(
            'TransformationImplementationModalityCatalog $catalog',
            $service
        );
        $this->assertStringContainsString('$this->catalog->exists($modality)', $service);
        $this->assertStringContainsString('$this->catalog->get($modality)', $service);
    }

    public function test_price_and_time_are_mutable_only_before_acceptance(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationPricingService.php'
        ));

        $this->assertStringContainsString(
            "in_array(\$phase->plan->status, ['draft', 'presented'], true)",
            $service
        );
        $this->assertStringContainsString(
            'Precio y tiempo solo pueden modificarse antes de aceptar el Plan de Implementación.',
            $service
        );
    }

    public function test_selected_modality_resolves_phase_estimate(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationPricingService.php'
        ));

        $this->assertStringContainsString('forSelectedModality', $service);
        $this->assertStringContainsString('selected_modality', $service);
        $this->assertStringContainsString('totalForSelectedModality', $service);
    }

    public function test_r2e_does_not_bill_collect_or_start_subscription(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationPricingService.php'
        ));

        foreach ([
            'Company::',
            'Subscriber::',
            'Subscription::',
            'Invoice::',
            'Payment::',
            'Milestone::',
            'go_live',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $service);
        }
    }
}
