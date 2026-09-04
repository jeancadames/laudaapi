<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapGenerator;
use App\Services\Diagnosis\TransformationProfessionalCapabilityCatalog;
use App\Services\Diagnosis\TransformationServiceCapabilityCatalog;
use PHPUnit\Framework\TestCase;

class DataTransformationBiFoundationContractTest extends TestCase
{
    private function assessment(float $dataScore): DiagnosisAssessment
    {
        $assessment = new DiagnosisAssessment();

        $assessment->id = 1301;
        $assessment->organization_name = 'S13 Contract Test';
        $assessment->methodology_version = 's13-contract';
        $assessment->status = 'reviewed';

        /*
         * No necesitamos persistencia ni timestamps reales.
         */
        $assessment->published_at = null;

        $assessment->maturity_score = 60;
        $assessment->capacity_score = 60;
        $assessment->urgency_score = 60;

        $assessment->dimension_scores = [
            'strategy' => 70,
            'people' => 70,
            'presence' => 70,
            'commercial' => 70,
            'operations' => 70,
            'technology' => 70,
            'data' => $dataScore,
            'governance' => 70,
        ];

        $assessment->review_summary =
            'Contrato unitario S13.';

        $assessment->review_priorities = [];

        $assessment->final_modality = 'assisted';
        $assessment->final_modality_label =
            'LAUDA 360 Asistido';

        $assessment->business_activity_type = 'goods';
        $assessment->business_sector = 'commerce';
        $assessment->customer_market = 'both';

        $assessment->sales_channels = [];
        $assessment->logistics_operation_types = [];

        $assessment->business_activity_description =
            'Empresa utilizada exclusivamente para contrato S13.';

        return $assessment;
    }

    private function report(): DiagnosisExpandedReport
    {
        $report = new DiagnosisExpandedReport();

        $report->id = 1302;
        $report->version = 1;
        $report->status =
            DiagnosisExpandedReport::STATUS_PUBLISHED;

        $report->published_at = null;
        $report->sections = [];

        return $report;
    }

    public function test_bi_is_professional_implementation_capability(): void
    {
        $bi =
            TransformationProfessionalCapabilityCatalog::get(
                'data_transformation_bi'
            );

        $this->assertNotNull($bi);

        $this->assertSame(
            'data_transformation_bi',
            $bi['capability_key']
        );

        $this->assertSame(
            'Transformación e Inteligencia de Datos para BI',
            $bi['title']
        );

        $this->assertSame(
            'professional_service',
            $bi['kind']
        );

        $this->assertSame(
            'data',
            $bi['category']
        );

        $this->assertNull(
            $bi['service_key']
        );

        $this->assertFalse(
            $bi['subscription_candidate']
        );

        $this->assertTrue(
            $bi['requires_lauda_review']
        );

        $this->assertSame(
            ['DAT-01'],
            $bi['linked_initiative_keys']
        );

        $this->assertSame(
            'implementation_plan_estimate_required',
            $bi['commercial_readiness']
        );

        $this->assertSame(
            'implementation_only',
            $bi['activation_policy']
        );
    }

    public function test_bi_is_not_a_recurring_service_capability(): void
    {
        $this->assertNotContains(
            'data_transformation_bi',
            TransformationServiceCapabilityCatalog::keys()
        );
    }

    public function test_data_score_70_recommends_bi_explainably(): void
    {
        $generated =
            (new DiagnosisDetailedRoadmapGenerator())
                ->generate(
                    $this->assessment(70),
                    $this->report()
                );

        $bi =
            $generated['roadmap']
                ['transformation_capabilities']
                ['data_transformation_bi'];

        $this->assertTrue(
            $bi['recommended']
        );

        $this->assertSame(
            70.0,
            (float) $bi['data_dimension_score']
        );

        $this->assertSame(
            'medium',
            $bi['data_priority']
        );

        $this->assertSame(
            'implementation_only',
            $bi['activation_policy']
        );

        $this->assertFalse(
            $bi['automatic_price_changes']
        );

        $this->assertStringContainsString(
            '70/100',
            $bi['recommendation_basis']
        );
    }

    public function test_data_score_above_80_does_not_force_recommendation(): void
    {
        $generated =
            (new DiagnosisDetailedRoadmapGenerator())
                ->generate(
                    $this->assessment(90),
                    $this->report()
                );

        $bi =
            $generated['roadmap']
                ['transformation_capabilities']
                ['data_transformation_bi'];

        $this->assertFalse(
            $bi['recommended']
        );

        $this->assertSame(
            'sustain',
            $bi['data_priority']
        );
    }

    public function test_analytical_model_contains_foundational_entities(): void
    {
        $generated =
            (new DiagnosisDetailedRoadmapGenerator())
                ->generate(
                    $this->assessment(50),
                    $this->report()
                );

        $model =
            $generated['roadmap']
                ['transformation_capabilities']
                ['data_transformation_bi']
                ['target_analytical_model'];

        $this->assertContains(
            'DimCustomerSegment',
            $model['dimensions']
        );

        $this->assertContains(
            'DimRawMaterial',
            $model['dimensions']
        );

        $this->assertContains(
            'BridgeProductRawMaterial',
            $model['bridges']
        );

        $this->assertContains(
            'BridgeSupplierRawMaterial',
            $model['bridges']
        );

        $this->assertContains(
            'FactCommodityMarketPrice',
            $model['facts']
        );

        $this->assertContains(
            'FactCustomerRisk',
            $model['facts']
        );

        $this->assertContains(
            'FactSupplierRisk',
            $model['facts']
        );

        $this->assertContains(
            'FactSupplierOpportunity',
            $model['facts']
        );
    }

    public function test_bi_cannot_use_free_professional_activation_flow(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        $this->assertStringContainsString(
            "activation_policy",
            $source
        );

        $this->assertStringContainsString(
            "implementation_only",
            $source
        );
    }

    public function test_roadmap_publish_requires_bi_structure(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'DiagnosisDetailedRoadmapService.php'
        );

        $this->assertStringContainsString(
            'transformation_capabilities.'
            .'data_transformation_bi.title',
            $source
        );
    }

    public function test_bi_ui_is_informational_not_free_activation(): void
    {
        $root = dirname(__DIR__, 3);

        $component = file_get_contents(
            $root
            .'/resources/js/components/diagnosis/'
            .'DetailedRoadmapTransformationCapabilities.vue'
        );

        foreach ([
            'capabilities.data_transformation_bi',
            'Implementación',
            'Recomendado',
            'Para considerar',
            'No ejecuta ETL',
            'warehouse',
            'cambios automáticos de precio',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $component
            );
        }

        foreach ([
            'data_transformation_bi.activate',
            'activateDataTransformationBi',
            'declineDataTransformationBi',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $component
            );
        }
    }

    public function test_s13_foundation_does_not_create_commercial_objects(): void
    {
        $root = dirname(__DIR__, 3);

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'TransformationProfessionalCapabilityCatalog.php'
            )
            ."\n"
            .file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DiagnosisDetailedRoadmapGenerator.php'
            );

        foreach ([
            'Service::create',
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
            'SubscriptionItem::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
