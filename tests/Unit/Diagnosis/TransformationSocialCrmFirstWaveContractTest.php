<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationServiceCapabilityCatalog;
use PHPUnit\Framework\TestCase;

class TransformationSocialCrmFirstWaveContractTest extends TestCase
{
    public function test_social_and_crm_are_independent_capabilities(): void
    {
        $social = TransformationServiceCapabilityCatalog::get('social');
        $crm = TransformationServiceCapabilityCatalog::get('crm');
        $presence = TransformationServiceCapabilityCatalog::get(
            'digital_presence'
        );

        $this->assertNotNull($social);
        $this->assertNotNull($crm);
        $this->assertNotNull($presence);

        $this->assertSame('Social', $social['title']);
        $this->assertSame('social', $social['service_key']);
        $this->assertSame('CRM', $crm['title']);
        $this->assertSame('erp_crm', $crm['service_key']);

        $this->assertNotSame(
            $social['service_key'],
            $presence['service_key']
        );
    }

    public function test_social_catalog_does_not_create_service_or_subscription(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/TransformationServiceCapabilityCatalog.php'
        );

        foreach ([
            'Service::create',
            'Subscription::create',
            'SubscriptionItem::create',
            'TransformationImplementationCapabilityServiceMapping::create',
            'DB::table(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_roadmap_snapshots_include_capability_catalog(): void
    {
        $generator = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php'
        );

        $this->assertStringContainsString(
            "'service_capabilities' =>",
            $generator
        );

        $this->assertStringContainsString(
            'TransformationServiceCapabilityCatalog::all()',
            $generator
        );
    }
}
