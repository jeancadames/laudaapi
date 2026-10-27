<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiBusinessNeedsCatalog;
use App\Services\Diagnosis\DataBiInformationRequirementDraftGenerator;
use PHPUnit\Framework\TestCase;

final class DataBiInformationRequirementDraftGeneratorTest extends TestCase
{
    public function test_empty_needs_does_not_invent_sources_or_requirements(): void
    {
        $preview = DataBiInformationRequirementDraftGenerator::preview([]);
        self::assertSame(0, $preview['selected_needs_count']);
        self::assertSame([], $preview['requirements']);
        self::assertFalse($preview['can_send_without_review']);
        self::assertFalse($preview['data_inspected']);
    }

    public function test_shared_requirements_are_deduplicated_and_traceable(): void
    {
        $preview = DataBiInformationRequirementDraftGenerator::preview([
            'business_needs' => ['improvements' => [
                ['key' => 'gestion_desempeno'],
                ['key' => 'finanzas_ingresos'],
                ['key' => 'operaciones_recursos'],
            ]],
        ]);
        $requirements = array_column($preview['requirements'], null, 'key');
        self::assertSame(count($requirements), $preview['requirements_count']);
        self::assertCount(1, array_filter($preview['requirements'], fn (array $r): bool => $r['key'] === 'ventas_servicios'));
        self::assertSame(['gestion_desempeno', 'finanzas_ingresos'], $requirements['ventas_servicios']['objective_keys']);
        self::assertSame(['operaciones_recursos'], $requirements['recursos_capacidad']['objective_keys']);
        self::assertSame(['operaciones_recursos'], $requirements['tiempos_operativos']['objective_keys']);
        self::assertNull($requirements['ventas_servicios']['availability']);
        self::assertSame([], $requirements['ventas_servicios']['declared_sources']);
        self::assertFalse($requirements['ventas_servicios']['structure_required']);
        self::assertFalse($preview['tenant_response_received']);
    }

    public function test_unknown_needs_are_reported_not_silently_mapped(): void
    {
        $preview = DataBiInformationRequirementDraftGenerator::preview([
            'business_needs' => ['improvements' => [
                ['key' => 'categoria_desconocida'],
                ['key' => 'gestion_clientes'],
                ['key' => 'gestion_clientes'],
            ]],
        ]);
        self::assertSame(['categoria_desconocida'], $preview['unmapped_need_keys']);
        self::assertSame(1, $preview['selected_needs_count']);
        self::assertCount(2, $preview['requirements']);
        self::assertTrue($preview['requires_lauda_review']);
    }

    public function test_all_catalog_needs_have_rules_and_no_data_claims(): void
    {
        $keys = array_keys(DataBiBusinessNeedsCatalog::improvementMap());
        $preview = DataBiInformationRequirementDraftGenerator::preview([
            'business_needs' => ['improvements' => array_map(
                fn (string $key): array => ['key' => $key],
                $keys
            )],
        ]);
        self::assertCount(15, $keys);
        self::assertSame([], $preview['unmapped_need_keys']);
        self::assertCount(15, $preview['selected_needs']);
        foreach ($preview['requirements'] as $requirement) {
            self::assertNotEmpty($requirement['objective_keys']);
            self::assertNull($requirement['availability']);
            self::assertSame([], $requirement['declared_sources']);
        }
        self::assertSame('preview_only', $preview['mode']);
        self::assertFalse($preview['can_send_without_review']);
    }

    public function test_selection_order_does_not_change_draft(): void
    {
        $one = ['business_needs' => ['improvements' => [
            ['key' => 'gestion_clientes'],
            ['key' => 'finanzas_ingresos'],
        ]]];
        $two = ['business_needs' => ['improvements' => [
            ['key' => 'finanzas_ingresos'],
            ['key' => 'gestion_clientes'],
        ]]];
        self::assertSame(
            DataBiInformationRequirementDraftGenerator::preview($one),
            DataBiInformationRequirementDraftGenerator::preview($two)
        );
    }
}
