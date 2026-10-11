<?php

namespace App\Services\Diagnosis;

use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestExpectedSourcesPlan
{
    private const SOURCE_TYPES = [
        'to_define',
        'sql_server',
        'mysql',
        'postgresql',
        'dbf',
        'quickbooks',
        'excel',
        'csv',
        'api',
        'other',
    ];

    /**
     * Referencia determinista del entregable.
     * Si cambia el contenido relevante, cambia la referencia.
     */
    public static function deliverableRef(array $item): string
    {
        $parts = [
            (string) ($item['source'] ?? ''),
            (string) ($item['phase_id'] ?? ''),
            (string) ($item['capability_key'] ?? ''),
            trim((string) ($item['deliverable'] ?? '')),
        ];

        return 'del_'.hash(
            'sha256',
            json_encode(
                $parts,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /**
     * BI12_T1 · Normalización sin efectos secundarios.
     *
     * No crea fuentes ni archivos técnicos.
     */
    public static function normalize(
        array $snapshot,
        array $scope,
        array $deliverables
    ): array {
        if (! array_key_exists('expected_sources_plan', $scope)) {
            return $scope;
        }

        $plan = $scope['expected_sources_plan'];

        if (
            ! is_array($plan)
            || ($plan['schema_version'] ?? null) !== 1
            || ! is_array($plan['sources'] ?? null)
            || ! is_array($plan['links'] ?? null)
            || ! array_is_list($plan['sources'])
            || ! array_is_list($plan['links'])
        ) {
            self::fail('El plan de fuentes esperadas tiene estructura inválida.');
        }

        $improvements = data_get(
            $snapshot,
            'business_needs.improvements',
            []
        );

        if (! is_array($improvements) || $improvements === []) {
            self::fail('No existen necesidades originales para relacionar.');
        }

        $needKeys = [];

        foreach ($improvements as $improvement) {
            $key = is_array($improvement)
                ? trim((string) ($improvement['key'] ?? ''))
                : '';

            if ($key === '' || isset($needKeys[$key])) {
                self::fail('Las necesidades originales no tienen claves únicas.');
            }

            $needKeys[$key] = true;
        }

        if (count($plan['sources']) > 100 || count($plan['links']) > 300) {
            self::fail('El plan supera los límites admitidos.');
        }

        $sources = [];
        $sourceKeys = [];

        foreach ($plan['sources'] as $source) {
            if (! is_array($source)) {
                self::fail('Una fuente esperada tiene estructura inválida.');
            }

            $key = $source['key'] ?? null;
            $name = $source['name'] ?? null;
            $type = $source['source_type'] ?? null;
            // BI12_T2_ORIGIN · Origen preliminar.
            // No representa una fuente técnica verificada.
            $originHint = $source['origin_hint'] ?? '';

            if (
                ! is_string($originHint)
                || mb_strlen(trim($originHint)) > 200
            ) {
                self::fail(
                    'El origen preliminar es inválido.'
                );
            }

            $notes = $source['notes'] ?? '';
            $domains = $source['data_domains'] ?? [];

            if (
                ! is_string($key)
                || ! preg_match('/\Asrc_[A-Za-z0-9_-]{4,80}\z/D', $key)
                || isset($sourceKeys[$key])
                || ! is_string($name)
                || trim($name) === ''
                || mb_strlen(trim($name)) > 180
                || ! is_string($type)
                || ! in_array($type, self::SOURCE_TYPES, true)
                || ! is_string($notes)
                || mb_strlen(trim($notes)) > 2000
            ) {
                self::fail('La identificación o descripción de una fuente esperada es inválida.');
            }

            $domains = self::stringList($domains, 30, 120);

            $sourceKeys[$key] = true;

            $sources[] = [
                'key' => $key,
                'name' => trim($name),
                'source_type' => $type,
                'origin_hint' => trim($originHint),
                'data_domains' => $domains,
                'notes' => trim($notes),
            ];
        }

        $deliverableRefs = [];

        foreach ($deliverables as $deliverable) {
            if (
                ! is_array($deliverable)
                || ! is_string($deliverable['deliverable'] ?? null)
                || trim($deliverable['deliverable']) === ''
            ) {
                continue;
            }

            $ref = self::deliverableRef($deliverable);

            if (isset($deliverableRefs[$ref])) {
                self::fail('Existen entregables indistinguibles para la vinculación.');
            }

            $deliverableRefs[$ref] = true;
        }

        $statuses = [];

        foreach (
            data_get($scope, 'business_needs_assessment.items', [])
            as $item
        ) {
            if (is_array($item) && is_string($item['key'] ?? null)) {
                $statuses[$item['key']] = $item['status'] ?? null;
            }
        }

        $links = [];
        $linkedNeeds = [];

        foreach ($plan['links'] as $link) {
            if (! is_array($link)) {
                self::fail('Una relación funcional tiene estructura inválida.');
            }

            $needKey = $link['need_key'] ?? null;
            $notes = $link['notes'] ?? '';

            if (
                ! is_string($needKey)
                || ! isset($needKeys[$needKey])
                || isset($linkedNeeds[$needKey])
                || ! is_string($notes)
                || mb_strlen(trim($notes)) > 2000
            ) {
                self::fail('Una relación contiene una necesidad o justificación inválida.');
            }

            $linkedNeeds[$needKey] = true;

            $linkedSources = self::stringList(
                $link['source_keys'] ?? null,
                100,
                100
            );

            $linkedDeliverables = self::stringList(
                $link['deliverable_refs'] ?? null,
                100,
                80
            );

            foreach ($linkedSources as $key) {
                if (! isset($sourceKeys[$key])) {
                    self::fail('Una relación referencia una fuente inexistente.');
                }
            }

            foreach ($linkedDeliverables as $ref) {
                if (! isset($deliverableRefs[$ref])) {
                    self::fail('Una relación referencia un entregable inexistente.');
                }
            }

            if (
                ($statuses[$needKey] ?? null) === 'excluded'
                && ($linkedSources !== [] || $linkedDeliverables !== [])
            ) {
                self::fail('Una necesidad excluida no puede conservar vinculaciones.');
            }

            $links[] = [
                'need_key' => $needKey,
                'source_keys' => $linkedSources,
                'deliverable_refs' => $linkedDeliverables,
                'notes' => trim($notes),
            ];
        }

        $scope['expected_sources_plan'] = [
            'schema_version' => 1,
            'sources' => $sources,
            'links' => $links,
        ];

        return $scope;
    }

    private static function stringList(
        mixed $values,
        int $maximum,
        int $maxLength
    ): array {
        if (
            ! is_array($values)
            || ! array_is_list($values)
            || count($values) > $maximum
        ) {
            self::fail('Una lista del plan tiene estructura inválida.');
        }

        $result = [];

        foreach ($values as $value) {
            if (
                ! is_string($value)
                || trim($value) === ''
                || mb_strlen(trim($value)) > $maxLength
                || in_array(trim($value), $result, true)
            ) {
                self::fail('Una lista contiene valores inválidos o duplicados.');
            }

            $result[] = trim($value);
        }

        return $result;
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages([
            'implementation_scope.expected_sources_plan' => [$message],
        ]);
    }
}
