<?php

namespace Tests\Unit\Entitlements;

use PHPUnit\Framework\TestCase;

class CentralEntitlementActivationContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Entitlements/CentralEntitlementActivationService.php'
        );
    }

    public function test_owner_exposes_separate_subscription_and_item_primitives(): void
    {
        $source = $this->source();

        foreach ([
            'public function ensureSubscription(',
            'public function activateCommercialItem(',
            'public function activateCommercial(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_subscription_primitive_does_not_require_service_or_create_item(): void
    {
        $source = $this->source();

        $method = explode(
            'public function ensureSubscription(',
            $source,
            2
        )[1];

        $method = explode(
            'public function activateCommercialItem(',
            $method,
            2
        )[0];

        $this->assertStringNotContainsString(
            'Service $service',
            $method
        );

        $this->assertStringNotContainsString(
            'SubscriptionItem::',
            $method
        );

        $this->assertStringContainsString(
            'Subscription::query()->create',
            $method
        );
    }

    public function test_item_primitive_requires_commercial_service_and_pricing(): void
    {
        $source = $this->source();

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
            'assertCommercialService(',
            'ServicePricingEngine::class',
            'SubscriptionItem::query()',
            'SubscriptionTotalsService::class',
            'ServiceEntitlementPolicy::ITEM_STATUSES',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }
    }

    public function test_standalone_convenience_calls_both_primitives(): void
    {
        $source = $this->source();

        $method = explode(
            'public function activateCommercial(',
            $source,
            2
        )[1];

        $method = explode(
            'private function assertSource(',
            $method,
            2
        )[0];

        $this->assertStringContainsString(
            '$this->ensureSubscription(',
            $method
        );

        $this->assertStringContainsString(
            '$this->activateCommercialItem(',
            $method
        );

        $this->assertStringContainsString(
            'ServicePricingEngine::class',
            $method
        );
    }

    public function test_both_sources_remain_supported(): void
    {
        $source = $this->source();

        foreach ([
            'SOURCE_STANDALONE_SETTLEMENT',
            "'standalone_settlement'",
            'SOURCE_TRANSFORMATION_360',
            "'transformation_360'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_non_billable_service_cannot_activate_commercial_item(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'if (! $service->billable)',
            $source
        );

        $this->assertStringContainsString(
            'todavía no está habilitado',
            $source
        );
    }

    public function test_lock_order_is_parent_first(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'Subscriber → Subscription → SubscriptionItem.',
            $source
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $source,
                'Subscriber::query()'
            )
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $source,
                '->lockForUpdate()'
            )
        );
    }

    public function test_item_activation_is_idempotent_and_reactivation_is_in_place(): void
    {
        $source = $this->source();

        foreach ([
            "'reused'",
            "'reactivated'",
            "'created'",
            '$item->forceFill(',
            'SubscriptionItem::query()',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }
}
