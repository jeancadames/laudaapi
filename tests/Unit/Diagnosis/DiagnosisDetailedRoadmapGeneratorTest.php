<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;
use App\Services\Diagnosis\DiagnosisDetailedRoadmapGenerator;
use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapGeneratorTest extends TestCase
{
    private function assessment(
        string $activity = 'goods',
        string $sector = 'commerce'
    ): DiagnosisAssessment {
        $assessment = new DiagnosisAssessment();

        $assessment->id = 10;
        $assessment->organization_name = 'Empresa Demo';
        $assessment->methodology_version = 'lauda360-v1';
        $assessment->status = 'reviewed';
        /*
         * Unit test puro: no asignar Carbon a un cast datetime de
         * Eloquent porque PHPUnit\Framework\TestCase no arranca el
         * resolver de conexión. El generador admite published_at null.
         */
        $assessment->published_at = null;
        $assessment->maturity_score = 43;
        $assessment->capacity_score = 52;
        $assessment->urgency_score = 71;
        $assessment->dimension_scores = [
            'strategy' => 25,
            'people' => 50,
            'presence' => 55,
            'commercial' => 30,
            'operations' => 20,
            'technology' => 45,
            'data' => 35,
            'governance' => 60,
        ];
        $assessment->review_summary =
            'La empresa requiere ordenar su transformación.';
        $assessment->review_priorities = [
            'Estandarizar procesos.',
            'Mejorar trazabilidad.',
        ];
        $assessment->final_modality = 'assisted';
        $assessment->final_modality_label = 'LAUDA 360 Asistido';
        $assessment->business_activity_type = $activity;
        $assessment->business_sector = $sector;
        $assessment->customer_market = 'both';
        $assessment->sales_channels = ['physical', 'salesforce'];
        $assessment->business_activity_description =
            'Empresa demo utilizada para validar el Roadmap Detallado.';

        return $assessment;
    }

    private function report(): DiagnosisExpandedReport
    {
        $report = new DiagnosisExpandedReport();

        $report->id = 7;
        $report->version = 2;
        $report->status = DiagnosisExpandedReport::STATUS_PUBLISHED;
        /*
         * Igual que el assessment: el timestamp no es requisito del
         * generador y no debe convertir este unit test en integración.
         */
        $report->published_at = null;
        $report->sections = [
            'executive_summary' => [
                'body' =>
                    'El Informe Ampliado identifica brechas operativas y comerciales.',
            ],
        ];

        return $report;
    }

    public function test_generates_four_phases_and_executable_initiatives(): void
    {
        $result = (new DiagnosisDetailedRoadmapGenerator())
            ->generate($this->assessment(), $this->report());

        $roadmap = $result['roadmap'];

        $this->assertCount(4, $roadmap['phases']);
        $this->assertGreaterThanOrEqual(
            9,
            count($roadmap['initiatives'])
        );

        $first = $roadmap['initiatives'][0];

        foreach ([
            'id',
            'priority',
            'title',
            'objective',
            'actions',
            'owner_role',
            'dependencies',
            'impact',
            'effort',
            'success_metrics',
            'phase',
            'horizon',
            'sequence',
        ] as $field) {
            $this->assertArrayHasKey($field, $first);
        }
    }

    public function test_logistics_contains_end_to_end_flow(): void
    {
        $result = (new DiagnosisDetailedRoadmapGenerator())
            ->generate(
                $this->assessment('services', 'logistics'),
                $this->report()
            );

        $business = collect($result['roadmap']['initiatives'])
            ->firstWhere('id', 'BUS-01');

        $this->assertNotNull($business);

        $text = mb_strtolower(
            implode(' ', $business['actions'])
        );

        foreach ([
            'recepción',
            'picking',
            'entrega/pod',
            'facturación',
        ] as $token) {
            $this->assertStringContainsString($token, $text);
        }
    }

    public function test_source_snapshot_freezes_report_version(): void
    {
        $result = (new DiagnosisDetailedRoadmapGenerator())
            ->generate($this->assessment(), $this->report());

        $source = $result['source_snapshot']['expanded_report'];

        $this->assertSame(7, $source['id']);
        $this->assertSame(2, $source['version']);
        $this->assertSame('published', $source['status']);
    }

    public function test_execution_is_separate_from_roadmap_deliverable(): void
    {
        $result = (new DiagnosisDetailedRoadmapGenerator())
            ->generate($this->assessment(), $this->report());

        $body = $result['roadmap']['scope_note']['body'];

        $this->assertStringContainsString('se cotizan', $body);
        $this->assertStringContainsString('por separado', $body);
    }
}
