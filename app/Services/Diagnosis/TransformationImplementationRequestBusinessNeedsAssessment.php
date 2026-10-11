<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestBusinessNeedsAssessment
{
    private const STATUSES = [
        'pending',
        'included',
        'clarification',
        'excluded',
    ];

    /**
     * BI11_T1 · Control antes de presentar o cerrar.
     *
     * Lee únicamente la solicitud original y la definición
     * persistida. No modifica registros ni snapshots.
     */
    public static function assertTransitionReady(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            trim((string) $request->capability_key)
            !== 'data_transformation_bi'
        ) {
            return;
        }

        $snapshot = $request->source_snapshot;

        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        if (! is_array($snapshot)) {
            self::fail(
                'No se pudo verificar el origen de las necesidades de la solicitud.'
            );
        }

        $scope = $definition->implementation_scope;

        if (! is_array($scope)) {
            self::fail(
                'La definición no contiene un alcance funcional válido.'
            );
        }

        // Fuerza la revisión completa independientemente
        // de los flags guardados previamente.
        self::normalize(
            $snapshot,
            $scope,
            ['scope_confirmed' => true]
        );

        // BI12_T1 · Segunda validación del plan, si existe,
        // antes de presentar al tenant o cerrar funcionalmente.
        TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $snapshot,
            $scope,
            is_array($definition->deliverables)
                ? $definition->deliverables
                : []
        );
    }

    public static function normalize(
        array $sourceSnapshot,
        array $scope,
        array $readiness = []
    ): array {
        $needs = $sourceSnapshot['business_needs'] ?? null;

        $improvements = is_array($needs)
            ? ($needs['improvements'] ?? null)
            : null;

        if (! is_array($improvements) || $improvements === []) {
            if (array_key_exists('business_needs_assessment', $scope)) {
                self::fail(
                    'No se puede evaluar una solicitud sin necesidades originales.'
                );
            }

            return $scope;
        }

        $expected = [];

        foreach ($improvements as $improvement) {
            $key = is_array($improvement)
                ? trim((string) ($improvement['key'] ?? ''))
                : '';

            if ($key === '' || isset($expected[$key])) {
                self::fail(
                    'Las necesidades originales no tienen claves válidas y únicas.'
                );
            }

            $expected[$key] = true;
        }

        $block = $scope['business_needs_assessment'] ?? null;

        if ($block === null) {
            $items = array_map(
                static fn (string $key): array => [
                    'key' => $key,
                    'status' => 'pending',
                    'rationale' => '',
                ],
                array_keys($expected)
            );
        } else {
            if (
                ! is_array($block)
                || ($block['schema_version'] ?? null) !== 1
                || ! is_array($block['items'] ?? null)
            ) {
                self::fail(
                    'La evaluación de necesidades tiene una estructura inválida.'
                );
            }

            $items = $block['items'];
        }

        if (count($items) !== count($expected)) {
            self::fail(
                'La evaluación debe contener exactamente las necesidades solicitadas.'
            );
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                self::fail('Una evaluación tiene estructura inválida.');
            }

            $key = trim((string) ($item['key'] ?? ''));
            $status = $item['status'] ?? null;
            $rationale = $item['rationale'] ?? '';

            if (
                $key === ''
                || ! isset($expected[$key])
                || isset($normalized[$key])
            ) {
                self::fail(
                    'La evaluación contiene claves desconocidas o duplicadas.'
                );
            }

            if (
                ! is_string($status)
                || ! in_array($status, self::STATUSES, true)
                || ! is_string($rationale)
            ) {
                self::fail('La decisión o justificación es inválida.');
            }

            $rationale = trim($rationale);

            if (mb_strlen($rationale) > 2000) {
                self::fail('La justificación supera 2000 caracteres.');
            }

            if (
                in_array($status, ['clarification', 'excluded'], true)
                && $rationale === ''
            ) {
                self::fail(
                    'Las aclaraciones y exclusiones requieren justificación.'
                );
            }

            $normalized[$key] = [
                'key' => $key,
                'status' => $status,
                'rationale' => $rationale,
            ];
        }

        if (($readiness['scope_confirmed'] ?? false) === true) {
            foreach ($normalized as $item) {
                if (
                    ! in_array(
                        $item['status'],
                        ['included', 'excluded'],
                        true
                    )
                ) {
                    self::fail(
                        'No se puede confirmar el alcance con necesidades pendientes o por aclarar.'
                    );
                }
            }
        }

        // Siempre preservar el orden de la solicitud original.
        $scope['business_needs_assessment'] = [
            'schema_version' => 1,
            'items' => array_map(
                static fn (string $key): array => $normalized[$key],
                array_keys($expected)
            ),
        ];

        return $scope;
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'implementation_scope.business_needs_assessment' => [
                $message,
            ],
        ]);
    }
}
