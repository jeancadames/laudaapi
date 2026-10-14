<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiUx2NavigationContractTest extends TestCase
{
    public function test_bi_need_codes_are_stable_and_ui_only(): void
    {
        $view = file_get_contents(base_path(
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ));
        $this->assertIsString($view);

        $pairs = [
            'operaciones_inventarios' => 'OP-01',
            'operaciones_calidad' => 'OP-02',
            'operaciones_recursos' => 'OP-03',
            'operaciones_proyectos' => 'OP-04',
            'operaciones_procesos' => 'OP-05',
            'gestion_clientes' => 'GE-01',
            'gestion_desempeno' => 'GE-02',
            'gestion_indicadores' => 'GE-03',
            'gestion_planificacion' => 'GE-04',
            'gestion_riesgos' => 'GE-05',
            'finanzas_ingresos' => 'FI-01',
            'finanzas_presupuestos' => 'FI-02',
            'finanzas_cobros' => 'FI-03',
            'finanzas_rentabilidad' => 'FI-04',
            'finanzas_proyecciones' => 'FI-05',
        ];

        foreach ($pairs as $key => $code) {
            $this->assertStringContainsString("{$key}: '{$code}'", $view);
        }
        $this->assertStringContainsString('function dataBiUx2NeedCode(', $view);
        $this->assertStringContainsString('return dataBiUx2NeedCodes[key] ?? key;', $view);
    }

    public function test_navigation_targets_exist_and_form_is_preserved(): void
    {
        $view = file_get_contents(base_path(
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ));
        $this->assertIsString($view);

        foreach (['needs', 'proposals', 'requirements', 'links', 'review'] as $section) {
            $this->assertSame(
                1,
                substr_count($view, 'id="bi13-ux2-'.$section.'"')
            );
        }
        $this->assertStringContainsString('const humanReviewForm = useForm({', $view);
        $this->assertStringContainsString('function saveImplementationDefinitionHumanReview()', $view);
        $this->assertStringContainsString('humanReviewForm.patch(', $view);
        $this->assertStringContainsString('dataBiUx2NeedCode(improvement.key)', $view);
        $this->assertStringContainsString('dataBiUx2NeedCode(suggestion.need_key)', $view);
    }
}
