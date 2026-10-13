<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiFunctionalCoverageUiContractTest extends TestCase
{
    private function source(): string
    {
        $path = resource_path('js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
        $code = file_get_contents($path);
        $this->assertIsString($code);
        return $code;
    }

    public function test_coverage_is_calculated_from_current_form_without_writing(): void
    {
        $code = $this->source();
        $start = strpos($code, 'function dataBiFunctionalCoverageMetrics():');
        $end = strpos($code, 'function dataBiPlanEditable():', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $helper = substr($code, $start, $end - $start);

        $this->assertStringContainsString('dataBiExpectedPlan()', $helper);
        $this->assertStringContainsString('dataBiNeedDecisionStatus(key)', $helper);
        $this->assertStringContainsString("status === 'excluded'", $helper);
        $this->assertStringContainsString('validRequirementKeys.has(key)', $helper);
        $this->assertStringContainsString('linkedNeedKeys.add(needKey)', $helper);
        $this->assertStringContainsString('usedRequirementKeys.add(key)', $helper);
        $this->assertStringContainsString('relatedDeliverables.add(ref)', $helper);
        $this->assertStringContainsString('dataBiProposalPreviewSignature', $helper);
        $this->assertStringNotContainsString('humanReviewForm.patch(', $helper);
        $this->assertStringNotContainsString('fetch(', $helper);
    }

    public function test_view_shows_six_indicators_without_auto_apply_or_save(): void
    {
        $code = $this->source();
        $start = strpos($code, '<!-- BI13_T3_COVERAGE · Actualización reactiva del formulario -->');
        $end = strpos($code, '<!-- BI13_T2 · Sugerencias sin persistencia automática -->', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $section = substr($code, $start, $end - $start);

        foreach ([
            'Necesidades vinculadas',
            'Sin vinculación',
            'Requisitos sin usar',
            'Aclaraciones pendientes',
            'Entregables relacionados',
            'Propuestas sin aplicar',
            'Ver pendientes por revisar',
            'Vincular no significa',
        ] as $label) {
            $this->assertStringContainsString($label, $section);
        }
        $this->assertStringNotContainsString('@click=', $section);
        $this->assertStringNotContainsString('v-model', $section);
    }
}
