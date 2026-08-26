<?php

namespace Tests\Unit\Commercial;

use PHPUnit\Framework\TestCase;

class CommercialConcurrencyHardeningContractTest extends TestCase
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

    public function test_provisioning_serializes_same_customer_by_user_row(): void
    {
        $source = $this->source(
            'app/Services/Commercial/CommercialCustomerProvisioningService.php'
        );

        foreach ([
            'User::query()',
            '->whereKey($user->id)',
            '->lockForUpdate()',
            '->firstOrFail()',
            'DB::transaction',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $userLock = strpos(
            $source,
            '->whereKey($user->id)'
        );

        $subscriberCreate = strpos(
            $source,
            'Subscriber::query()->create'
        );

        $this->assertNotFalse($userLock);
        $this->assertNotFalse($subscriberCreate);
        $this->assertLessThan(
            $subscriberCreate,
            $userLock,
            'El User debe bloquearse antes de crear Subscriber.'
        );
    }

    public function test_r2i_serializes_general_subscription_by_subscriber_row(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        foreach ([
            'Subscriber::query()',
            '->whereKey($subscriber->id)',
            '->lockForUpdate()',
            'El Subscriber debe permanecer activo durante la activación post-Go-Live.',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $subscriberLock = strpos(
            $source,
            '->whereKey($subscriber->id)'
        );

        $subscriptionCreate = strpos(
            $source,
            'Subscription::query()->create'
        );

        $this->assertNotFalse($subscriberLock);
        $this->assertNotFalse($subscriptionCreate);
        $this->assertLessThan(
            $subscriptionCreate,
            $subscriberLock,
            'El Subscriber debe bloquearse antes de crear Subscription.'
        );
    }

    public function test_r2i_rechecks_same_golive_activation_after_mutex(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $transaction = explode(
            'return DB::transaction',
            $source,
            2
        )[1];

        foreach ([
            '$lockedActivation',
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
            '->whereKey($subscriber->id)'
        );

        $recheckPos = strpos(
            $transaction,
            '$lockedActivation'
        );

        $subscriptionCreatePos = strpos(
            $transaction,
            'Subscription::query()->create'
        );

        $this->assertNotFalse($lockPos);
        $this->assertNotFalse($recheckPos);
        $this->assertNotFalse($subscriptionCreatePos);

        $this->assertLessThan(
            $recheckPos,
            $lockPos,
            'El mutex debe adquirirse antes del recheck.'
        );

        $this->assertLessThan(
            $subscriptionCreatePos,
            $recheckPos,
            'El recheck debe ocurrir antes de crear Subscription.'
        );
    }

    public function test_r2i_keeps_database_idempotency_ledger(): void
    {
        $migration = $this->source(
            'database/migrations/2026_08_24_164000_create_transformation_implementation_subscription_activations_table.php'
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

    public function test_company_one_per_subscriber_constraint_remains_intact(): void
    {
        $migration = $this->source(
            'database/migrations/2026_01_24_122210_create_companies_table.php'
        );

        $this->assertStringContainsString(
            "\$table->unique('subscriber_id');",
            $migration
        );
    }

    public function test_hardening_does_not_create_subscription_item_or_trial_in_provisioning(): void
    {
        $source = $this->source(
            'app/Services/Commercial/CommercialCustomerProvisioningService.php'
        );

        foreach ([
            'Subscription::query()->create',
            'SubscriptionItem::query()->create',
            "'status' => 'trialing'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_r2i_created_subscription_still_has_no_trial(): void
    {
        $source = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        foreach ([
            "'status' => 'active'",
            "'trial_ends_at' => null",
            "'subscription_items_pending_r2j' => true",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
}
