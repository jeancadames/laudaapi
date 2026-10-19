<?php

use PHPUnit\Framework\Assert;

it('shows preparation as informational and preserves backend transitions', function (): void {
    $vue = file_get_contents(dirname(__DIR__, 3) . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
    Assert::assertIsString($vue);
    Assert::assertSame(1, substr_count($vue, 'BI15_T1_PRESENTATION_PREPARATION'));
    Assert::assertSame(1, substr_count($vue, 'function bi15T1OpenSection('));
    $start = strpos($vue, 'BI15_T1_PRESENTATION_PREPARATION');
    $end = strpos($vue, 'BI15_T1_PANEL_END', $start);
    Assert::assertNotFalse($start);
    Assert::assertNotFalse($end);
    $panel = substr($vue, $start, $end - $start);
    foreach ([
        'dataBiFunctionalCoverageMetrics().pendingDecisions',
        'dataBiFunctionalCoverageMetrics().clarificationNeeds',
        'dataBiFunctionalCoverageMetrics().fullyLinkedNeeds',
        'dataBiFunctionalCoverageMetrics().partiallyLinkedNeeds',
        'dataBiAssessmentCanConfirm()',
        'humanReviewForm.readiness.scope_confirmed',
        "bi15T1OpenSection('needs')",
        "bi15T1OpenSection('requirements')",
        'no determina si el servidor aceptará presentar',
    ] as $contract) {
        Assert::assertStringContainsString($contract, $panel);
    }
    foreach (['v-model=', 'humanReviewForm.patch(', 'tenantReviewSubmissionForm.post(', 'dataBiEnsureNeedLink('] as $mutation) {
        Assert::assertStringNotContainsString($mutation, $panel);
    }
    Assert::assertStringContainsString('v-if="props.capability.key === DATA_BI_CAPABILITY"', $panel);
});
