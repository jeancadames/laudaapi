<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\DiagnosisExpandedReportGenerator;
use App\Services\Diagnosis\DiagnosisExpandedReportService;
use Tests\TestCase;

class DiagnosisExpandedReportGeneratorTest extends TestCase
{
    public function test_generates_contextualized_logistics_report(): void
    {
        $assessment = new DiagnosisAssessment([
            'organization_name' =>
                'Logística Demo SRL',
            'methodology_version' => '1.0',
            'status' => 'reviewed',
            'maturity_score' => 42,
            'capacity_score' => 38,
            'urgency_score' => 72,
            'dimension_scores' => [
                'strategy' => 20,
                'people' => 35,
                'presence' => 55,
                'commercial' => 30,
                'operations' => 25,
                'technology' => 45,
                'data' => 60,
                'governance' => 40,
            ],
            'maturity_level' =>
                'Empresa Digital',
            'urgency_level' => 'Alta',
            'review_summary' =>
                'La empresa necesita conectar sus procesos críticos.',
            'review_priorities' => [
                'Estandarizar la operación.',
                'Conectar información comercial y operativa.',
            ],
            'business_activity_type' =>
                'services',
            'business_sector' =>
                'logistics',
            'customer_market' => 'b2b',
            'sales_channels' => [
                'contracts',
                'quotations',
            ],
            'logistics_operation_types' => [
                'warehousing',
                'last_mile',
            ],
            'business_activity_description' =>
                'Operador logístico B2B con almacenamiento y distribución de última milla.',
        ]);

        $assessment->published_at = now();

        $result = app(
            DiagnosisExpandedReportGenerator::class
        )->generate($assessment);

        $this->assertArrayHasKey(
            'source_snapshot',
            $result
        );

        $this->assertArrayHasKey(
            'sections',
            $result
        );

        $sections = $result['sections'];

        foreach ([
            'executive_summary',
            'business_context',
            'maturity_interpretation',
            'dimension_analysis',
            'critical_gaps',
            'relative_strengths',
            'business_implications',
            'recommended_focus',
            'execution_capacity',
            'next_step_note',
        ] as $key) {
            $this->assertArrayHasKey(
                $key,
                $sections
            );
        }

        $implications = implode(
            ' ',
            $sections[
                'business_implications'
            ]['items']
        );

        $this->assertStringContainsString(
            'entrega/POD',
            $implications
        );

        $this->assertSame(
            'Logística',
            $sections[
                'business_context'
            ]['sector']
        );

        $this->assertSame(
            38,
            $sections[
                'execution_capacity'
            ]['capacity_score']
        );

        $this->assertArrayNotHasKey(
            'recommended_modality',
            $sections['execution_capacity']
        );

        $this->assertArrayNotHasKey(
            'recommended_modality_label',
            $sections['execution_capacity']
        );

        $this->assertArrayNotHasKey(
            'modality',
            $result['source_snapshot']['official_result']
        );

        $this->assertArrayNotHasKey(
            'modality_label',
            $result['source_snapshot']['official_result']
        );
    }

    public function test_commercial_snapshot_uses_agreed_price(): void
    {
        $service = app(
            DiagnosisExpandedReportService::class
        );

        $snapshot =
            $service->commercialSnapshot();

        $this->assertSame(
            'DOP',
            $snapshot['currency']
        );

        $this->assertSame(
            29900.0,
            $snapshot['subtotal']
        );

        $this->assertSame(
            18.0,
            $snapshot['tax_rate']
        );

        $this->assertSame(
            5382.0,
            $snapshot['tax_amount']
        );

        $this->assertSame(
            35282.0,
            $snapshot['total']
        );
    }

    public function test_report_is_distinct_from_detailed_roadmap(): void
    {
        $assessment = new DiagnosisAssessment([
            'organization_name' =>
                'Servicios Demo SRL',
            'methodology_version' => '1.0',
            'status' => 'reviewed',
            'maturity_score' => 55,
            'capacity_score' => 60,
            'urgency_score' => 40,
            'dimension_scores' => [
                'strategy' => 50,
                'people' => 55,
                'presence' => 60,
                'commercial' => 45,
                'operations' => 50,
                'technology' => 65,
                'data' => 70,
                'governance' => 55,
            ],
            'business_activity_type' =>
                'services',
            'business_sector' =>
                'professional_services',
            'customer_market' => 'both',
            'sales_channels' => [
                'quotations',
            ],
            'business_activity_description' =>
                'Empresa de servicios profesionales por proyectos y cotizaciones.',
        ]);

        $assessment->published_at = now();

        $sections = app(
            DiagnosisExpandedReportGenerator::class
        )->generate($assessment)['sections'];

        $this->assertStringContainsString(
            'No sustituye el Roadmap Detallado',
            $sections[
                'next_step_note'
            ]['body']
        );

        $implications = implode(
            ' ',
            $sections[
                'business_implications'
            ]['items']
        );

        $this->assertStringContainsString(
            'cotización',
            $implications
        );
    }
}
