<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationSubscriptionContractTest extends TestCase
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
                'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
            )
        );
    }

    public function test_migration_links_go_live_to_real_subscription_stack(): void
    {
        $migration = file_get_contents(
            $this->project(
                'database/migrations/2026_08_24_164000_create_transformation_implementation_subscription_activations_table.php'
            )
        );

        foreach ([
            'transformation_implementation_subscription_activations',
            "'transformation_implementation_capability_go_live_id'",
            "'subscriber_id'",
            "'company_id'",
            "'subscription_id'",
            "'activation_type'",
            "'go_live_at'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $migration
            );
        }
    }

    public function test_r2i_still_requires_live_golive(): void
    {
        $source = $this->source();

        foreach ([
            'TransformationImplementationCapabilityGoLive::STATUS_LIVE',
            '! $goLive->went_live_at',
            'La suscripción LAUDAAPI solo puede ',
            'iniciar después de un Go-Live LIVE.',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_r2i_delegates_subscription_to_central_owner(): void
    {
        $source = $this->source();

        foreach ([
            'CentralEntitlementActivationService::class',
            '->ensureSubscription(',
            'SOURCE_TRANSFORMATION_360',
            "'subscription_items_pending_r2j' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'Subscription::query()->create',
            $source
        );

        $this->assertStringNotContainsString(
            'SubscriptionItem::',
            $source
        );
    }

    public function test_r2i_preserves_created_reused_ledger_semantics(): void
    {
        $source = $this->source();

        foreach ([
            "'subscription_created'",
            'TYPE_CREATED',
            'TYPE_REUSED',
            'TransformationImplementationSubscriptionActivation::query()',
            "'source_snapshot' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_r2i_preserves_company_subscriber_identity_guard(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            '(int) $company->subscriber_id',
            $source
        );

        $this->assertStringContainsString(
            '(int) $subscriber->id',
            $source
        );
    }
}
