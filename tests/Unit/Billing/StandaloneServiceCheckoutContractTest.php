<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class StandaloneServiceCheckoutContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Billing/StandaloneServiceCheckoutService.php'
        );
    }

    public function test_checkout_uses_non_persistent_subscription_pricing_probe(): void
    {
        $source = $this->source();

        foreach ([
            '$pricingProbe = new Subscription([',
            'ServicePricingEngine::class',
            ')->quote(',
            "'billing_cycle' =>",
            "'currency' =>",
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        $this->assertStringNotContainsString(
            'Subscription::query()->create',
            $source
        );
    }

    public function test_checkout_rejects_non_billable_and_zero_upfront(): void
    {
        $source = $this->source();

        foreach ([
            'if (! $service->billable)',
            'if ($amountDue <= 0)',
            'requiere un monto ',
            'inicial mayor que cero',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }
    }

    public function test_checkout_is_idempotent_before_invoice_creation(): void
    {
        $source = $this->source();

        $existing = strpos($source, '$existing =');
        $invoiceCreate = strpos($source, 'Invoice::query()->create');

        $this->assertNotFalse($existing);
        $this->assertNotFalse($invoiceCreate);
        $this->assertLessThan($invoiceCreate, $existing);

        $this->assertStringContainsString(
            "'activation_request_service_id',",
            $source
        );
    }

    public function test_checkout_creates_invoice_item_and_pending_settlement_only(): void
    {
        $source = $this->source();

        foreach ([
            'Invoice::query()->create',
            'InvoiceItem::query()->create',
            "'service_id' =>",
            "'subscription_id' => null",
            'StandaloneServiceSettlementService::class',
            ')->registerPending(',
            "'pending_payment'",
            "'entitlement_granted' => false",
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }

        foreach ([
            'SubscriptionItem::query()->create',
            'CentralEntitlementActivationService',
            'activateCommercial(',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_checkout_uses_request_row_as_mutex(): void
    {
        $source = $this->source();

        foreach ([
            'ActivationRequestService::query()',
            '->lockForUpdate()',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }
    }
}
