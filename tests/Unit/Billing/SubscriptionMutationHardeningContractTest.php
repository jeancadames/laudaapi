<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;

class SubscriptionMutationHardeningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_legacy_trial_activation_no_longer_mutates_items_or_totals(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Subscriber/SubscriberServiceActivationController.php'
        );

        foreach ([
            'buildTrialItem',
            'SubscriptionTotalsService::class',
            'LaudaOneProvisioner',
            '$item->forceFill(',
            'SubscriptionItem::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }

        foreach ([
            'legacy_service_trial_activation_blocked_t360',
            "'pending_payment'",
            "'entitlement_granted' =>",
            'false',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_cancellation_recalculates_central_totals(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Subscriber/SubscriberServiceCancellationController.php'
        );

        $this->assertStringContainsString(
            "\$item->status = 'cancelled';",
            $controller
        );

        $this->assertStringContainsString(
            'app(SubscriptionTotalsService::class)',
            $controller
        );

        $this->assertStringNotContainsString(
            "\$sub->status = 'cancelled'",
            $controller
        );

        $this->assertStringNotContainsString(
            "\$subscription->status = 'cancelled'",
            $controller
        );
    }

    public function test_zero_item_subscription_clears_residual_tax(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Billing/SubscriptionTotalsService.php'
        );

        foreach ([
            '$tax = $subtotal <= 0',
            "'tax_amount' =>",
            "'subtotal_amount' =>",
            "'discount_amount' =>",
            "'total_amount' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_r2j_keeps_using_same_totals_engine(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $this->assertStringContainsString(
            'SubscriptionTotalsService::class',
            $service
        );

        $this->assertStringContainsString(
            'recalculateSubscriptionTotals',
            $service
        );
    }
}
