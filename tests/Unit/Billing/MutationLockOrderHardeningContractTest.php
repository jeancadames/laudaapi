<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class MutationLockOrderHardeningContractTest extends TestCase
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

    public function test_cancellation_uses_subscription_then_item_order(): void
    {
        $source = $this->source(
            'app/Http/Controllers/Subscriber/SubscriberServiceCancellationController.php'
        );

        foreach ([
            'use App\\Models\\Subscription;',
            'Subscription es el mutex de cancelación.',
            '$subscription = Subscription::query()',
            '$item = SubscriptionItem::query()',
            '->lockForUpdate()',
            '}, 3);',
            "\$item->status = 'cancelled';",
            'SubscriptionTotalsService::class',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $tx = explode(
            '$result = DB::transaction',
            $source,
            2
        )[1];

        $subscriptionPos = strpos(
            $tx,
            '$subscription = Subscription::query()'
        );

        $itemPos = strpos(
            $tx,
            '$item = SubscriptionItem::query()'
        );

        $cancelPos = strpos(
            $tx,
            "\$item->status = 'cancelled';"
        );

        $this->assertNotFalse($subscriptionPos);
        $this->assertNotFalse($itemPos);
        $this->assertNotFalse($cancelPos);

        $this->assertLessThan(
            $itemPos,
            $subscriptionPos
        );

        $this->assertLessThan(
            $cancelPos,
            $itemPos
        );
    }

    public function test_r2j_and_cancellation_share_parent_first_order(): void
    {
        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $central = $this->source(
            'app/Services/Entitlements/CentralEntitlementActivationService.php'
        );

        $cancel = $this->source(
            'app/Http/Controllers/Subscriber/SubscriberServiceCancellationController.php'
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );

        $this->assertStringContainsString(
            'Subscriber → Subscription → SubscriptionItem.',
            $central
        );

        $this->assertStringContainsString(
            'Subscription es el mutex de cancelación.',
            $cancel
        );
    }

    public function test_totals_has_subscription_lock_and_retry(): void
    {
        $source = $this->source(
            'app/Services/Billing/SubscriptionTotalsService.php'
        );

        foreach ([
            'Subscription::query()',
            '->lockForUpdate()',
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            '}, 3);',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_invoice_reconciliation_has_locks_and_retry(): void
    {
        $source = $this->source(
            'app/Services/Billing/InvoiceReconciliationService.php'
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, '->lockForUpdate()')
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, '}, 3);')
        );
    }

    public function test_invoice_item_sorts_ids_before_reconcile_loop(): void
    {
        $source = $this->source(
            'app/Models/InvoiceItem.php'
        );

        $sort = strpos(
            $source,
            'sort($invoiceIds, SORT_NUMERIC);'
        );

        $loop = strpos(
            $source,
            'foreach ($invoiceIds as $invoiceId)'
        );

        $this->assertNotFalse($sort);
        $this->assertNotFalse($loop);
        $this->assertLessThan($loop, $sort);
    }

    public function test_payment_sorts_ids_before_reconcile_loop(): void
    {
        $source = $this->source(
            'app/Models/Payment.php'
        );

        $sort = strpos(
            $source,
            'sort($invoiceIds, SORT_NUMERIC);'
        );

        $loop = strpos(
            $source,
            'foreach ($invoiceIds as $invoiceId)'
        );

        $this->assertNotFalse($sort);
        $this->assertNotFalse($loop);
        $this->assertLessThan($loop, $sort);
    }
}
