<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationSubscriptionContractTest extends TestCase
{
    private function project(string $path): string
    {
        return dirname(__DIR__, 3).'/'.$path;
    }

    public function test_migration_links_go_live_to_real_subscription_stack(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_164000_create_transformation_implementation_subscription_activations_table.php'
        ));

        $this->assertStringContainsString(
            "Schema::create('transformation_implementation_subscription_activations'",
            $migration
        );

        foreach ([
            "'transformation_implementation_capability_go_live_id'",
            "'subscriber_id'",
            "'company_id'",
            "'subscription_id'",
            "'activation_type'",
            "'go_live_at'",
            "'subscription_started_at'",
        ] as $required) {
            $this->assertStringContainsString($required, $migration);
        }
    }

    public function test_mysql_constraint_names_are_explicit_and_short(): void
    {
        $migration = file_get_contents($this->project(
            'database/migrations/2026_08_24_164000_create_transformation_implementation_subscription_activations_table.php'
        ));

        foreach ([
            'tip_sa_go_live_fk',
            'tip_sa_subscriber_fk',
            'tip_sa_company_fk',
            'tip_sa_subscription_fk',
            'tip_sa_created_fk',
            'tip_sa_go_live_uq',
            'tip_sa_sub_subscription_idx',
            'tip_sa_company_status_idx',
        ] as $identifier) {
            $this->assertStringContainsString($identifier, $migration);
            $this->assertLessThanOrEqual(64, strlen($identifier));
        }
    }

    public function test_subscription_requires_live_go_live(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        ));

        $this->assertStringContainsString(
            'TransformationImplementationCapabilityGoLive::STATUS_LIVE',
            $service
        );
        $this->assertStringContainsString('!$goLive->went_live_at', $service);
        $this->assertStringContainsString(
            'La suscripción LAUDAAPI solo puede iniciar después de un Go-Live LIVE.',
            $service
        );
    }

    public function test_company_must_belong_to_subscriber(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        ));

        $this->assertStringContainsString(
            '(int) $company->subscriber_id !== (int) $subscriber->id',
            $service
        );
    }

    public function test_new_subscription_starts_exactly_from_go_live(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        ));

        $this->assertStringContainsString(
            "'starts_at' => \$goLive->went_live_at",
            $service
        );
        $this->assertStringContainsString(
            "'current_period_start' => \$periodStart",
            $service
        );
        $this->assertStringContainsString("'trial_ends_at' => null", $service);
        $this->assertStringContainsString("'status' => 'active'", $service);
    }

    public function test_active_subscription_is_reused_for_later_go_lives(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        ));

        $this->assertStringContainsString("->where('status', 'active')", $service);
        $this->assertStringContainsString('TYPE_REUSED', $service);
        $this->assertStringContainsString('TYPE_CREATED', $service);
    }

    public function test_r2i_does_not_create_subscriber_or_company(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        ));

        $this->assertStringNotContainsString('Subscriber::create', $service);
        $this->assertStringNotContainsString('Company::create', $service);
        $this->assertStringNotContainsString('ensureSubscriber', $service);
        $this->assertStringNotContainsString('ensureCompany', $service);
    }

    public function test_r2i_does_not_create_subscription_items_yet(): void
    {
        $service = file_get_contents($this->project(
            'app/Services/Diagnosis/TransformationImplementationSubscriptionService.php'
        ));

        $this->assertStringNotContainsString('SubscriptionItem::create', $service);
        $this->assertStringContainsString(
            "'subscription_items_pending_r2j' => true",
            $service
        );
    }
}
