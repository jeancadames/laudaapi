<?php

namespace Tests\Unit\AppHub;

use App\Models\ServicePlan;
use PHPUnit\Framework\TestCase;

class SolutionOwnedCommercialPlanFoundationContractTest extends TestCase
{
    private function source(string $path): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 3).'/'.$path
        );

        $this->assertIsString($source);

        return $source;
    }

    public function test_solution_owned_plan_foundation_is_declared_in_migration(): void
    {
        $migration = $this->source(
            'database/migrations/2026_08_27_211500_create_solution_owned_service_plans_foundation.php'
        );

        foreach ([
            "'service_plans'",
            "'service_id'",
            "'code'",
            "'name'",
            "'currency'",
            "'billing_model'",
            "'monthly_price'",
            "'yearly_price'",
            "'source_solution'",
            "'source_plan_key'",
            "'source_snapshot'",
            "'synced_at'",
            "'active'",
            "'service_plan_pricing_tiers'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $migration
            );
        }
    }

    public function test_plan_reference_is_declared_in_all_central_commercial_lanes(): void
    {
        $migration = $this->source(
            'database/migrations/2026_08_27_211500_create_solution_owned_service_plans_foundation.php'
        );

        foreach ([
            "'activation_request_service'",
            "'invoice_items'",
            "'standalone_service_settlements'",
            "'subscription_items'",
        ] as $table) {
            $this->assertStringContainsString(
                $table,
                $migration
            );
        }

        $this->assertGreaterThanOrEqual(
            4,
            substr_count(
                $migration,
                "'service_plan_id'"
            )
        );
    }

    public function test_subscription_item_billing_cycle_is_declared_without_removing_legacy_subscription_cycle(): void
    {
        $migration = $this->source(
            'database/migrations/2026_08_27_211500_create_solution_owned_service_plans_foundation.php'
        );

        $this->assertStringContainsString(
            "'subscription_items'",
            $migration
        );

        $this->assertStringContainsString(
            "'billing_cycle'",
            $migration
        );

        $up = explode(
            'public function down(): void',
            $migration,
            2
        )[0];

        $this->assertStringNotContainsString(
            "Schema::table(\n            'subscriptions'",
            $up
        );
    }

    public function test_service_plan_is_a_solution_owned_offer_mirror(): void
    {
        $plan = new ServicePlan([
            'service_id' => 1,
            'code' => 'growth',
            'name' => 'Growth',
            'currency' => 'DOP',
            'billing_model' => 'flat',
            'monthly_price' => 1900,
            'yearly_price' => 18240,
            'source_solution' => 'social',
            'source_plan_key' => 'growth',
            'source_snapshot' => [
                'owner' => 'social.laudaapi.com',
            ],
            'active' => true,
        ]);

        $this->assertSame(
            'social',
            $plan->source_solution
        );

        $this->assertSame(
            'growth',
            $plan->source_plan_key
        );

        $this->assertSame(
            '1900.00',
            $plan->monthly_price
        );

        $this->assertSame(
            '18240.00',
            $plan->yearly_price
        );

        $this->assertSame(
            [
                'owner' => 'social.laudaapi.com',
            ],
            $plan->source_snapshot
        );
    }

    public function test_existing_models_expose_plan_relationship_contracts(): void
    {
        $service = $this->source(
            'app/Models/Service.php'
        );

        $item = $this->source(
            'app/Models/SubscriptionItem.php'
        );

        $invoiceItem = $this->source(
            'app/Models/InvoiceItem.php'
        );

        $settlement = $this->source(
            'app/Models/StandaloneServiceSettlement.php'
        );

        foreach ([
            'public function commercialPlans(): HasMany',
            'ServicePlan::class',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }

        foreach ([
            $item,
            $invoiceItem,
            $settlement,
        ] as $source) {
            $this->assertStringContainsString(
                'public function servicePlan()',
                $source
            );

            $this->assertStringContainsString(
                'ServicePlan::class',
                $source
            );
        }
    }

    public function test_legacy_service_pricing_contract_is_preserved(): void
    {
        $service = $this->source(
            'app/Models/Service.php'
        );

        foreach ([
            "'billing_model'",
            "'currency'",
            "'monthly_price'",
            "'yearly_price'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }

        $migration = $this->source(
            'database/migrations/2026_08_27_211500_create_solution_owned_service_plans_foundation.php'
        );

        foreach ([
            "dropColumn('monthly_price')",
            "dropColumn('yearly_price')",
            "dropColumn('billing_model')",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $migration
            );
        }
    }
}
