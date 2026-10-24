<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiDiagnosticDraftComposer;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class DataBiDiagnosticDraftComposerTest extends TestCase
{
    private function base(): array
    {
        return [
            'implementation_scope' => [
                'scope_mode' => 'single_capability',
                'request_id' => 5,
                'phase_id' => 4,
                'phase_name' => 'Fase 3',
                'phase_sequence' => 3,
                'phase_capability_id' => 8,
                'capability_key' => 'data_transformation_bi',
                'definition_scope_locked_to_request' => true,
                'business_needs_assessment' => ['items' => ['persist']],
                'phases' => [[
                    'id' => 4,
                    'objective' => 'Implantar sistemas',
                    'deliverables' => ['Construir un data warehouse'],
                    'dependencies' => ['Contratar implementación'],
                    'capabilities' => [[
                        'capability_key' => 'data_transformation_bi',
                        'purpose' => 'Ejecutar transformaciones',
                        'scope_items' => ['Crear dashboards definitivos'],
                    ]],
                ]],
            ],
            'deliverables' => [[
                'source' => 'professional_capability',
                'deliverable' => 'Perfilado, limpieza, normalización y preparación productiva',
            ]],
            'dependencies' => [[
                'source' => 'professional_capability',
                'dependency' => 'Contrato comercial',
            ]],
            'responsibility_model' => ['assignments' => [
                [
                    'initiative_id' => 'data_transformation_bi:client_source_delivery',
                    'initiative_title' => 'Empresa · identificación y entrega de fuentes',
                    'responsible_party' => 'client',
                    'confirmation_status' => 'pending',
                ],
                [
                    'initiative_id' => 'data_transformation_bi:lauda_transformation',
                    'initiative_title' => 'LAUDA · transformación y preparación analítica',
                    'responsible_party' => 'lauda',
                    'confirmation_status' => 'pending',
                ],
            ]],
            'readiness' => ['human_review_required' => true, 'ready_for_execution' => false],
        ];
    }

    private function source(int $count = 9): array
    {
        $areas = ['operaciones', 'gestion', 'finanzas'];
        $improvements = [];
        for ($i = 0; $i < $count; $i++) {
            $improvements[] = [
                'key' => 'need_'.$i,
                'group' => $areas[$i % 3],
                'label' => 'Necesidad '.$i,
            ];
        }
        return [
            'request' => ['id' => 5],
            'diagnostic_business_needs' => [
                'improvements' => $improvements,
                'need' => 'Problema declarado por el cliente',
                'expected_result' => 'Diagnóstico y recomendaciones',
                'additional_info' => 'Transformación empresarial futura',
            ],
        ];
    }

    public function test_preserves_all_needs_and_replaces_implementation_deliverables(): void
    {
        $actual = (new DataBiDiagnosticDraftComposer())->enrich($this->base(), $this->source());
        $scope = $actual['implementation_scope'];
        $draft = $scope['diagnostic_draft'];
        self::assertSame(9, count($draft['proposed_evaluations']));
        foreach (['operaciones', 'gestion', 'finanzas'] as $area) {
            self::assertCount(3, $draft['needs_by_area'][$area]);
        }
        self::assertSame('Problema declarado por el cliente', $draft['identified_problem']);
        self::assertSame('Diagnóstico y recomendaciones', $draft['expected_result']);
        self::assertSame('basic_diagnosis_without_charge', $draft['service_phase']);
        self::assertTrue($draft['requires_human_review']);
        self::assertFalse($draft['sources_verified']);
        self::assertSame(5, $draft['source_request_id']);
        self::assertSame('single_capability', $scope['scope_mode']);
        self::assertSame(['items' => ['persist']], $scope['business_needs_assessment']);
        self::assertTrue($scope['definition_scope_locked_to_request']);
        self::assertCount(2, $actual['responsibility_model']['assignments']);
        self::assertSame('Empresa · identificación y entrega autorizada de fuentes', $actual['responsibility_model']['assignments'][0]['initiative_title']);
        self::assertSame('LAUDA · evaluación diagnóstica básica y recomendaciones', $actual['responsibility_model']['assignments'][1]['initiative_title']);
        self::assertSame('client', $actual['responsibility_model']['assignments'][0]['responsible_party']);
        self::assertSame('lauda', $actual['responsibility_model']['assignments'][1]['responsible_party']);
        self::assertSame('pending', $actual['responsibility_model']['assignments'][0]['confirmation_status']);
        self::assertSame('pending', $actual['responsibility_model']['assignments'][1]['confirmation_status']);
        self::assertSame($this->base()['readiness'], $actual['readiness']);
        self::assertCount(4, $actual['deliverables']);
        foreach ($actual['deliverables'] as $item) {
            self::assertSame('basic_diagnostic_scope', $item['source']);
            self::assertSame(4, $item['phase_id']);
            self::assertSame(8, $item['phase_capability_id']);
            self::assertSame('data_transformation_bi', $item['capability_key']);
        }
        $text = json_encode([
            'scope' => $scope['phases'],
            'includes' => $scope['includes'],
            'deliverables' => $actual['deliverables'],
            'dependencies' => $actual['dependencies'],
            'responsibilities' => $actual['responsibility_model'],
        ], JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('data warehouse', $text);
        self::assertStringNotContainsString('Perfilado, limpieza, normalización', $text);
        self::assertStringNotContainsString('Contrato comercial', $text);
        self::assertStringNotContainsString('LAUDA · transformación y preparación analítica', $text);
        self::assertStringNotContainsString('Crear dashboards definitivos', $text);
    }

    public function test_missing_needs_does_not_change_legacy_generated_content(): void
    {
        $old = $this->base();
        self::assertSame($old, (new DataBiDiagnosticDraftComposer())->enrich($old, ['request' => ['id' => 5]]));
    }

    public function test_invalid_or_duplicate_needs_are_not_silently_dropped(): void
    {
        $source = $this->source(2);
        $source['diagnostic_business_needs']['improvements'][1]['key'] = 'need_0';
        $this->expectException(UnexpectedValueException::class);
        (new DataBiDiagnosticDraftComposer())->enrich($this->base(), $source);
    }
}
