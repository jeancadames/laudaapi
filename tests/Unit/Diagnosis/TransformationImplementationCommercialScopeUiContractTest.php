<?php

// D2I-R1-D1B · Contractual scope UI wiring (no database access).

it('sends explicit contracted scope fields from Admin LAUDA', function (): void {
    $vue = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
    foreach (['contract_scope_mode: data.contract_scope_mode',
        'contract_deliverable_indices: [...data.contract_deliverable_indices]',
        'contract_scope_terms: {',
        'chooseCommercialScopeMode',
        'v-model="commercialDraftForm.contract_scope_terms.acceptance_criteria"'] as $needle) {
        expect($vue)->toContain($needle);
    }
});

it('shows contracted terms separately from original reference in Admin', function (): void {
    $vue = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
    expect($vue)->toContain('Alcance contratado de esta propuesta')
        ->toContain('Alcance general (referencia)')
        ->toContain('Entregables generales (referencia)')
        ->toContain('Versión anterior sin selección contractual estructurada')
        ->toContain('Alcance contratado de esta versión histórica');
});

it('shows scoped contractual terms before tenant acceptance', function (): void {
    $vue = file_get_contents(dirname(__DIR__, 3).'/resources/js/pages/App/DataTransformationBi.vue');
    $scopePosition = strpos($vue, 'D2I_R1_D1B_CONTRACTED_SCOPE_UI');
    $acceptancePosition = strpos($vue, 'Aceptación expresa de la propuesta');
    expect($scopePosition)->not->toBeFalse();
    expect($acceptancePosition)->not->toBeFalse();
    expect($scopePosition)->toBeLessThan($acceptancePosition);
    expect($vue)->toContain('contracted_deliverables_snapshot')->toContain('contracted_scope_snapshot');
});
