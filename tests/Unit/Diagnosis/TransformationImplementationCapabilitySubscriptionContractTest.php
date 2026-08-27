<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationCapabilitySubscriptionContractTest extends TestCase
{
    private function project(
        string $path
    ): string {
        return dirname(__DIR__, 3)
            .'/'.$path;
    }

    private function source(): string
    {
        return file_get_contents(
            $this->project(
                'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
            )
        );
    }

    public function test_mapping_remains_capability_to_real_service(): void
    {
        $source = $this->source();

        foreach ([
            'upsertMapping(',
            "'capability_key'",
            "'service_id' =>",
            'TransformationImplementationCapabilityServiceMapping::query()',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            "'social'",
            $source
        );
    }

    public function test_r2j_requires_live_and_matching_r2i(): void
    {
        $source = $this->source();

        foreach ([
            'TransformationImplementationCapabilityGoLive::STATUS_LIVE',
            'La activación de suscripción debe ',
            'pertenecer al mismo Go-Live.',
            'subscriptionActivation->subscription_id',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_r2j_delegates_item_mutation_to_central_owner(): void
    {
        $source = $this->source();

        foreach ([
            'CentralEntitlementActivationService::class',
            '->activateCommercialItem(',
            'SOURCE_TRANSFORMATION_360',
            "'activation_mode' =>",
            "'post_go_live'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'SubscriptionItem::query()->create',
            $source
        );

        $this->assertStringNotContainsString(
            '$existingItem->forceFill(',
            $source
        );

        $this->assertStringNotContainsString(
            'SubscriptionTotalsService::class',
            $source
        );
    }

    public function test_r2j_preserves_trace_ledger_and_price_snapshot(): void
    {
        $source = $this->source();

        foreach ([
            'TransformationImplementationSubscriptionItemActivation::query()',
            "'transformation_implementation_capability_service_mapping_id'",
            "'subscription_item_id'",
            "'price_snapshot' =>",
            "\$central['pricing']",
            'TYPE_CREATED',
            'TYPE_REUSED',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_r2j_parent_lock_order_starts_subscriber_then_subscription(): void
    {
        $source = $this->source();

        $tx = explode(
            'return DB::transaction',
            $source,
            2
        )[1];

        $subscriber = strpos(
            $tx,
            '$subscriber = Subscriber::query()'
        );

        $subscription = strpos(
            $tx,
            '$subscription ='
        );

        $delegation = strpos(
            $tx,
            '->activateCommercialItem('
        );

        $this->assertNotFalse($subscriber);
        $this->assertNotFalse($subscription);
        $this->assertNotFalse($delegation);

        $this->assertLessThan(
            $subscription,
            $subscriber
        );

        $this->assertLessThan(
            $delegation,
            $subscription
        );
    }
}
