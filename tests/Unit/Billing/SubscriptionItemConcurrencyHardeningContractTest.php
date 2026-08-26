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
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            'Subscription es el mutex de sus SubscriptionItems.',
            'Subscription::query()',
            '->whereKey($subscription->id)',
            "->where('subscriber_id', \$subscriptionActivation->subscriber_id)",
            "->where('status', 'active')",
            '->lockForUpdate()',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $transaction = explode(
            'return DB::transaction',
            $source,
            2
        )[1];

        $subscriptionLock = strpos(
            $transaction,
            '->whereKey($subscription->id)'
        );

        $itemLookup = strpos(
            $transaction,
            '$existingItem = SubscriptionItem::query()'
        );

        $itemCreate = strpos(
            $transaction,
            'SubscriptionItem::query()'
        );

        $this->assertNotFalse($subscriptionLock);
        $this->assertNotFalse($itemLookup);
        $this->assertNotFalse($itemCreate);

        $this->assertLessThan(
            $itemLookup,
            $subscriptionLock,
            'La Subscription debe bloquearse antes de resolver el item.'
        );
    }

    public function test_r2j_rechecks_same_golive_item_activation_after_mutex(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $transaction = explode(
            'return DB::transaction',
            $source,
            2
        )[1];

        foreach ([
            '$lockedActivation',
            'TransformationImplementationSubscriptionItemActivation::query()',
            'transformation_implementation_capability_go_live_id',
            'return $lockedActivation;',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $transaction
            );
        }

        $lockPos = strpos(
            $transaction,
            '->whereKey($subscription->id)'
        );

        $recheckPos = strpos(
            $transaction,
            '$lockedActivation'
        );

        $itemLookupPos = strpos(
            $transaction,
            '$existingItem = SubscriptionItem::query()'
        );

        $this->assertNotFalse($lockPos);
        $this->assertNotFalse($recheckPos);
        $this->assertNotFalse($itemLookupPos);

        $this->assertLessThan(
            $recheckPos,
            $lockPos,
            'El mutex debe adquirirse antes del activation recheck.'
        );

        $this->assertLessThan(
            $itemLookupPos,
            $recheckPos,
            'El activation recheck debe ocurrir antes de resolver/create item.'
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
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            '$existingItem->forceFill(',
            '$itemPayload',
            ')->save();',
            '$item = $existingItem->fresh();',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_r2j_still_recalculates_subscription_totals_centrally(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            'SubscriptionTotalsService::class',
            '->recalculate(',
            '$this->recalculateSubscriptionTotals($subscription);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_concurrency_hardening_does_not_move_pricing_out_of_engine(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $source
        );

        $this->assertStringContainsString(
            '->quote(',
            $source
        );
    }
}
