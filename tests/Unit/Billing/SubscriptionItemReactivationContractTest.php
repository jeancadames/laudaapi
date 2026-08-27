<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class SubscriptionItemReactivationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_legacy_trial_route_is_no_longer_a_reactivation_owner(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        foreach ([
            'buildTrialItem',
            '$item->forceFill(',
            'LaudaOneProvisioner',
            'SubscriptionTotalsService::class',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        $this->assertStringContainsString(
            'legacy_service_trial_activation_blocked_t360',
            $source
        );
    }

    public function test_r2j_reuses_cancelled_row_instead_of_duplicate_create(): void
    {
        $r2j = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = file_get_contents(
            $this->root()
            .'/app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        foreach ([
            "'subscription_id'",
            "'service_id'",
            '->lockForUpdate()',
            '$item->forceFill(',
            "'reactivated'",
            "'reused'",
            "'created'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }

        $this->assertStringNotContainsString(
            'SubscriptionItem::query()->create',
            $r2j
        );
    }

    public function test_database_contract_is_one_item_per_subscription_and_service(): void
    {
        $migrationFiles = glob(
            $this->root()
            .'/database/migrations/*.php'
        );

        $source = '';

        foreach ($migrationFiles as $file) {
            $chunk = file_get_contents(
                $file
            );

            if (
                str_contains(
                    $chunk,
                    'subscription_items'
                )
                && str_contains(
                    $chunk,
                    'service_id'
                )
            ) {
                $source .= $chunk;
            }
        }

        $this->assertStringContainsString(
            "'subscription_id', 'service_id'",
            $source
        );
    }
}
