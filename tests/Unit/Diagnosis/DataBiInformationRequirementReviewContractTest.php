<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationRequirementDraftGenerator;
use App\Services\Diagnosis\DataBiInformationRequirementReviewContract;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DataBiInformationRequirementReviewContractTest extends TestCase
{
    private function generated(): array
    {
        return DataBiInformationRequirementDraftGenerator::preview([
            'business_needs' => ['improvements' => ['gestion_clientes','gestion_desempeno','finanzas_ingresos']],
        ]);
    }

    private function form(array $generated): array
    {
        return ['requirements' => array_map(static fn (array $item): array => [
            'key' => $item['key'],
            'included' => true,
            'label' => $item['label'],
            'guidance' => $item['guidance'],
        ], $generated['requirements'])];
    }

    public function test_works_with_deduplicated_multiobjective_categories(): void
    {
        $g = $this->generated();
        $data = DataBiInformationRequirementReviewContract::normalize($g, $this->form($g));
        self::assertSame('information_source_declaration', $data['purpose']);
        self::assertSame(false, $data['data_inspected']);
        self::assertSame(false, $data['tenant_response_received']);
        self::assertCount(count($g['requirements']), $data['requirements']);
        self::assertSame(64, strlen($data['content_sha256']));
        $sales = array_values(array_filter($data['requirements'],static fn ($r) => $r['key'] === 'ventas_servicios'))[0];
        self::assertContains('gestion_clientes', $sales['objective_keys']);
        self::assertContains('gestion_desempeno', $sales['objective_keys']);
        self::assertContains('finanzas_ingresos', $sales['objective_keys']);
    }

    public function test_exclusion_preserves_source_objectives_and_not_included_items(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        $excluded = $form['requirements'][0]['key'];
        $form['requirements'][0]['included'] = false;
        $data = DataBiInformationRequirementReviewContract::normalize($g, $form);
        self::assertCount(count($g['requirements']) - 1, $data['requirements']);
        self::assertNotContains($excluded, array_column($data['requirements'], 'key'));
    }

    public function test_rejects_tampered_objective_links(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        $form['requirements'][0]['objective_keys'] = ['finanzas_presupuestos'];
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequirementReviewContract::normalize($g, $form);
    }

    public function test_rejects_unknown_requirement_key(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        $form['requirements'][0]['key'] = 'unsupported';
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequirementReviewContract::normalize($g, $form);
    }

    public function test_rejects_duplicate_request_key(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        $form['requirements'][1]['key'] = $form['requirements'][0]['key'];
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequirementReviewContract::normalize($g, $form);
    }

    public function test_rejects_empty_submission(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        foreach ($form['requirements'] as &$item) $item['included'] = false;
        unset($item);
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequirementReviewContract::normalize($g, $form);
    }

    public function test_rejects_duplicate_names_after_human_edits(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        $form['requirements'][1]['label'] = $form['requirements'][0]['label'];
        $this->expectException(InvalidArgumentException::class);
        DataBiInformationRequirementReviewContract::normalize($g, $form);
    }

    public function test_hash_is_stable_for_same_review(): void
    {
        $g = $this->generated();
        $form = $this->form($g);
        $a = DataBiInformationRequirementReviewContract::normalize($g, $form);
        $b = DataBiInformationRequirementReviewContract::normalize($g, $form);
        self::assertSame($a['content_sha256'], $b['content_sha256']);
    }
}
