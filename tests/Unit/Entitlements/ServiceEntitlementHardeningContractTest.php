<?php

namespace Tests\Unit\Entitlements;

use PHPUnit\Framework\TestCase;

class ServiceEntitlementHardeningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_policy_excludes_pending_from_real_access(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Entitlements/ServiceEntitlementPolicy.php'
        );

        $this->assertStringContainsString(
            'ITEM_STATUSES',
            $source
        );

        $this->assertStringContainsString(
            "'active'",
            $source
        );

        $this->assertStringContainsString(
            "'trialing'",
            $source
        );

        $itemBlock = explode(
            'public const ITEM_STATUSES = [',
            $source,
            2
        )[1];

        $itemBlock = explode(
            '];',
            $itemBlock,
            2
        )[0];

        $this->assertStringNotContainsString(
            "'pending'",
            $itemBlock
        );
    }

    public function test_subscriber_entitlements_uses_central_policy(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Entitlements/SubscriberEntitlements.php'
        );

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            $source
        );

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
            $source
        );

        $this->assertStringNotContainsString(
            "whereIn('subscription_items.status', ['active', 'trialing', 'pending'])",
            $source
        );

        $this->assertStringNotContainsString(
            "'PAGO'",
            $source
        );
    }

    public function test_direct_url_middleware_uses_same_item_policy(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Middleware/EnsureServiceEntitled.php'
        );

        foreach ([
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
            "'services.active'",
            '->exists()',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "['active', 'trialing', 'pending']",
            $source
        );

        $this->assertStringNotContainsString(
            'Cache::remember',
            $source
        );

        $this->assertStringNotContainsString(
            'use Illuminate\\Support\\Facades\\Cache;',
            $source
        );
    }

    public function test_service_launch_access_is_not_cross_request_cached(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/LaudaErp/ServiceAccessResolver.php'
        );

        $method = explode(
            'private function hasActiveEntitlement(',
            $source,
            2
        )[1];

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::ITEM_STATUSES',
            $method
        );

        $this->assertStringContainsString(
            'ServiceEntitlementPolicy::SUBSCRIPTION_STATUSES',
            $method
        );

        $this->assertStringContainsString(
            "->where(\n                'services.active',\n                true",
            $method
        );

        $this->assertStringNotContainsString(
            'Cache::remember',
            $method
        );

        $this->assertStringNotContainsString(
            "'pending'",
            $method
        );
    }

    public function test_subscription_item_model_agrees_with_policy(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Models/SubscriptionItem.php'
        );

        $this->assertStringContainsString(
            "['active', 'trialing']",
            $source
        );

        $this->assertStringNotContainsString(
            "['active', 'trialing', 'pending']",
            $source
        );
    }
}
