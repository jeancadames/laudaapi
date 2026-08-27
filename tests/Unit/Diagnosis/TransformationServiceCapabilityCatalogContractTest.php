<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationServiceCapabilityCatalog;
use PHPUnit\Framework\TestCase;

class TransformationServiceCapabilityCatalogContractTest extends TestCase
{
    public function test_initial_catalog_contains_explicit_service_capabilities(): void
    {
        $this->assertSame(
            [
                'digital_presence',
                'social',
                'crm',
                'ecommerce_b2c',
                'ecommerce_b2b',
                'electronic_billing',
                'fiscal_compliance',
            ],
            TransformationServiceCapabilityCatalog::keys()
        );
    }

    public function test_crm_maps_conceptually_to_existing_service_key_without_service_id(): void
    {
        $crm = TransformationServiceCapabilityCatalog::get('crm');

        $this->assertNotNull($crm);
        $this->assertSame('erp_crm', $crm['service_key']);
        $this->assertContains('COM-01', $crm['linked_initiative_keys']);
        $this->assertTrue($crm['subscription_candidate']);
        $this->assertFalse($crm['recommended']);
        $this->assertTrue($crm['requires_lauda_review']);
        $this->assertArrayNotHasKey('service_id', $crm);
    }

    public function test_digital_presence_is_first_class_service_without_fake_launch_app(): void
    {
        $presence = TransformationServiceCapabilityCatalog::get(
            'digital_presence'
        );

        $this->assertNotNull($presence);
        $this->assertSame(
            'digital_presence',
            $presence['service_key']
        );
        $this->assertSame(
            'catalog_and_pricing_validation_required',
            $presence['commercial_readiness']
        );

        foreach ([
            'Community management recurrente.',
            'Creación recurrente de contenido.',
            'Diseño recurrente de piezas.',
            'Gestión de campañas y compra de medios.',
        ] as $excluded) {
            $this->assertContains($excluded, $presence['excludes']);
        }
    }

    public function test_ecommerce_and_fiscal_capabilities_reference_known_catalog_keys(): void
    {
        $this->assertSame(
            'laudaone_b2c',
            TransformationServiceCapabilityCatalog::get(
                'ecommerce_b2c'
            )['service_key']
        );

        $this->assertSame(
            'laudaone_b2b',
            TransformationServiceCapabilityCatalog::get(
                'ecommerce_b2b'
            )['service_key']
        );

        $this->assertSame(
            'api_facturacion_electronica',
            TransformationServiceCapabilityCatalog::get(
                'electronic_billing'
            )['service_key']
        );

        $this->assertSame(
            'cumplimiento_fiscal',
            TransformationServiceCapabilityCatalog::get(
                'fiscal_compliance'
            )['service_key']
        );
    }

    public function test_structural_transformation_capabilities_are_not_subscription_services(): void
    {
        $keys = TransformationServiceCapabilityCatalog::keys();

        $this->assertNotContains('procedures_guide', $keys);
        $this->assertNotContains('branding_identity', $keys);
    }

    public function test_generator_embeds_service_capabilities_in_published_roadmap_payload(): void
    {
        $root = dirname(__DIR__, 3);

        $generator = file_get_contents(
            $root
            .'/app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php'
        );

        $service = file_get_contents(
            $root
            .'/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        $this->assertStringContainsString(
            "'service_capabilities' =>",
            $generator
        );

        $this->assertStringContainsString(
            'TransformationServiceCapabilityCatalog::all()',
            $generator
        );

        $this->assertStringContainsString(
            "'service_capabilities',",
            $service
        );
    }

    public function test_catalog_does_not_create_services_mappings_or_subscriptions(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/app/Services/Diagnosis/TransformationServiceCapabilityCatalog.php'
        );

        foreach ([
            'Service::create',
            'Service::query()->create',
            'TransformationImplementationCapabilityServiceMapping::create',
            'Subscription::create',
            'SubscriptionItem::create',
            'DB::table(',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_social_is_a_distinct_capability_from_digital_presence(): void
    {
        $social = TransformationServiceCapabilityCatalog::get('social');
        $presence = TransformationServiceCapabilityCatalog::get(
            'digital_presence'
        );
        $crm = TransformationServiceCapabilityCatalog::get('crm');

        $this->assertNotNull($social);
        $this->assertNotNull($presence);
        $this->assertNotNull($crm);

        $this->assertSame('Social', $social['title']);
        $this->assertSame('social', $social['service_key']);
        $this->assertSame('erp_crm', $crm['service_key']);

        $this->assertNotSame(
            $social['service_key'],
            $presence['service_key']
        );

        $this->assertTrue($social['subscription_candidate']);
        $this->assertTrue($social['requires_lauda_review']);
    }

}
