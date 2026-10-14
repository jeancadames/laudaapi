<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiUx2R1SafeNavigationTest extends TestCase
{
    private function vueSourceText(): string
    {
        $path = dirname(__DIR__, 3)
            . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue';
        $source = file_get_contents($path);
        self::assertIsString($source);
        return $source;
    }

    public function test_navigation_uses_existing_save_handler_and_anchors(): void
    {
        $source = $this->vueSourceText();
        self::assertStringContainsString('BI13_UX2_R1_SAFE_NAV', $source);
        foreach (['needs', 'proposals', 'requirements', 'links', 'review'] as $section) {
            self::assertStringContainsString('href="#bi-ux2-' . $section . '"', $source);
            self::assertSame(1, substr_count($source, 'id="bi-ux2-' . $section . '"'));
        }
        self::assertSame(2, substr_count($source, '@click="saveImplementationDefinitionHumanReview"'));
        self::assertStringContainsString('humanReviewForm.processing', $source);
    }

    public function test_need_codes_are_complete_and_not_saved_as_identifiers(): void
    {
        $source = $this->vueSourceText();
        foreach ([
            'operaciones_inventarios' => 'OP-01', 'operaciones_calidad' => 'OP-02',
            'operaciones_recursos' => 'OP-03', 'operaciones_proyectos' => 'OP-04',
            'operaciones_procesos' => 'OP-05', 'gestion_clientes' => 'GE-01',
            'gestion_desempeno' => 'GE-02', 'gestion_indicadores' => 'GE-03',
            'gestion_planificacion' => 'GE-04', 'gestion_riesgos' => 'GE-05',
            'finanzas_ingresos' => 'FI-01', 'finanzas_presupuestos' => 'FI-02',
            'finanzas_cobros' => 'FI-03', 'finanzas_rentabilidad' => 'FI-04',
            'finanzas_proyecciones' => 'FI-05',
        ] as $key => $code) {
            self::assertStringContainsString("'{$key}': '{$code}'", $source);
        }
        self::assertStringContainsString('bi13Ux2VisualNeedCode(improvement.key)', $source);
        self::assertStringContainsString('bi13Ux2VisualNeedCode(suggestion.need_key)', $source);
    }

    public function test_original_relation_editor_and_bi13_form_still_present(): void
    {
        $source = $this->vueSourceText();
        foreach ([
            'dataBiToggleNeedRelation(', 'dataBiUpdateNeedLinkNotes(',
            'dataBiApplyFunctionalProposals', 'saveImplementationDefinitionHumanReview',
        ] as $part) {
            self::assertStringContainsString($part, $source);
        }
        self::assertStringNotContainsString('dataBiDeliverableOptions.findIndex(', $source);
    }
}
