<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationRequestExpectedSourcesPlan;
use App\Services\Diagnosis\TransformationImplementationRequestFunctionalProposalEngine;
use Tests\TestCase;

final class TransformationImplementationRequestFunctionalProposalEngineTest extends TestCase
{
    private function snapshot(): array
    {
        $keys = [
            'operaciones_inventarios', 'operaciones_calidad', 'operaciones_recursos',
            'operaciones_proyectos', 'operaciones_procesos', 'gestion_clientes',
            'gestion_desempeno', 'gestion_indicadores', 'gestion_planificacion',
            'gestion_riesgos', 'finanzas_ingresos', 'finanzas_presupuestos',
            'finanzas_cobros', 'finanzas_rentabilidad', 'finanzas_proyecciones',
        ];
        return ['business_needs' => ['improvements' => array_map(
            static fn (string $key): array => ['key' => $key, 'label' => $key],
            $keys
        )]];
    }

    private function deliverables(): array
    {
        $texts = [
            'Maestro, calidad, comportamiento histórico, segmentación e industria de clientes.',
            'Riesgo, oportunidad, concentración y exposición de clientes y segmentos mediante indicadores explicables.',
            'Productos, categorías, existencia, movimientos, costos, precios, rotación, margen, disponibilidad e inventario valorizado.',
            'Historial de ventas por fecha, cliente, producto, categoría, sucursal y vendedor.',
        ];
        return array_map(static fn (string $text): array => [
            'source' => 'professional_capability',
            'phase_id' => 12,
            'capability_key' => 'data_transformation_bi',
            'deliverable' => $text,
        ], $texts);
    }

    private function humanScope(): array
    {
        $ref = TransformationImplementationRequestExpectedSourcesPlan::deliverableRef(
            $this->deliverables()[2]
        );
        $items = array_map(static fn (array $item): array => [
            'key' => $item['key'],
            'status' => $item['key'] === 'operaciones_inventarios' ? 'clarification' : 'pending',
            'rationale' => $item['key'] === 'operaciones_inventarios' ? 'Confirmar gestión y disponibilidad.' : '',
        ], $this->snapshot()['business_needs']['improvements']);
        return [
            'business_needs_assessment' => ['schema_version' => 1, 'items' => $items],
            'expected_sources_plan' => [
                'schema_version' => 1,
                'sources' => [[
                    'key' => 'src_human_inventory',
                    'name' => 'Información de productos, existencias y movimientos de inventario',
                    'source_type' => 'to_define',
                    'origin_hint' => '',
                    'data_domains' => [],
                    'notes' => 'Revisado por LAUDA.',
                ]],
                'links' => [[
                    'need_key' => 'operaciones_inventarios',
                    'source_keys' => ['src_human_inventory'],
                    'deliverable_refs' => [$ref],
                    'notes' => 'Relación humana existente.',
                ]],
            ],
        ];
    }

    public function test_human_inventory_is_preserved_and_three_proposals_are_offered(): void
    {
        $scope = $this->humanScope();
        $original = $scope;
        $preview = TransformationImplementationRequestFunctionalProposalEngine::preview(
            $this->snapshot(), $scope, $this->deliverables()
        );
        $this->assertSame($original, $scope);
        $this->assertSame('preview_only', $preview['mode']);
        $this->assertSame(15, $preview['summary']['total_needs']);
        $this->assertSame(3, $preview['summary']['suggested']);
        $this->assertSame(12, $preview['summary']['not_suggested']);
        $this->assertContains([
            'need_key' => 'operaciones_inventarios',
            'reason' => 'existing_human_link',
        ], $preview['skipped']);
        $this->assertSame(
            ['gestion_clientes', 'gestion_desempeno', 'gestion_riesgos'],
            array_column($preview['suggestions'], 'need_key')
        );
        foreach ($preview['suggestions'] as $suggestion) {
            $this->assertTrue($suggestion['requires_human_review']);
            $this->assertFalse($suggestion['source_verified']);
            $this->assertSame('partial', $suggestion['coverage']);
            $this->assertSame('to_define', $suggestion['source']['source_type']);
        }
    }

    public function test_suggestions_fit_bi12_without_changing_existing_inventory(): void
    {
        $scope = $this->humanScope();
        $preview = TransformationImplementationRequestFunctionalProposalEngine::preview(
            $this->snapshot(), $scope, $this->deliverables()
        );
        foreach ($preview['suggestions'] as $suggestion) {
            if ($suggestion['source'] !== null) {
                $scope['expected_sources_plan']['sources'][] = $suggestion['source'];
            }
            $scope['expected_sources_plan']['links'][] = $suggestion['link'];
        }
        $normalized = TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(), $scope, $this->deliverables()
        );
        $plan = $normalized['expected_sources_plan'];
        $this->assertCount(4, $plan['sources']);
        $this->assertCount(4, $plan['links']);
        $this->assertSame('src_human_inventory', $plan['sources'][0]['key']);
        $this->assertSame('Relación humana existente.', $plan['links'][0]['notes']);
    }

    public function test_preview_is_deterministic_and_does_not_guess_missing_catalog_items(): void
    {
        $snapshot = $this->snapshot();
        $deliverables = $this->deliverables();
        $first = TransformationImplementationRequestFunctionalProposalEngine::preview(
            $snapshot, [], $deliverables
        );
        $this->assertSame(4, $first['summary']['suggested']);
        $this->assertSame($first, TransformationImplementationRequestFunctionalProposalEngine::preview(
            $snapshot, [], $deliverables
        ));
        $missing = TransformationImplementationRequestFunctionalProposalEngine::preview(
            $snapshot, [], array_slice($deliverables, 1)
        );
        $this->assertContains([
            'need_key' => 'gestion_clientes', 'reason' => 'deliverable_missing',
        ], $missing['skipped']);
    }

    public function test_existing_requirement_can_be_reused_without_duplication(): void
    {
        $scope = $this->humanScope();
        $scope['expected_sources_plan']['sources'][] = [
            'key' => 'src_human_clients',
            'name' => 'Información maestra e histórica de clientes y segmentos',
            'source_type' => 'to_define',
            'origin_hint' => '',
            'data_domains' => [],
            'notes' => 'Registrado manualmente.',
        ];
        $preview = TransformationImplementationRequestFunctionalProposalEngine::preview(
            $this->snapshot(), $scope, $this->deliverables()
        );
        $clients = $preview['suggestions'][0];
        $this->assertSame('reuse_requirement', $clients['action']);
        $this->assertNull($clients['source']);
        $this->assertSame(['src_human_clients'], $clients['link']['source_keys']);
    }
}
