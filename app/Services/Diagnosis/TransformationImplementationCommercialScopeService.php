<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationDefinition;
use Illuminate\Validation\ValidationException;

/**
 * Immutable, definition-indexed contracted scope. The agreed Definition stays intact.
 */
final class TransformationImplementationCommercialScopeService
{
    /** @return array{contracted_scope_snapshot: array, contracted_deliverables_snapshot: array} */
    public function build(TransformationImplementationDefinition $definition, array $data): array
    {
        $mode = $data['contract_scope_mode'] ?? null;
        $indices = $data['contract_deliverable_indices'] ?? null;
        $conditions = $data['contract_scope_terms'] ?? null;
        $original = $definition->deliverables;

        if (! is_array($original) || ! array_is_list($original) || $original === []) {
            $this->reject('definition', 'La definición acordada no contiene entregables válidos.');
        }
        if (! in_array($mode, ['full', 'partial'], true)) {
            $this->reject('contract_scope_mode', 'Selecciona explícitamente alcance completo o parcial.');
        }
        if (! is_array($indices) || ! array_is_list($indices) || $indices === []) {
            $this->reject('contract_deliverable_indices', 'Selecciona al menos un entregable de la definición.');
        }

        foreach ($indices as $index) {
            if (! is_int($index) || $index < 0 || ! array_key_exists($index, $original)) {
                $this->reject('contract_deliverable_indices', 'La selección contiene un entregable no autorizado.');
            }
        }
        if (count(array_unique($indices)) !== count($indices)) {
            $this->reject('contract_deliverable_indices', 'No se permiten entregables duplicados.');
        }

        sort($indices, SORT_NUMERIC);
        $fullIndices = range(0, count($original) - 1);
        if (($mode === 'full' && $indices !== $fullIndices)
            || ($mode === 'partial' && $indices === $fullIndices)) {
            $this->reject('contract_scope_mode', 'La modalidad no corresponde a los entregables seleccionados.');
        }

        if (! is_array($conditions) || ! is_string($conditions['acceptance_criteria'] ?? null)
            || trim($conditions['acceptance_criteria']) === '') {
            $this->reject('contract_scope_terms', 'Define criterios de aceptación de la propuesta.');
        }
        $cleanConditions = [];
        foreach (['acceptance_criteria', 'dependencies', 'assumptions', 'exclusions'] as $field) {
            $value = $conditions[$field] ?? '';
            if (! is_string($value) || strlen($value) > 10000) {
                $this->reject('contract_scope_terms', 'Una condición comercial tiene formato o longitud inválida.');
            }
            $cleanConditions[$field] = trim($value);
        }

        $selected = [];
        foreach ($indices as $index) {
            if (! is_array($original[$index]) || ! is_string($original[$index]['deliverable'] ?? null)
                || trim($original[$index]['deliverable']) === '') {
                $this->reject('definition', 'La definición contiene un entregable sin descripción utilizable.');
            }
            $selected[] = ['definition_index' => $index] + $original[$index];
        }

        return [
            'contracted_scope_snapshot' => [
                'schema_version' => 1,
                'mode' => $mode,
                'definition_id' => (int) $definition->getKey(),
                'definition_version' => (int) $definition->version,
                'selected_definition_indices' => $indices,
                'reference_deliverable_count' => count($original),
                'conditions' => $cleanConditions,
            ],
            'contracted_deliverables_snapshot' => $selected,
        ];
    }

    public function assertPersistedContract(
        TransformationImplementationCommercialEngagement $engagement,
        ?TransformationImplementationDefinition $definition = null
    ): void
    {
        $scope = $engagement->contracted_scope_snapshot;
        $selected = $engagement->contracted_deliverables_snapshot;
        $reference = $engagement->deliverables_snapshot;
        if ((int) $engagement->contract_scope_schema_version !== 1
            || ! is_array($scope) || ! is_array($selected) || $selected === []
            || ! is_array($reference) || $reference === []
            || (int) ($scope['schema_version'] ?? 0) !== 1
            || (int) ($scope['definition_id'] ?? 0) !== (int) $engagement->transformation_implementation_definition_id) {
            $this->reject('contracted_scope_snapshot', 'La propuesta no posee un alcance contratado válido.');
        }
        if (! is_int($scope['definition_version'] ?? null)
            || $scope['definition_version'] <= 0
            || ! is_int($scope['reference_deliverable_count'] ?? null)
            || $scope['reference_deliverable_count'] !== count($reference)) {
            $this->reject('contracted_scope_snapshot', 'Versión o número de entregables de referencia inconsistente.');
        }
        if ($definition !== null
            && ((int) $scope['definition_id'] !== (int) $definition->getKey()
                || (int) $scope['definition_version'] !== (int) $definition->version
                || $reference !== $definition->deliverables
                || $engagement->scope_snapshot !== $definition->implementation_scope)) {
            $this->reject('contracted_scope_snapshot', 'El alcance congelado difiere de la definición acordada.');
        }
        $indexes = $scope['selected_definition_indices'] ?? null;
        if (! is_array($indexes) || ! array_is_list($indexes) || count($indexes) !== count($selected)) {
            $this->reject('contracted_deliverables_snapshot', 'Los entregables contratados no coinciden con la selección.');
        }
        foreach ($indexes as $idx) {
            if (! is_int($idx) || $idx < 0) {
                $this->reject('contracted_deliverables_snapshot', 'La selección contiene índices inválidos.');
            }
        }
        if (count(array_unique($indexes)) !== count($indexes)) {
            $this->reject('contracted_deliverables_snapshot', 'La selección contiene duplicados.');
        }
        if (! in_array($scope['mode'] ?? null, ['full', 'partial'], true)) {
            $this->reject('contract_scope_mode', 'La modalidad comercial es inválida.');
        }
        foreach ($selected as $position => $item) {
            $idx = $indexes[$position] ?? null;
            if (! is_int($idx) || ! array_key_exists($idx, $reference)
                || ! is_array($item) || ($item['definition_index'] ?? null) !== $idx) {
                $this->reject('contracted_deliverables_snapshot', 'El alcance contratado no corresponde con la definición.');
            }
            $exact = $item;
            unset($exact['definition_index']);
            if ($exact !== $reference[$idx]) {
                $this->reject('contracted_deliverables_snapshot', 'El contenido contratado difiere del entregable acordado.');
            }
        }
        $expected = range(0, count($reference) - 1);
        if ((($scope['mode'] ?? '') === 'full') !== ($indexes === $expected)) {
            $this->reject('contract_scope_mode', 'El modo contratado es incoherente con la selección.');
        }
        if (! is_string(data_get($scope, 'conditions.acceptance_criteria'))
            || trim(data_get($scope, 'conditions.acceptance_criteria')) === '') {
            $this->reject('contract_scope_terms', 'No hay criterios de aceptación.');
        }
    }

    private function reject(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
