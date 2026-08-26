<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanAcceptanceContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );
    }

    public function test_plan_has_public_present_and_accept_transitions(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'public function markPresented(',
            $source
        );

        $this->assertStringContainsString(
            'public function acceptPlan(',
            $source
        );

        $this->assertStringContainsString('STATUS_DRAFT', $source);
        $this->assertStringContainsString('STATUS_PRESENTED', $source);
        $this->assertStringContainsString('STATUS_ACCEPTED', $source);
    }

    public function test_acceptance_requires_commercial_readiness(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'private function commercialReadiness(',
            $source
        );

        $this->assertStringContainsString(
            "load('phases.capabilities')",
            $source
        );

        $this->assertStringContainsString(
            'TransformationImplementationPhaseEstimate::query()',
            $source
        );

        $this->assertStringContainsString(
            'TransformationImplementationMilestone::query()',
            $source
        );

        $this->assertStringContainsString(
            "!== 'DOP'",
            $source
        );

        $this->assertStringContainsString(
            'abs($estimateAmount - $allocatedAmount)',
            $source
        );
    }

    public function test_acceptance_does_not_create_subscription_stack(): void
    {
        $source = $this->source();

        $this->assertStringNotContainsString(
            'Subscriber::create(',
            $source
        );

        $this->assertStringNotContainsString(
            'Company::create(',
            $source
        );

        $this->assertStringNotContainsString(
            'Subscription::create(',
            $source
        );

        $this->assertStringNotContainsString(
            'SubscriptionItem::create(',
            $source
        );

        $this->assertStringContainsString(
            "'subscription_created' => false",
            $source
        );
    }
}
