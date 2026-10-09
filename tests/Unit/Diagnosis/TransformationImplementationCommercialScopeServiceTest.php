<?php

use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationDefinition;
use App\Services\Diagnosis\TransformationImplementationCommercialScopeService;
use Illuminate\Validation\ValidationException;

// Boot the Laravel application so ValidationException::withMessages()
// can resolve the Validator facade in these Pest unit tests.
uses(Tests\TestCase::class);

function d2iR1ScopeDefinition(): TransformationImplementationDefinition
{
    return (new TransformationImplementationDefinition())->forceFill([
        'id' => 3,
        'version' => 1,
        'implementation_scope' => ['includes' => ['Clientes', 'Ventas']],
        'deliverables' => [
            ['deliverable' => 'Maestro Clientes', 'source' => 'professional_capability'],
            ['deliverable' => 'Historial Ventas', 'source' => 'professional_capability'],
            ['deliverable' => 'Inteligencia de Suplidores', 'source' => 'professional_capability'],
        ],
    ]);
}

it('preserves the definition while selecting exact contracted deliverables', function () {
    $definition = d2iR1ScopeDefinition();
    $before = $definition->deliverables;
    $data = app(TransformationImplementationCommercialScopeService::class)->build($definition, [
        'contract_scope_mode' => 'partial',
        'contract_deliverable_indices' => [1, 0],
        'contract_scope_terms' => ['acceptance_criteria' => 'Validación con responsables'],
    ]);
    expect($definition->deliverables)->toBe($before);
    expect($data['contracted_scope_snapshot']['selected_definition_indices'])->toBe([0,1]);
    expect($data['contracted_deliverables_snapshot'])->toHaveCount(2);
    expect($data['contracted_deliverables_snapshot'][0]['definition_index'])->toBe(0);
});

it('rejects unknown and duplicate selected indices', function () {
    foreach ([[5], [0,0]] as $indices) {
        try {
            app(TransformationImplementationCommercialScopeService::class)->build(d2iR1ScopeDefinition(), [
                'contract_scope_mode' => 'partial',
                'contract_deliverable_indices' => $indices,
                'contract_scope_terms' => ['acceptance_criteria' => 'Revisión'],
            ]);
            throw new RuntimeException('Expected ValidationException');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('contract_deliverable_indices');
        }
    }
});

it('rejects full mode without all deliverables', function () {
    app(TransformationImplementationCommercialScopeService::class)->build(d2iR1ScopeDefinition(), [
        'contract_scope_mode' => 'full',
        'contract_deliverable_indices' => [0],
        'contract_scope_terms' => ['acceptance_criteria' => 'Revisión'],
    ]);
})->throws(ValidationException::class);

it('validates immutable contracted snapshots before commercial presentation', function () {
    $definition = d2iR1ScopeDefinition();
    $data = app(TransformationImplementationCommercialScopeService::class)->build($definition, [
        'contract_scope_mode' => 'partial',
        'contract_deliverable_indices' => [0,1],
        'contract_scope_terms' => ['acceptance_criteria' => 'Revisión'],
    ]);
    $engagement = (new TransformationImplementationCommercialEngagement())->forceFill([
        'transformation_implementation_definition_id' => 3,
        'scope_snapshot' => $definition->implementation_scope,
        'deliverables_snapshot' => $definition->deliverables,
        'contract_scope_schema_version' => 1,
    ] + $data);
    app(TransformationImplementationCommercialScopeService::class)->assertPersistedContract($engagement);
    $bad = $engagement->contracted_deliverables_snapshot;
    $bad[0]['deliverable'] = 'ALTERNATIVO NO ACORDADO';
    $engagement->contracted_deliverables_snapshot = $bad;
    expect(fn () => app(TransformationImplementationCommercialScopeService::class)
        ->assertPersistedContract($engagement))->toThrow(ValidationException::class);
});
