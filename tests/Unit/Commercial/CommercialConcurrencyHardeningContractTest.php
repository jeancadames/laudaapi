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
        $r2i = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            'Subscriber::query()',
            '->lockForUpdate()',
            '->ensureSubscription(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $r2i
            );
        }

        foreach ([
            'Subscriber es el mutex de la Subscription general.',
            'Subscriber::query()',
            'Subscription::query()',
            '->lockForUpdate()',
            'Subscription::query()->create',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }

        $method = explode(
            'public function ensureSubscription(',
            $central,
            2
        )[1];

        $method = explode(
            'public function activateCommercialItem(',
            $method,
            2
        )[0];

        $lockPos = strpos(
            $method,
            'Subscriber::query()'
        );

        $createPos = strpos(
            $method,
            'Subscription::query()->create'
        );

        $this->assertNotFalse($lockPos);
        $this->assertNotFalse($createPos);

        $this->assertLessThan(
            $createPos,
            $lockPos
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
            'Subscriber::query()',
            '$lockedActivation',
            'transformation_implementation_capability_go_live_id',
            'return $lockedActivation;',
            '->ensureSubscription(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $transaction
            );
        }

        $lockPos = strpos(
            $transaction,
            'Subscriber::query()'
        );

        $recheckPos = strpos(
            $transaction,
            '$lockedActivation'
        );

        $delegatePos = strpos(
            $transaction,
            '->ensureSubscription('
        );

        $this->assertNotFalse($lockPos);
        $this->assertNotFalse($recheckPos);
        $this->assertNotFalse($delegatePos);

        $this->assertLessThan(
            $recheckPos,
            $lockPos
        );

        $this->assertLessThan(
            $delegatePos,
            $recheckPos
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
        $r2i = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            '->ensureSubscription(',
            "'subscription_items_pending_r2j' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $r2i
            );
        }

        foreach ([
            "'status' => 'active'",
            "'trial_ends_at' => null",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }
    }
}
