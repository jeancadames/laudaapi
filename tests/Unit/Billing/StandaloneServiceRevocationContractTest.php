<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class StandaloneServiceRevocationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $relative): string
    {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_ledger_already_has_revocation_state_without_new_migration(): void
    {
        $model = $this->read(
            'app/Models/StandaloneServiceSettlement.php'
        );

        $migration = $this->read(
            'database/migrations/2026_08_27_150000_create_standalone_service_settlements_table.php'
        );

        foreach ([
            'STATUS_REVOKED',
            "'revoked'",
            "'revoked_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $model
            );
        }

        $this->assertStringContainsString(
            "'revoked_at'",
            $migration
        );
    }

    public function test_central_owner_tracks_multiple_entitlement_claims(): void
    {
        $source = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            "'entitlement_claims'",
            "'last_entitlement_claim'",
            'entitlementClaimKey(',
            'SOURCE_STANDALONE_SETTLEMENT',
            'SOURCE_TRANSFORMATION_360',
            "'standalone_settlement_id'",
            "'transformation_implementation_capability_go_live_id'",
            "'legacy_backfill'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_reused_active_item_persists_new_claim(): void
    {
        $source = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $method = explode(
            'public function activateCommercialItem(',
            $source,
            2
        )[1];

        $method = explode(
            'public function activateCommercial(',
            $method,
            2
        )[0];

        foreach ([
            '$alreadyEntitled',
            "'meta' => $itemMeta",
            '$item->forceFill(',
            "'entitlement_claims'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }
    }

    public function test_central_revocation_releases_claim_before_cancelling_item(): void
    {
        $source = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $method = explode(
            'public function revokeCommercialItem(',
            $source,
            2
        )[1];

        $method = explode(
            'private function entitlementClaimKey(',
            $method,
            2
        )[0];

        foreach ([
            'Subscription::query()',
            'SubscriptionItem::query()',
            '->lockForUpdate()',
            'unset($claims[$claimKey])',
            "'preserved_by_other_claim'",
            "'status' => 'cancelled'",
            'SubscriptionTotalsService::class',
            "'remaining_claims'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }

        $subscriptionPos = strpos(
            $method,
            'Subscription::query()'
        );

        $itemPos = strpos(
            $method,
            'SubscriptionItem::query()'
        );

        $this->assertNotFalse($subscriptionPos);
        $this->assertNotFalse($itemPos);

        $this->assertLessThan(
            $itemPos,
            $subscriptionPos
        );

        $this->assertStringNotContainsString(
            "\$subscription->status = 'cancelled'",
            $method
        );
    }

    public function test_settlement_revocation_delegates_its_own_claim_to_central_owner(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $method = explode(
            'public function revoke(',
            $source,
            2
        )[1];

        $method = explode(
            'public function revokeUnpaidInvoice(',
            $method,
            2
        )[0];

        foreach ([
            'STATUS_ACTIVATED',
            'STATUS_REVOKED',
            '->revokeCommercialItem(',
            "'standalone_settlement_id'",
            "'entitlement_granted'",
            "'revoked_at'",
            "'last_revocation'",
            "'item_revocation'",
            'standalone_service_entitlement_revoked',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }

        $this->assertStringNotContainsString(
            "'subscription_id' => null",
            $method
        );

        $this->assertStringNotContainsString(
            "'subscription_item_id' => null",
            $method
        );
    }

    public function test_non_paid_adapter_never_revokes_pending_payment(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $method = explode(
            'public function revokeUnpaidInvoice(',
            $source,
            2
        )[1];

        $method = explode(
            'public function recordPostReconciliationRevocationFailure(',
            $method,
            2
        )[0];

        foreach ([
            "=== 'paid'",
            'STATUS_ACTIVATED',
            'STATUS_REVOKED',
            '$this->revoke(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }

        $this->assertStringNotContainsString(
            'STATUS_PENDING_PAYMENT',
            $method
        );
    }

    public function test_revocation_failure_is_retryable_without_marking_revoked(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $method = explode(
            'public function recordPostReconciliationRevocationFailure(',
            $source,
            2
        )[1];

        $method = explode(
            'Adapter futuro para InvoiceReconciliationService',
            $method,
            2
        )[0];

        foreach ([
            'STATUS_ACTIVATED',
            "'failure_reason'",
            "'last_post_reconciliation_revocation_failure'",
            "'retryable'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }

        $this->assertStringNotContainsString(
            'STATUS_REVOKED',
            $method
        );
    }

    public function test_repayment_reactivates_same_item_and_restores_standalone_claim(): void
    {
        $settlement = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $central = $this->read(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        foreach ([
            'STATUS_REVOKED',
            "'cancelled'",
            "'revoked_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $settlement
            );
        }

        foreach ([
            "'reactivated'",
            "'entitlement_claims'",
            '$item->forceFill(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $central
            );
        }
    }

    public function test_payment_remains_decoupled_from_revocation_owner(): void
    {
        $payment = $this->read(
            'app/Models/Payment.php'
        );

        $this->assertStringContainsString(
            'InvoiceReconciliationService::class',
            $payment
        );

        foreach ([
            'StandaloneServiceSettlementService',
            'CentralEntitlementActivationService',
            'revokeUnpaidInvoice',
            'revokeCommercialItem',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $payment
            );
        }
    }
}
