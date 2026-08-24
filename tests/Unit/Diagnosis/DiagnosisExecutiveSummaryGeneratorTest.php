<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DiagnosisExecutiveSummaryGenerator;
use Tests\TestCase;

class DiagnosisExecutiveSummaryGeneratorTest extends TestCase
{
    private DiagnosisExecutiveSummaryGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = app(
            DiagnosisExecutiveSummaryGenerator::class
        );
    }

    public function test_generates_deterministic_logistics_summary(): void
    {
        $result = $this->sampleResult();

        $profile = [
            'business_activity_type' => 'mixed',
            'business_sector' => 'logistics',
            'customer_market' => 'both',
            'sales_channels' => [
                'salesforce',
                'quotations',
            ],
            'logistics_operation_types' => [
                'warehousing',
                'distribution',
                'last_mile',
            ],
            'business_activity_description' =>
                'Operador logístico de almacenamiento, distribución y última milla.',
        ];

        $first = $this->generator->generate(
            $result,
            $profile
        );

        $second = $this->generator->generate(
            $result,
            $profile
        );

        $this->assertSame(
            $first,
            $second
        );

        $this->assertStringContainsString(
            'Logística',
            $first['summary']
        );

        $this->assertStringContainsString(
            '11/100',
            $first['summary']
        );

        $this->assertSame(
            'managed',
            $first['modality']
        );

        $this->assertNotEmpty(
            $first['priorities']
        );

        $this->assertStringContainsString(
            'flujo logístico',
            implode(
                ' ',
                $first['priorities']
            )
        );
    }

    public function test_services_receive_service_cycle_priority(): void
    {
        $profile = [
            'business_activity_type' => 'services',
            'business_sector' =>
                'professional_services',
            'customer_market' => 'b2b',
            'business_activity_description' =>
                'Servicios profesionales recurrentes para empresas.',
        ];

        $generated = $this->generator->generate(
            $this->sampleResult(),
            $profile
        );

        $all = implode(
            ' ',
            $generated['priorities']
        );

        $this->assertStringContainsString(
            'ciclo de servicios',
            $all
        );

        $this->assertStringContainsString(
            'cotización',
            $all
        );
    }

    public function test_goods_receive_goods_cycle_priority(): void
    {
        $profile = [
            'business_activity_type' => 'goods',
            'business_sector' => 'distribution',
            'customer_market' => 'both',
            'business_activity_description' =>
                'Distribución de mercancías a empresas y clientes finales.',
        ];

        $generated = $this->generator->generate(
            $this->sampleResult(),
            $profile
        );

        $all = implode(
            ' ',
            $generated['priorities']
        );

        $this->assertStringContainsString(
            'ciclo de bienes',
            $all
        );

        $this->assertStringContainsString(
            'inventario',
            $all
        );
    }

    public function test_priorities_are_between_one_and_five(): void
    {
        $generated = $this->generator->generate(
            $this->sampleResult(),
            []
        );

        $this->assertGreaterThanOrEqual(
            1,
            count($generated['priorities'])
        );

        $this->assertLessThanOrEqual(
            5,
            count($generated['priorities'])
        );

        $this->assertGreaterThan(
            40,
            mb_strlen($generated['summary'])
        );
    }

    private function sampleResult(): array
    {
        return [
            'maturity_score' => 11,
            'maturity_level' =>
                'Empresa tradicional',
            'capacity_score' => 33,
            'urgency_score' => 35,
            'urgency_level' => 'Media',
            'recommended_modality' => 'managed',
            'recommended_modality_label' =>
                'LAUDA 360 Gestionado',
            'dimension_scores' => [
                'strategy_leadership' => 0,
                'people_culture' => 15,
                'presence_experience' => 15,
                'commercial_clients' => 5,
                'processes_operations' => 5,
                'technology_integration' => 15,
                'data_intelligence' => 20,
                'governance_security_control' => 30,
            ],
        ];
    }
}
