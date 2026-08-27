<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class StandaloneServiceSettlementContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(
        string $relative
    ): string {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_migration_creates_exact_request_invoice_item_ledger(): void
    {
        $source = $this->read(
            'database/migrations/2026_08_27_150000_create_standalone_service_settlements_table.php'
        );

        foreach ([
            'standalone_service_settlements',
            "'activation_request_service_id'",
            "'activation_request_id'",
            "'subscriber_id'",
            "'company_id'",
            "'service_id'",
            "'invoice_id'",
            "'invoice_item_id'",
            "'subscription_id'",
            "'subscription_item_id'",
            "'billing_cycle'",
            "'currency'",
            "'amount_due'",
            "'amount_paid'",
            "'evidence_snapshot'",
            "'sss_request_row_uq'",
            "'sss_invoice_item_uq'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_down_refuses_to_delete_real_activated_history(): void
    {
        $source = $this->read(
            'database/migrations/2026_08_27_150000_create_standalone_service_settlements_table.php'
        );

        foreach ([
            "->where(",
            "'status'",
            "'activated'",
            'existen activaciones comerciales reales',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_registration_is_idempotent_by_request_row_and_invoice_item(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        foreach ([
            'public function registerPending(',
            "'activation_request_service_id'",
            "'invoice_item_id'",
            '->lockForUpdate()',
            'assertSameCheckoutEvidence(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "'payment_id' =>",
            $source
        );
    }

    public function test_settlement_requires_canonical_paid_invoice_evidence(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        foreach ([
            "!== 'paid'",
            "'invoice_id'",
            'whereNotNull(',
            "'paid_at'",
            'invoice_amount_paid',
            'MONEY_EPSILON',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_invoice_item_must_match_exact_invoice_and_service(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        foreach ([
            '(int) $invoiceItem->invoice_id',
            '(int) $invoice->id',
            '(int) $invoiceItem->service_id',
            '(int) $service->id',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_settlement_delegates_to_same_central_owner_as_t360(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        foreach ([
            'CentralEntitlementActivationService::class',
            '->activateCommercial(',
            'SOURCE_STANDALONE_SETTLEMENT',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'Subscription::query()->create',
            'SubscriptionItem::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_non_billable_service_is_blocked_before_entitlement(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $this->assertStringContainsString(
            'if (! (bool) $service->billable)',
            $source
        );

        $this->assertStringContainsString(
            'todavía no está habilitado',
            $source
        );
    }

    public function test_request_row_becomes_active_only_after_central_activation(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $activation = strpos(
            $source,
            '->activateCommercial('
        );

        $requestActive = strpos(
            $source,
            "'status' =>\n                        'active'"
        );

        $this->assertNotFalse(
            $activation
        );

        $this->assertNotFalse(
            $requestActive
        );

        $this->assertLessThan(
            $requestActive,
            $activation
        );

        $this->assertStringContainsString(
            "'entitlement_granted' =>\n                        true",
            $source
        );
    }

    public function test_reconciliation_dispatches_activation_and_revocation_after_canonical_status(): void
    {
        $reconciliation = $this->read(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        $payment = $this->read(
            'app/Models/Payment.php'
        );

        foreach ([
            'private function afterReconciliation(',
            "\$status === 'paid'",
            'StandaloneServiceSettlementService::class',
            '->settlePaidInvoice(',
            '->revokeUnpaidInvoice(',
            '->recordPostReconciliationFailure(',
            '->recordPostReconciliationRevocationFailure(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $reconciliation
            );
        }

        $this->assertStringContainsString(
            'InvoiceReconciliationService::class',
            $payment
        );

        $this->assertStringNotContainsString(
            'StandaloneServiceSettlementService',
            $payment
        );

        $this->assertStringNotContainsString(
            'CentralEntitlementActivationService',
            $reconciliation
        );
    }

    public function test_social_is_not_special_cased_in_settlement_foundation(): void
    {
        $source = $this->read(
            'app/Services/Billing/StandaloneServiceSettlementService.php'
        );

        $this->assertStringNotContainsString(
            "'social'",
            $source
        );

        $this->assertStringNotContainsString(
            'service_key',
            $source
        );
    }
}
