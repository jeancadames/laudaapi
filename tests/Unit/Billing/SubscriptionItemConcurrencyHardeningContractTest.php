<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class SubscriptionItemConcurrencyHardeningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(string $relative): string
    {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_r2j_serializes_item_creation_by_subscription_row(): void
    {
        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            'Subscriber → Subscription → SubscriptionItem.',
            'Subscriber::query()',
            'Subscription::query()',
            'SubscriptionItem::query()',
            '->lockForUpdate()',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }

        $method = explode(
            'public function activateCommercialItem(',
            $central,
            2
        )[1];

        $method = explode(
            'public function activateCommercial(',
            $method,
            2
        )[0];

        $subscriberPos = strpos(
            $method,
            'Subscriber::query()'
        );

        $subscriptionPos = strpos(
            $method,
            'Subscription::query()'
        );

        $itemPos = strpos(
            $method,
            'SubscriptionItem::query()'
        );

        $this->assertNotFalse($subscriberPos);
        $this->assertNotFalse($subscriptionPos);
        $this->assertNotFalse($itemPos);

        $this->assertLessThan(
            $subscriptionPos,
            $subscriberPos
        );

        $this->assertLessThan(
            $itemPos,
            $subscriptionPos
        );
    }

    public function test_r2j_rechecks_same_golive_item_activation_after_mutex(): void
    {
        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $transaction = explode(
            'return DB::transaction',
            $r2j,
            2
        )[1];

        foreach ([
            '$subscriber = Subscriber::query()',
            '$subscription =',
            '$lockedActivation',
            'TransformationImplementationSubscriptionItemActivation::query()',
            'return $lockedActivation;',
            '->activateCommercialItem(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $transaction
            );
        }

        $subscriberPos = strpos(
            $transaction,
            '$subscriber = Subscriber::query()'
        );

        $subscriptionPos = strpos(
            $transaction,
            '$subscription ='
        );

        $recheckPos = strpos(
            $transaction,
            '$lockedActivation'
        );

        $delegatePos = strpos(
            $transaction,
            '->activateCommercialItem('
        );

        $this->assertNotFalse($subscriberPos);
        $this->assertNotFalse($subscriptionPos);
        $this->assertNotFalse($recheckPos);
        $this->assertNotFalse($delegatePos);

        $this->assertLessThan(
            $subscriptionPos,
            $subscriberPos
        );

        $this->assertLessThan(
            $recheckPos,
            $subscriptionPos
        );

        $this->assertLessThan(
            $delegatePos,
            $recheckPos
        );
    }

    public function test_subscription_item_unique_constraint_remains_final_guard(): void
    {
        $migration = $this->source(
            'database/migrations/2026_01_24_122419_subscription_items_table.php'
        );

        $this->assertStringContainsString(
            "\$table->unique(['subscription_id', 'service_id']);",
            $migration
        );
    }

    public function test_item_activation_ledger_remains_unique_per_golive(): void
    {
        $migration = $this->source(
            'database/migrations/2026_08_24_165100_create_transformation_implementation_subscription_item_activations_table.php'
        );

        $this->assertStringContainsString(
            "['transformation_implementation_capability_go_live_id']",
            $migration
        );

        $this->assertStringContainsString(
            '->unique(',
            $migration
        );
    }

    public function test_cancelled_item_is_reactivated_in_place(): void
    {
        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            '$item->forceFill(',
            '$payload',
            ')->save();',
            '$item = $item->fresh();',
            "'reactivated'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }
    }

    public function test_r2j_still_recalculates_subscription_totals_centrally(): void
    {
        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        $this->assertStringContainsString(
            'SubscriptionTotalsService::class',
            $central
        );

        $this->assertStringContainsString(
            ')->recalculate(',
            $central
        );
    }

    public function test_concurrency_hardening_does_not_move_pricing_out_of_engine(): void
    {
        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $central
        );

        $this->assertStringContainsString(
            '->quote(',
            $central
        );
    }
}
