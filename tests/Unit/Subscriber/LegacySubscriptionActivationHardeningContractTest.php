<?php

namespace Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;

class LegacySubscriptionActivationHardeningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_legacy_controller_no_longer_creates_identity_subscription_or_trial(): void
    {
        $source = file_get_contents(
            $this->root().'/app/Http/Controllers/Subscriber/SubscriberActivationController.php'
        );

        foreach ([
            'Subscriber::create(',
            'Company::create(',
            'Subscription::create(',
            'ensureSubscriber(',
            'ensureCompany(',
            'ensureSubscription(',
            'ensureActivationTrial(',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }

        foreach ([
            'legacy_subscriber_activation_blocked_t360',
            'new_subscription_requires_lauda360_golive',
            'legacy_subscriber_activation_preserved_existing',
            "'active'",
            "'trialing'",
        ] as $required) {
            $this->assertStringContainsString($required, $source);
        }
    }

    public function test_r2i_remains_real_subscription_creation_boundary(): void
    {
        $r2i = file_get_contents(
            $this->root().'/app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        );

        foreach ([
            'activateFromGoLive(',
            'STATUS_LIVE',
            'Subscription::query()->create(',
            "'status' => 'active'",
            "'trial_ends_at' => null",
        ] as $required) {
            $this->assertStringContainsString($required, $r2i);
        }
    }

    public function test_route_is_preserved_as_compatibility_guard(): void
    {
        $routes = file_get_contents($this->root().'/routes/subscriber.php');
        $this->assertStringContainsString(
            "SubscriberActivationController::class, 'activate'",
            $routes
        );
        $this->assertStringContainsString("'/activation/activate'", $routes);
    }
}
