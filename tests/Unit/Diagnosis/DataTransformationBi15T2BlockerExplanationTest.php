<?php

use PHPUnit\Framework\TestCase;

final class DataTransformationBi15T2BlockerExplanationTest extends TestCase
{
    public function test_explains_real_blockers_without_changing_server_gates(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue';
        $source = file_get_contents($path);
        self::assertIsString($source);

        $marker = 'BI15_T2_EXPLAINABLE_BLOCKERS';
        self::assertSame(1, substr_count($source, $marker));
        self::assertLessThan(strpos($source, $marker), strpos($source, 'BI15_T1_PRESENTATION_PREPARATION'));

        $start = strpos($source, '<!-- ' . $marker);
        self::assertNotFalse($start);
        $end = strpos($source, '</section>', $start);
        self::assertNotFalse($end);
        $panel = substr($source, $start, $end - $start);

        foreach ([
            'Qué falta para presentar la definición',
            'dataBiAssessmentCanConfirm()',
            'pendingDecisions',
            'clarificationNeeds',
            'humanReviewForm.readiness.scope_confirmed',
            'humanReviewForm.readiness.deliverables_confirmed',
            'humanReviewForm.readiness.dependencies_confirmed',
            'humanReviewForm.readiness.responsibilities_confirmed',
            'Insumos y accesos se verifican',
            'unlinkedNeeds',
            "bi13Ux2R5Select('needs')",
            "bi13Ux2R5Select('requirements')",
            'v-show="bi13Ux2R5Visible(\'review\')"',
            'No verifica la versión guardada',
        ] as $required) {
            self::assertStringContainsString($required, $panel);
        }

        foreach (['v-model=', '.post(', 'submitDefinitionForTenantReview(', 'markReady(', 'ready_for_commercial'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $panel);
        }
    }
}
