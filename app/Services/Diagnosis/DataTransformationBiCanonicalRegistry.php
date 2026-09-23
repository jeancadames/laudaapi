<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use LogicException;

final class DataTransformationBiCanonicalRegistry
{
    /*
     * Registry version belongs to the source-centric canonical contract.
     *
     * It is intentionally independent from:
     * - tenant source definitions;
     * - legacy source-domain delivery;
     * - mapping version;
     * - source profiling version.
     */
    public const VERSION = 1;

    private const STANDARD_INTAKE_BASE_VERSION = 1;

    /*
     * New canonical entities belong here as LAUDA evolves.
     *
     * They do not need to become Standard Intake domains and they do not
     * change what source tables/files a tenant is allowed to register.
     *
     * Shape:
     *
     * 'entity_key' => [
     *     'key' => 'entity_key',
     *     'label' => '...',
     *     'description' => '...',
     *     'identity_keys' => ['...'],
     *     'fields' => [
     *         'field_key' => [
     *             'key' => 'field_key',
     *             'required' => false,
     *             'type' => 'text',
     *             'description' => '...',
     *         ],
     *     ],
     *     'source' => 'canonical_registry',
     * ]
     */
    private const EXTENSION_ENTITIES = [];

    /*
     * Shape:
     *
     * [
     *     'from_entity_key' => '...',
     *     'from_field_key' => '...',
     *     'to_entity_key' => '...',
     *     'to_field_key' => '...',
     *     'source' => 'canonical_registry',
     * ]
     */
    private const EXTENSION_RELATIONSHIPS = [];

    public function version(): int
    {
        return self::VERSION;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function entities(): array
    {
        if (
            DataTransformationBiStandardIntakeSchema::VERSION
                !== self::STANDARD_INTAKE_BASE_VERSION
        ) {
            throw new LogicException(
                'Canonical Registry v1 requiere revisar explícitamente '
                .'cambios del Standard Intake base antes de adoptarlos.'
            );
        }

        $entities = [];

        $identityKeys =
            DataTransformationBiStandardIntakeSchema::identityKeys();

        foreach (
            DataTransformationBiStandardIntakeSchema::domains()
            as $entityKey => $definition
        ) {
            $fields = [];

            foreach (
                $definition['fields'] ?? []
                as $field
            ) {
                if (
                    ! is_array($field)
                    || ! isset($field['name'])
                ) {
                    throw new LogicException(
                        "Campo canónico inválido en {$entityKey}."
                    );
                }

                $fieldKey =
                    trim(
                        (string) $field['name']
                    );

                if ($fieldKey === '') {
                    throw new LogicException(
                        "Campo canónico vacío en {$entityKey}."
                    );
                }

                if (isset($fields[$fieldKey])) {
                    throw new LogicException(
                        "Campo canónico duplicado: "
                        ."{$entityKey}.{$fieldKey}."
                    );
                }

                $fields[$fieldKey] = [
                    'key' =>
                        $fieldKey,

                    'required' =>
                        (bool) (
                            $field['required']
                            ?? false
                        ),

                    'type' =>
                        (string) (
                            $field['type']
                            ?? 'text'
                        ),

                    'description' =>
                        (string) (
                            $field['description']
                            ?? ''
                        ),
                ];
            }

            $entities[$entityKey] = [
                'key' =>
                    (string) $entityKey,

                'label' =>
                    (string) (
                        $definition['label']
                        ?? $entityKey
                    ),

                'description' =>
                    (string) (
                        $definition['description']
                        ?? ''
                    ),

                'identity_keys' =>
                    array_values(
                        $identityKeys[$entityKey]
                        ?? []
                    ),

                'fields' =>
                    $fields,

                /*
                 * Traceability only.
                 *
                 * It does not mean the new source-centric mapping is a
                 * Standard Intake/domain-delivery workflow.
                 */
                'source' =>
                    'standard_intake_schema_v1',
            ];
        }

        foreach (
            self::EXTENSION_ENTITIES
            as $entityKey => $definition
        ) {
            if (isset($entities[$entityKey])) {
                throw new LogicException(
                    "La extensión canónica {$entityKey} intenta "
                    .'sobrescribir una entidad base.'
                );
            }

            $entities[$entityKey] =
                $definition;
        }

        return $entities;
    }

    /**
     * @return array<int,string>
     */
    public function entityKeys(): array
    {
        return array_keys(
            $this->entities()
        );
    }

    public function supportsEntity(
        string $entityKey
    ): bool {
        return isset(
            $this->entities()[$entityKey]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function entity(
        string $entityKey
    ): array {
        $entities =
            $this->entities();

        if (! isset($entities[$entityKey])) {
            throw new InvalidArgumentException(
                "Entidad canónica no registrada: {$entityKey}."
            );
        }

        return $entities[$entityKey];
    }

    /**
     * @return array<int,string>
     */
    public function fieldKeys(
        string $entityKey
    ): array {
        $entity =
            $this->entity(
                $entityKey
            );

        return array_keys(
            is_array($entity['fields'] ?? null)
                ? $entity['fields']
                : []
        );
    }

    public function supportsField(
        string $entityKey,
        string $fieldKey
    ): bool {
        if (! $this->supportsEntity($entityKey)) {
            return false;
        }

        $entity =
            $this->entity(
                $entityKey
            );

        return isset(
            $entity['fields'][$fieldKey]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function field(
        string $entityKey,
        string $fieldKey
    ): array {
        $entity =
            $this->entity(
                $entityKey
            );

        if (
            ! isset(
                $entity['fields'][$fieldKey]
            )
        ) {
            throw new InvalidArgumentException(
                "Campo canónico no registrado: "
                ."{$entityKey}.{$fieldKey}."
            );
        }

        return $entity['fields'][$fieldKey];
    }

    /**
     * @return array<int,string>
     */
    public function identityKeys(
        string $entityKey
    ): array {
        $entity =
            $this->entity(
                $entityKey
            );

        return array_values(
            is_array(
                $entity['identity_keys']
                ?? null
            )
                ? $entity['identity_keys']
                : []
        );
    }

    /**
     * @return array<int,array{
     *     from_entity_key:string,
     *     from_field_key:string,
     *     to_entity_key:string,
     *     to_field_key:string,
     *     source:string
     * }>
     */
    public function relationships(): array
    {
        $relationships = [];

        foreach (
            DataTransformationBiStandardIntakeSchema::relationships()
            as $relationship
        ) {
            $relationships[] = [
                'from_entity_key' =>
                    (string) $relationship['from_domain'],

                'from_field_key' =>
                    (string) $relationship['from_field'],

                'to_entity_key' =>
                    (string) $relationship['to_domain'],

                'to_field_key' =>
                    (string) $relationship['to_field'],

                'source' =>
                    'standard_intake_schema_v1',
            ];
        }

        foreach (
            self::EXTENSION_RELATIONSHIPS
            as $relationship
        ) {
            $relationships[] =
                $relationship;
        }

        return $relationships;
    }
}
