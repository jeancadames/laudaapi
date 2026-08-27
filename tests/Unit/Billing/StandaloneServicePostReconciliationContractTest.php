<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class StandaloneServicePostReconciliationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $relative): string
    {
        return file_get_contents($this->root().'/'.$relative);
    }

    public function test_both_reconciliation_entrypoints_finish_through_same_post_hook(): void
    {
        $source = $this->read('app/Services/Billing/InvoiceReconciliationService.php');
        $this->assertGreaterThanOrEqual(2, substr_count($source, 'return $this->afterReconciliation('));
        $this->assertStringContainsString('private function afterReconciliation(', $source);
    }

    public function test_post_hook_dispatches_paid_activation_and_non_paid_revocation(): void
    {
        $source = $this->read(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        $method = explode(
            'private function afterReconciliation(',
            $source,
            2
        )[1];

        $method = explode(
            'private function normalizeCurrency(',
            $method,
            2
        )[0];

        foreach ([
            "\$status === 'paid'",
            '->settlePaidInvoice(',
            '->revokeUnpaidInvoice(',
            '->recordPostReconciliationFailure(',
            '->recordPostReconciliationRevocationFailure(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }
    }

    public function test_payment_model_remains_decoupled_from_entitlement(): void
    {
        $payment = $this->read('app/Models/Payment.php');
        $this->assertStringContainsString('InvoiceReconciliationService::class', $payment);
        foreach (['StandaloneServiceSettlementService','CentralEntitlementActivationService','Subscription::','SubscriptionItem::'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $payment);
        }
    }

    public function test_reconciliation_does_not_become_economic_owner(): void
    {
        $source = $this->read('app/Services/Billing/InvoiceReconciliationService.php');
        foreach (['CentralEntitlementActivationService','Subscription::query()->create','SubscriptionItem::query()->create'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_failure_recording_keeps_pending_settlement_retryable(): void
    {
        $source = $this->read('app/Services/Billing/StandaloneServiceSettlementService.php');
        $method = explode('public function recordPostReconciliationFailure(', $source, 2)[1];
        $method = explode('private function assertIdentity(', $method, 2)[0];
        foreach (['STATUS_PENDING_PAYMENT', "'failure_reason'", "'last_post_reconciliation_failure'", "'retryable' => true"] as $required) {
            $this->assertStringContainsString($required, $method);
        }
        $this->assertStringNotContainsString('STATUS_FAILED', $method);
    }

    public function test_paid_invoice_adapter_is_idempotent_at_ledger_level(): void
    {
        $source = $this->read('app/Services/Billing/StandaloneServiceSettlementService.php');
        foreach (['public function settlePaidInvoice(', 'STATUS_PENDING_PAYMENT', 'STATUS_ACTIVATED', 'STATUS_REVOKED', '$this->settle('] as $required) {
            $this->assertStringContainsString($required, $source);
        }
    }


    public function test_activation_request_query_builder_uses_real_id_column(): void
    {
        foreach ([
            'app/Services/Billing/StandaloneServiceCheckoutService.php',
            'app/Services/Billing/StandaloneServiceSettlementService.php',
        ] as $path) {
            $source = $this->read($path);
            $normalized = preg_replace('/\\s+/', ' ', $source);

            $this->assertDoesNotMatchRegularExpression(
                "/DB::table\\(\\s*'activation_request_service'\\s*\\)"
                ."\\s*->whereKey\\(/",
                $normalized
            );
        }
    }
}
