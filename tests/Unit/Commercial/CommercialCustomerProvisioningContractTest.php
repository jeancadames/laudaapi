<?php

namespace Tests\Unit\Commercial;

use PHPUnit\Framework\TestCase;

class CommercialCustomerProvisioningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Services/Commercial/CommercialCustomerProvisioningService.php'
        );
    }

    public function test_service_exposes_assessment_and_generic_entrypoints(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'function ensureForAssessment(',
            $source
        );

        $this->assertStringContainsString(
            'function ensure(',
            $source
        );
    }

    public function test_assessment_resolves_real_customer_user(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'DiagnosisAccessRequest::query()',
            $source
        );

        $this->assertStringContainsString(
            "'diagnosis_assessment_id'",
            $source
        );

        $this->assertStringContainsString(
            "'user'",
            $source
        );

        $this->assertStringContainsString(
            "'contactRequest'",
            $source
        );
    }

    public function test_reuses_commercial_history_before_user_pivot(): void
    {
        $source = $this->source();

        $history = strpos(
            $source,
            'resolveFromAssessmentCommercialHistory('
        );

        $pivot = strpos(
            $source,
            "DB::table('subscriber_user')"
        );

        $this->assertNotFalse($history);
        $this->assertNotFalse($pivot);
        $this->assertLessThan($pivot, $history);

        $this->assertStringContainsString(
            'DiagnosisDetailedRoadmapOrder::query()',
            $source
        );

        $this->assertStringContainsString(
            'DiagnosisExpandedReportOrder::query()',
            $source
        );
    }

    public function test_multi_subscriber_ambiguity_is_blocked(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            '$activeSubscriberIds->count() > 1',
            $source
        );

        $this->assertStringContainsString(
            'El usuario pertenece a varios Subscribers activos.',
            $source
        );
    }

    public function test_identity_defaults_to_dop_and_dominican_timezone(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "'currency' => 'DOP'",
            $source
        );

        $this->assertStringContainsString(
            "'country_code' => 'DO'",
            $source
        );

        $this->assertStringContainsString(
            "'America/Santo_Domingo'",
            $source
        );
    }

    public function test_service_never_creates_subscription_or_service_item(): void
    {
        $source = $this->source();

        foreach ([
            'Subscription::create(',
            'Subscription::query()->create(',
            'SubscriptionItem::create(',
            'SubscriptionItem::query()->create(',
            'ActivationRequest::',
            'trial_ends_at',
            'activateFromGoLive(',
            'LaudaOneProvisioner',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_company_remains_one_to_one_with_subscriber(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "->where(\n                        'subscriber_id',\n                        \$subscriber->id",
            $source
        );

        $this->assertStringContainsString(
            "'subscriber_id' => \$subscriber->id",
            $source
        );
    }

    public function test_existing_inactive_identity_is_not_silently_reactivated(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'El Subscriber comercial existente está inactivo',
            $source
        );

        $this->assertStringContainsString(
            'La Company comercial existente está inactiva',
            $source
        );
    }
}
