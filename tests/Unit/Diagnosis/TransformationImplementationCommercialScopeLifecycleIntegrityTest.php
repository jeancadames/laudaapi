<?php

use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationDefinition;
use App\Services\Diagnosis\TransformationImplementationCommercialScopeService;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

function d2iD1DDefinition(): TransformationImplementationDefinition
{
    return (new TransformationImplementationDefinition())->forceFill([
        'id' => 37,
        'version' => 2,
        'implementation_scope' => ['includes' => ['Clientes', 'Ventas']],
        'deliverables' => [
            ['deliverable' => 'Maestro Clientes', 'source' => 'professional_capability'],
            ['deliverable' => 'Historial Ventas', 'source' => 'professional_capability'],
            ['deliverable' => 'Inventario', 'source' => 'professional_capability'],
        ],
    ]);
}

function d2iD1DEngagement(TransformationImplementationDefinition $definition): TransformationImplementationCommercialEngagement
{
    $built = app(TransformationImplementationCommercialScopeService::class)->build($definition, [
        'contract_scope_mode' => 'partial',
        'contract_deliverable_indices' => [0, 1],
        'contract_scope_terms' => ['acceptance_criteria' => 'Validación de entregables con responsables'],
    ]);
    return (new TransformationImplementationCommercialEngagement())->forceFill([
        'transformation_implementation_definition_id' => $definition->getKey(),
        'scope_snapshot' => $definition->implementation_scope,
        'deliverables_snapshot' => $definition->deliverables,
        'contract_scope_schema_version' => 1,
    ] + $built);
}

it('validates a V1 contracted commercial scope against the exact pinned definition', function () {
    $definition = d2iD1DDefinition();
    app(TransformationImplementationCommercialScopeService::class)
        ->assertPersistedContract(d2iD1DEngagement($definition), $definition);
    expect(true)->toBeTrue();
});

it('rejects a tampered definition version', function () {
    $definition = d2iD1DDefinition();
    $engagement = d2iD1DEngagement($definition);
    $scope = $engagement->contracted_scope_snapshot;
    $scope['definition_version'] = 99;
    $engagement->contracted_scope_snapshot = $scope;
    expect(fn () => app(TransformationImplementationCommercialScopeService::class)
        ->assertPersistedContract($engagement, $definition))->toThrow(ValidationException::class);
});

it('rejects a tampered reference deliverable count', function () {
    $definition = d2iD1DDefinition();
    $engagement = d2iD1DEngagement($definition);
    $scope = $engagement->contracted_scope_snapshot;
    $scope['reference_deliverable_count'] = 999;
    $engagement->contracted_scope_snapshot = $scope;
    expect(fn () => app(TransformationImplementationCommercialScopeService::class)
        ->assertPersistedContract($engagement, $definition))->toThrow(ValidationException::class);
});

it('rejects an altered general snapshot against the pinned definition', function () {
    $definition = d2iD1DDefinition();
    $engagement = d2iD1DEngagement($definition);
    $general = $engagement->deliverables_snapshot;
    $general[2]['deliverable'] = 'Inventario cambiado';
    $engagement->deliverables_snapshot = $general;
    expect(fn () => app(TransformationImplementationCommercialScopeService::class)
        ->assertPersistedContract($engagement, $definition))->toThrow(ValidationException::class);
});

it('checks contracted scope before authorization and preserves exact evidence', function () {
    $authorization = file_get_contents(base_path(
        'app/Services/Diagnosis/TransformationImplementationAuthorizationService.php'
    ));
    $method = explode('private function assertLaudaAdmin(', explode('public function authorize(', $authorization, 2)[1], 2)[0];
    expect($method)->toContain('assertPersistedContract($lockedEngagement, $definition)');
    expect($method)->toContain("'contracted_scope_snapshot'");
    expect($method)->toContain("'contracted_deliverables_snapshot'");
    expect($method)->toContain("'contract_scope_schema_version'");
    expect(strpos($method, 'assertPersistedContract('))->toBeLessThan(strpos($method, '$authorization ='));
});
