<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationCentralEntitlementDelegationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(
        string $relative
    ): string {
        return file_get_contents(
            $this->root().'/'.$relative
        );
    }

    public function test_r2i_and_r2j_share_the_same_central_owner(): void
    {
        $r2i = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([$r2i, $r2j] as $source) {
            $this->assertStringContainsString(
                'CentralEntitlementActivationService',
                $source
            );

            $this->assertStringContainsString(
                'SOURCE_TRANSFORMATION_360',
                $source
            );
        }

        $this->assertStringContainsString(
            '->ensureSubscription(',
            $r2i
        );

        $this->assertStringContainsString(
            '->activateCommercialItem(',
            $r2j
        );
    }

    public function test_economic_mutations_are_removed_from_t360_adapters(): void
    {
        $r2i = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        foreach ([
            'Subscription::query()->create',
            'Subscription::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $r2i
            );
        }

        foreach ([
            'SubscriptionItem::query()->create',
            'SubscriptionItem::create',
            '$existingItem->forceFill(',
            'SubscriptionTotalsService::class',
            'ServicePricingEngine::class',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $r2j
            );
        }
    }

    public function test_t360_ledgers_remain_local_to_t360(): void
    {
        $r2i = $this->source(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        $r2j = $this->source(
            'app/Services/Diagnosis/TransformationImplementationCapabilitySubscriptionService.php'
        );

        $this->assertStringContainsString(
            'TransformationImplementationSubscriptionActivation::query()',
            $r2i
        );

        $this->assertStringContainsString(
            'TransformationImplementationSubscriptionItemActivation::query()',
            $r2j
        );
    }

    public function test_orchestrators_and_admin_contract_remain_separate_steps(): void
    {
        $sub = $this->source(
            'app/Services/Diagnosis/TransformationImplementationPostGoLiveSubscriptionService.php'
        );

        $item = $this->source(
            'app/Services/Diagnosis/TransformationImplementationPostGoLiveServiceActivationService.php'
        );

        $controller = $this->source(
            'app/Http/Controllers/Admin/AdminTransformationImplementationExecutionController.php'
        );

        $this->assertStringContainsString(
            'activateSubscriptionForGoLive(',
            $sub
        );

        $this->assertStringContainsString(
            'Primero debe completarse R2-I',
            $item
        );

        $this->assertStringContainsString(
            'public function activateSubscription(',
            $controller
        );

        $this->assertStringContainsString(
            'public function activateService(',
            $controller
        );
    }
}
