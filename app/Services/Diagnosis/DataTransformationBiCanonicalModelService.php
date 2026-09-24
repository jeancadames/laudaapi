<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiCanonicalEntity;
use App\Models\DataTransformationBiCanonicalField;
use App\Models\DataTransformationBiCanonicalRegistryVersion;
use App\Models\DataTransformationBiCanonicalRelationship;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DataTransformationBiCanonicalModelService
{
    private const KEY_PATTERN =
        '/^[a-z][a-z0-9_]{0,99}$/';

    private const MAX_LABEL_LENGTH = 191;
    private const MAX_DESCRIPTION_LENGTH = 4000;

    private const FIELD_TYPES = [
        DataTransformationBiCanonicalField::TYPE_TEXT,
        DataTransformationBiCanonicalField::TYPE_INTEGER,
        DataTransformationBiCanonicalField::TYPE_DECIMAL,
        DataTransformationBiCanonicalField::TYPE_BOOLEAN,
        DataTransformationBiCanonicalField::TYPE_DATE,
        DataTransformationBiCanonicalField::TYPE_DATETIME,
    ];

    private const RELATIONSHIP_TYPES = [
        DataTransformationBiCanonicalRelationship::TYPE_ONE_TO_ONE,
        DataTransformationBiCanonicalRelationship::TYPE_ONE_TO_MANY,
        DataTransformationBiCanonicalRelationship::TYPE_MANY_TO_ONE,
        DataTransformationBiCanonicalRelationship::TYPE_MANY_TO_MANY,
    ];

    public function __construct(
        private readonly DataTransformationBiIntakeActorAuthorizationService
            $authorization
    ) {
    }

    /**
     * Read-only effective workspace for the company's canonical model.
     *
     * No registry is created from GET/read operations.
     *
     * @return array<string,mixed>
     */
    public function workspace(
        TransformationImplementationRequest $request,
        User $actor
    ): array {
        $this->assertAdmin(
            $request,
            $actor
        );

        $registry =
            DataTransformationBiCanonicalRegistryVersion::query()
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->whereIn(
                    'status',
                    [
                        DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_DRAFT,
                        DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_PUBLISHED,
                    ]
                )
                ->orderByRaw(
                    "CASE WHEN status = 'draft' THEN 0 ELSE 1 END"
                )
                ->orderByDesc('version')
                ->first();

        return [
            'registry' =>
                $registry !== null
                    ? $this->registryPayload(
                        $registry
                    )
                    : null,
        ];
    }

    /**
     * Resolve the immutable canonical model currently published
     * for this company.
     *
     * READ ONLY unless the caller explicitly requests a row lock
     * from inside an existing transaction.
     */
    public function publishedRegistry(
        TransformationImplementationRequest $request,
        User $actor,
        bool $lockForUpdate = false
    ): ?DataTransformationBiCanonicalRegistryVersion {
        $this->assertAdmin(
            $request,
            $actor
        );

        $query =
            DataTransformationBiCanonicalRegistryVersion::query()
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->where(
                    'status',
                    DataTransformationBiCanonicalRegistryVersion
                        ::STATUS_PUBLISHED
                )
                ->orderByDesc(
                    'version'
                );

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Mapping-facing published registry payload.
     *
     * Draft canonical models are intentionally invisible to mapping.
     *
     * @return array<string,mixed>|null
     */
    public function publishedWorkspace(
        TransformationImplementationRequest $request,
        User $actor
    ): ?array {
        $registry =
            $this->publishedRegistry(
                $request,
                $actor
            );

        return $registry !== null
            ? $this->registryPayload(
                $registry
            )
            : null;
    }

    /**
     * Explicitly prepare a mutable company-owned canonical model version.
     *
     * The first version starts empty. A later draft clones the last
     * published version so LAUDA can evolve the semantic model safely.
     */
    public function prepareDraft(
        TransformationImplementationRequest $request,
        User $actor
    ): DataTransformationBiCanonicalRegistryVersion {
        $this->assertAdmin(
            $request,
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $actor
            ): DataTransformationBiCanonicalRegistryVersion {
                $existingDraft =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->where(
                            'status',
                            DataTransformationBiCanonicalRegistryVersion
                                ::STATUS_DRAFT
                        )
                        ->orderByDesc('version')
                        ->lockForUpdate()
                        ->first();

                if ($existingDraft !== null) {
                    return $existingDraft;
                }

                $latest =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->orderByDesc('version')
                        ->lockForUpdate()
                        ->first();

                $nextVersion =
                    $latest !== null
                        ? (int) $latest->version + 1
                        : 1;

                $draft =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->create([
                            'company_id' =>
                                (int) $request->company_id,

                            'source_transformation_implementation_request_id' =>
                                (int) $request->getKey(),

                            'version' =>
                                $nextVersion,

                            'status' =>
                                DataTransformationBiCanonicalRegistryVersion
                                    ::STATUS_DRAFT,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'updated_by_user_id' =>
                                (int) $actor->getKey(),
                        ]);

                $published =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->where(
                            'status',
                            DataTransformationBiCanonicalRegistryVersion
                                ::STATUS_PUBLISHED
                        )
                        ->orderByDesc('version')
                        ->first();

                if ($published !== null) {
                    $this->clonePublishedIntoDraft(
                        $published,
                        $draft,
                        $actor
                    );
                }

                return $draft->fresh()
                    ?? $draft;
            }
        );
    }

    /**
     * Create one dynamic canonical entity in a draft model.
     *
     * @param array<string,mixed> $input
     */
    public function createEntity(
        TransformationImplementationRequest $request,
        DataTransformationBiCanonicalRegistryVersion $registry,
        array $input,
        User $actor
    ): DataTransformationBiCanonicalEntity {
        $this->assertAdmin(
            $request,
            $actor
        );

        $this->assertDraftScope(
            $request,
            $registry
        );

        $entityKey =
            $this->validatedKey(
                (string) (
                    $input['entity_key']
                    ?? ''
                ),
                'entity_key'
            );

        $label =
            $this->validatedLabel(
                (string) (
                    $input['label']
                    ?? ''
                ),
                'label'
            );

        $description =
            $this->nullableDescription(
                $input['description']
                ?? null,
                'description'
            );

        return DB::transaction(
            function () use (
                $request,
                $registry,
                $entityKey,
                $label,
                $description,
                $actor
            ): DataTransformationBiCanonicalEntity {
                $lockedRegistry =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->whereKey(
                            (int) $registry->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $lockedRegistry === null
                    || (string) $lockedRegistry->status
                        !== DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'canonical_registry' => [
                            'El modelo canónico ya no admite cambios.',
                        ],
                    ]);
                }

                $duplicate =
                    DataTransformationBiCanonicalEntity::query()
                        ->where(
                            'canonical_registry_version_id',
                            (int) $lockedRegistry->getKey()
                        )
                        ->where(
                            'entity_key',
                            $entityKey
                        )
                        ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'entity_key' => [
                            'Ya existe una entidad canónica con esa clave.',
                        ],
                    ]);
                }

                $nextSortOrder =
                    (
                        (int) (
                            DataTransformationBiCanonicalEntity::query()
                                ->where(
                                    'canonical_registry_version_id',
                                    (int) $lockedRegistry->getKey()
                                )
                                ->max('sort_order')
                            ?? -1
                        )
                    ) + 1;

                return DataTransformationBiCanonicalEntity::query()
                    ->create([
                        'canonical_registry_version_id' =>
                            (int) $lockedRegistry->getKey(),

                        'company_id' =>
                            (int) $request->company_id,

                        'entity_key' =>
                            $entityKey,

                        'label' =>
                            $label,

                        'description' =>
                            $description,

                        'status' =>
                            DataTransformationBiCanonicalEntity
                                ::STATUS_ACTIVE,

                        'sort_order' =>
                            $nextSortOrder,

                        'created_by_user_id' =>
                            (int) $actor->getKey(),

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),
                    ]);
            }
        );
    }

    /**
     * Replace the complete field definition of one draft entity.
     *
     * @param array<int,array<string,mixed>> $fields
     */
    public function replaceFields(
        TransformationImplementationRequest $request,
        DataTransformationBiCanonicalRegistryVersion $registry,
        DataTransformationBiCanonicalEntity $entity,
        array $fields,
        User $actor
    ): DataTransformationBiCanonicalEntity {
        $this->assertAdmin(
            $request,
            $actor
        );

        $this->assertDraftScope(
            $request,
            $registry
        );

        $this->assertEntityScope(
            $request,
            $registry,
            $entity
        );

        if ($fields === []) {
            throw ValidationException::withMessages([
                'fields' => [
                    'La entidad canónica debe contener al menos un campo.',
                ],
            ]);
        }

        $normalized =
            [];

        $seen =
            [];

        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                throw ValidationException::withMessages([
                    "fields.$index" => [
                        'Cada campo canónico debe ser un objeto válido.',
                    ],
                ]);
            }

            $fieldKey =
                $this->validatedKey(
                    (string) (
                        $field['field_key']
                        ?? ''
                    ),
                    "fields.$index.field_key"
                );

            if (isset($seen[$fieldKey])) {
                throw ValidationException::withMessages([
                    "fields.$index.field_key" => [
                        'La clave del campo canónico está repetida.',
                    ],
                ]);
            }

            $seen[$fieldKey] =
                true;

            $dataType =
                strtolower(
                    trim(
                        (string) (
                            $field['data_type']
                            ?? DataTransformationBiCanonicalField
                                ::TYPE_TEXT
                        )
                    )
                );

            if (
                ! in_array(
                    $dataType,
                    self::FIELD_TYPES,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "fields.$index.data_type" => [
                        'El tipo de dato canónico no está permitido.',
                    ],
                ]);
            }

            $normalized[] = [
                'field_key' =>
                    $fieldKey,

                'label' =>
                    $this->validatedLabel(
                        (string) (
                            $field['label']
                            ?? $fieldKey
                        ),
                        "fields.$index.label"
                    ),

                'data_type' =>
                    $dataType,

                'required' =>
                    (bool) (
                        $field['required']
                        ?? false
                    ),

                'is_identity' =>
                    (bool) (
                        $field['is_identity']
                        ?? false
                    ),

                'description' =>
                    $this->nullableDescription(
                        $field['description']
                        ?? null,
                        "fields.$index.description"
                    ),
            ];
        }

        return DB::transaction(
            function () use (
                $request,
                $registry,
                $entity,
                $normalized,
                $actor
            ): DataTransformationBiCanonicalEntity {
                $lockedRegistry =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->whereKey(
                            (int) $registry->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $lockedRegistry === null
                    || (string) $lockedRegistry->status
                        !== DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'canonical_registry' => [
                            'El modelo canónico ya no admite cambios.',
                        ],
                    ]);
                }

                $lockedEntity =
                    DataTransformationBiCanonicalEntity::query()
                        ->whereKey(
                            (int) $entity->getKey()
                        )
                        ->where(
                            'canonical_registry_version_id',
                            (int) $lockedRegistry->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($lockedEntity === null) {
                    throw new AuthorizationException(
                        'La entidad canónica no pertenece a este modelo.'
                    );
                }

                /*
                 * Relationships are model-owned definitions. Replacing fields
                 * invalidates relationships involving this entity so they can
                 * be explicitly rebuilt against the new field set.
                 */
                DataTransformationBiCanonicalRelationship::query()
                    ->where(
                        'canonical_registry_version_id',
                        (int) $lockedRegistry->getKey()
                    )
                    ->where(
                        function ($query) use ($lockedEntity): void {
                            $query
                                ->where(
                                    'from_canonical_entity_id',
                                    (int) $lockedEntity->getKey()
                                )
                                ->orWhere(
                                    'to_canonical_entity_id',
                                    (int) $lockedEntity->getKey()
                                );
                        }
                    )
                    ->delete();

                $lockedEntity
                    ->fields()
                    ->delete();

                foreach (
                    $normalized
                    as $sortOrder => $field
                ) {
                    DataTransformationBiCanonicalField::query()
                        ->create([
                            'canonical_entity_id' =>
                                (int) $lockedEntity->getKey(),

                            'field_key' =>
                                $field['field_key'],

                            'label' =>
                                $field['label'],

                            'data_type' =>
                                $field['data_type'],

                            'required' =>
                                $field['required'],

                            'is_identity' =>
                                $field['is_identity'],

                            'description' =>
                                $field['description'],

                            'status' =>
                                DataTransformationBiCanonicalField
                                    ::STATUS_ACTIVE,

                            'sort_order' =>
                                $sortOrder,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'updated_by_user_id' =>
                                (int) $actor->getKey(),
                        ]);
                }

                $lockedEntity->forceFill([
                    'updated_by_user_id' =>
                        (int) $actor->getKey(),
                ])->save();

                return $lockedEntity->fresh(
                    'fields'
                ) ?? $lockedEntity;
            }
        );
    }

    /**
     * @param array<int,array<string,mixed>> $relationships
     */
    public function replaceRelationships(
        TransformationImplementationRequest $request,
        DataTransformationBiCanonicalRegistryVersion $registry,
        array $relationships,
        User $actor
    ): DataTransformationBiCanonicalRegistryVersion {
        $this->assertAdmin(
            $request,
            $actor
        );

        $this->assertDraftScope(
            $request,
            $registry
        );

        return DB::transaction(
            function () use (
                $request,
                $registry,
                $relationships,
                $actor
            ): DataTransformationBiCanonicalRegistryVersion {
                $lockedRegistry =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->whereKey(
                            (int) $registry->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $lockedRegistry === null
                    || (string) $lockedRegistry->status
                        !== DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'canonical_registry' => [
                            'El modelo canónico ya no admite cambios.',
                        ],
                    ]);
                }

                $entities =
                    DataTransformationBiCanonicalEntity::query()
                        ->where(
                            'canonical_registry_version_id',
                            (int) $lockedRegistry->getKey()
                        )
                        ->where(
                            'status',
                            DataTransformationBiCanonicalEntity
                                ::STATUS_ACTIVE
                        )
                        ->with([
                            'fields' => function ($query): void {
                                $query->where(
                                    'status',
                                    DataTransformationBiCanonicalField
                                        ::STATUS_ACTIVE
                                );
                            },
                        ])
                        ->get()
                        ->keyBy('entity_key');

                $resolved =
                    [];

                $seen =
                    [];

                foreach (
                    $relationships
                    as $index => $relationship
                ) {
                    if (! is_array($relationship)) {
                        throw ValidationException::withMessages([
                            "relationships.$index" => [
                                'Cada relación debe ser un objeto válido.',
                            ],
                        ]);
                    }

                    $fromEntityKey =
                        $this->validatedKey(
                            (string) (
                                $relationship['from_entity_key']
                                ?? ''
                            ),
                            "relationships.$index.from_entity_key"
                        );

                    $fromFieldKey =
                        $this->validatedKey(
                            (string) (
                                $relationship['from_field_key']
                                ?? ''
                            ),
                            "relationships.$index.from_field_key"
                        );

                    $toEntityKey =
                        $this->validatedKey(
                            (string) (
                                $relationship['to_entity_key']
                                ?? ''
                            ),
                            "relationships.$index.to_entity_key"
                        );

                    $toFieldKey =
                        $this->validatedKey(
                            (string) (
                                $relationship['to_field_key']
                                ?? ''
                            ),
                            "relationships.$index.to_field_key"
                        );

                    $type =
                        strtolower(
                            trim(
                                (string) (
                                    $relationship['relationship_type']
                                    ?? DataTransformationBiCanonicalRelationship
                                        ::TYPE_MANY_TO_ONE
                                )
                            )
                        );

                    if (
                        ! in_array(
                            $type,
                            self::RELATIONSHIP_TYPES,
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            "relationships.$index.relationship_type" => [
                                'El tipo de relación canónica no está permitido.',
                            ],
                        ]);
                    }

                    $fromEntity =
                        $entities->get(
                            $fromEntityKey
                        );

                    $toEntity =
                        $entities->get(
                            $toEntityKey
                        );

                    if (
                        $fromEntity === null
                        || $toEntity === null
                    ) {
                        throw ValidationException::withMessages([
                            "relationships.$index" => [
                                'La relación referencia una entidad canónica inexistente.',
                            ],
                        ]);
                    }

                    $fromField =
                        $fromEntity
                            ->fields
                            ->firstWhere(
                                'field_key',
                                $fromFieldKey
                            );

                    $toField =
                        $toEntity
                            ->fields
                            ->firstWhere(
                                'field_key',
                                $toFieldKey
                            );

                    if (
                        $fromField === null
                        || $toField === null
                    ) {
                        throw ValidationException::withMessages([
                            "relationships.$index" => [
                                'La relación referencia un campo canónico inexistente.',
                            ],
                        ]);
                    }

                    $signature =
                        implode(
                            '|',
                            [
                                $fromEntityKey,
                                $fromFieldKey,
                                $toEntityKey,
                                $toFieldKey,
                                $type,
                            ]
                        );

                    if (isset($seen[$signature])) {
                        throw ValidationException::withMessages([
                            "relationships.$index" => [
                                'La relación canónica está repetida.',
                            ],
                        ]);
                    }

                    $seen[$signature] =
                        true;

                    $resolved[] = [
                        'from_entity_id' =>
                            (int) $fromEntity->getKey(),

                        'from_field_id' =>
                            (int) $fromField->getKey(),

                        'to_entity_id' =>
                            (int) $toEntity->getKey(),

                        'to_field_id' =>
                            (int) $toField->getKey(),

                        'relationship_type' =>
                            $type,

                        'label' =>
                            isset($relationship['label'])
                                ? $this->nullableLabel(
                                    $relationship['label'],
                                    "relationships.$index.label"
                                )
                                : null,

                        'description' =>
                            $this->nullableDescription(
                                $relationship['description']
                                ?? null,
                                "relationships.$index.description"
                            ),
                    ];
                }

                $lockedRegistry
                    ->relationships()
                    ->delete();

                foreach (
                    $resolved
                    as $sortOrder => $relationship
                ) {
                    DataTransformationBiCanonicalRelationship::query()
                        ->create([
                            'canonical_registry_version_id' =>
                                (int) $lockedRegistry->getKey(),

                            'company_id' =>
                                (int) $request->company_id,

                            'from_canonical_entity_id' =>
                                $relationship['from_entity_id'],

                            'from_canonical_field_id' =>
                                $relationship['from_field_id'],

                            'to_canonical_entity_id' =>
                                $relationship['to_entity_id'],

                            'to_canonical_field_id' =>
                                $relationship['to_field_id'],

                            'relationship_type' =>
                                $relationship['relationship_type'],

                            'label' =>
                                $relationship['label'],

                            'description' =>
                                $relationship['description'],

                            'status' =>
                                DataTransformationBiCanonicalRelationship
                                    ::STATUS_ACTIVE,

                            'sort_order' =>
                                $sortOrder,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'updated_by_user_id' =>
                                (int) $actor->getKey(),
                        ]);
                }

                return $lockedRegistry->fresh()
                    ?? $lockedRegistry;
            }
        );
    }

    /**
     * Publishing freezes one semantic model version.
     *
     * Mapping will later pin to this company-scoped integer version.
     */
    public function publish(
        TransformationImplementationRequest $request,
        DataTransformationBiCanonicalRegistryVersion $registry,
        User $actor
    ): DataTransformationBiCanonicalRegistryVersion {
        $this->assertAdmin(
            $request,
            $actor
        );

        $this->assertDraftScope(
            $request,
            $registry
        );

        return DB::transaction(
            function () use (
                $request,
                $registry,
                $actor
            ): DataTransformationBiCanonicalRegistryVersion {
                $locked =
                    DataTransformationBiCanonicalRegistryVersion::query()
                        ->whereKey(
                            (int) $registry->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $locked === null
                    || (string) $locked->status
                        !== DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'canonical_registry' => [
                            'El modelo canónico ya no está en borrador.',
                        ],
                    ]);
                }

                $entities =
                    DataTransformationBiCanonicalEntity::query()
                        ->where(
                            'canonical_registry_version_id',
                            (int) $locked->getKey()
                        )
                        ->where(
                            'status',
                            DataTransformationBiCanonicalEntity
                                ::STATUS_ACTIVE
                        )
                        ->with([
                            'fields' => function ($query): void {
                                $query->where(
                                    'status',
                                    DataTransformationBiCanonicalField
                                        ::STATUS_ACTIVE
                                );
                            },
                        ])
                        ->get();

                if ($entities->isEmpty()) {
                    throw ValidationException::withMessages([
                        'canonical_registry' => [
                            'Define al menos una entidad canónica antes de publicar el modelo.',
                        ],
                    ]);
                }

                foreach ($entities as $entity) {
                    if ($entity->fields->isEmpty()) {
                        throw ValidationException::withMessages([
                            'canonical_registry' => [
                                "La entidad {$entity->entity_key} no tiene campos.",
                            ],
                        ]);
                    }

                    if (
                        ! $entity
                            ->fields
                            ->contains(
                                fn (
                                    DataTransformationBiCanonicalField $field
                                ): bool =>
                                    (bool) $field->is_identity
                            )
                    ) {
                        throw ValidationException::withMessages([
                            'canonical_registry' => [
                                "La entidad {$entity->entity_key} necesita al menos un campo de identidad.",
                            ],
                        ]);
                    }
                }

                DataTransformationBiCanonicalRegistryVersion::query()
                    ->where(
                        'company_id',
                        (int) $request->company_id
                    )
                    ->where(
                        'status',
                        DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_PUBLISHED
                    )
                    ->whereKeyNot(
                        (int) $locked->getKey()
                    )
                    ->update([
                        'status' =>
                            DataTransformationBiCanonicalRegistryVersion
                                ::STATUS_RETIRED,

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),
                    ]);

                $locked->forceFill([
                    'status' =>
                        DataTransformationBiCanonicalRegistryVersion
                            ::STATUS_PUBLISHED,

                    'published_by_user_id' =>
                        (int) $actor->getKey(),

                    'published_at' =>
                        now(),

                    'updated_by_user_id' =>
                        (int) $actor->getKey(),
                ])->save();

                return $locked->fresh()
                    ?? $locked;
            }
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function registryPayload(
        DataTransformationBiCanonicalRegistryVersion $registry
    ): array {
        $registry->loadMissing([
            'entities' => function ($query): void {
                $query
                    ->where(
                        'status',
                        DataTransformationBiCanonicalEntity
                            ::STATUS_ACTIVE
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
            'entities.fields' => function ($query): void {
                $query
                    ->where(
                        'status',
                        DataTransformationBiCanonicalField
                            ::STATUS_ACTIVE
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
            'relationships' => function ($query): void {
                $query
                    ->where(
                        'status',
                        DataTransformationBiCanonicalRelationship
                            ::STATUS_ACTIVE
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
            'relationships.fromEntity',
            'relationships.fromField',
            'relationships.toEntity',
            'relationships.toField',
        ]);

        return [
            'id' =>
                (int) $registry->getKey(),

            'company_id' =>
                (int) $registry->company_id,

            'version' =>
                (int) $registry->version,

            'status' =>
                (string) $registry->status,

            'published_at' =>
                $registry->published_at?->toISOString(),

            'entities' =>
                $registry
                    ->entities
                    ->map(
                        static function (
                            DataTransformationBiCanonicalEntity $entity
                        ): array {
                            return [
                                'id' =>
                                    (int) $entity->getKey(),

                                'key' =>
                                    (string) $entity->entity_key,

                                'label' =>
                                    (string) $entity->label,

                                'description' =>
                                    $entity->description,

                                'fields' =>
                                    $entity
                                        ->fields
                                        ->mapWithKeys(
                                            static function (
                                                DataTransformationBiCanonicalField $field
                                            ): array {
                                                return [
                                                    (string) $field->field_key => [
                                                        'id' =>
                                                            (int) $field->getKey(),

                                                        'key' =>
                                                            (string) $field->field_key,

                                                        'label' =>
                                                            (string) $field->label,

                                                        'type' =>
                                                            (string) $field->data_type,

                                                        'required' =>
                                                            (bool) $field->required,

                                                        'is_identity' =>
                                                            (bool) $field->is_identity,

                                                        'description' =>
                                                            $field->description,
                                                    ],
                                                ];
                                            }
                                        )
                                        ->all(),
                            ];
                        }
                    )
                    ->values()
                    ->all(),

            'relationships' =>
                $registry
                    ->relationships
                    ->map(
                        static function (
                            DataTransformationBiCanonicalRelationship $relationship
                        ): array {
                            return [
                                'from_entity_key' =>
                                    (string) $relationship
                                        ->fromEntity
                                        ->entity_key,

                                'from_field_key' =>
                                    (string) $relationship
                                        ->fromField
                                        ->field_key,

                                'to_entity_key' =>
                                    (string) $relationship
                                        ->toEntity
                                        ->entity_key,

                                'to_field_key' =>
                                    (string) $relationship
                                        ->toField
                                        ->field_key,

                                'relationship_type' =>
                                    (string) $relationship
                                        ->relationship_type,

                                'label' =>
                                    $relationship->label,

                                'description' =>
                                    $relationship->description,
                            ];
                        }
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function clonePublishedIntoDraft(
        DataTransformationBiCanonicalRegistryVersion $published,
        DataTransformationBiCanonicalRegistryVersion $draft,
        User $actor
    ): void {
        $published->load([
            'entities.fields',
            'relationships',
        ]);

        $entityMap =
            [];

        $fieldMap =
            [];

        foreach (
            $published
                ->entities
                ->sortBy('sort_order')
            as $sourceEntity
        ) {
            $entity =
                DataTransformationBiCanonicalEntity::query()
                    ->create([
                        'canonical_registry_version_id' =>
                            (int) $draft->getKey(),

                        'company_id' =>
                            (int) $draft->company_id,

                        'entity_key' =>
                            (string) $sourceEntity->entity_key,

                        'label' =>
                            (string) $sourceEntity->label,

                        'description' =>
                            $sourceEntity->description,

                        'status' =>
                            (string) $sourceEntity->status,

                        'sort_order' =>
                            (int) $sourceEntity->sort_order,

                        'created_by_user_id' =>
                            (int) $actor->getKey(),

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),
                    ]);

            $entityMap[
                (int) $sourceEntity->getKey()
            ] =
                (int) $entity->getKey();

            foreach (
                $sourceEntity
                    ->fields
                    ->sortBy('sort_order')
                as $sourceField
            ) {
                $field =
                    DataTransformationBiCanonicalField::query()
                        ->create([
                            'canonical_entity_id' =>
                                (int) $entity->getKey(),

                            'field_key' =>
                                (string) $sourceField->field_key,

                            'label' =>
                                (string) $sourceField->label,

                            'data_type' =>
                                (string) $sourceField->data_type,

                            'required' =>
                                (bool) $sourceField->required,

                            'is_identity' =>
                                (bool) $sourceField->is_identity,

                            'description' =>
                                $sourceField->description,

                            'status' =>
                                (string) $sourceField->status,

                            'sort_order' =>
                                (int) $sourceField->sort_order,

                            'created_by_user_id' =>
                                (int) $actor->getKey(),

                            'updated_by_user_id' =>
                                (int) $actor->getKey(),
                        ]);

                $fieldMap[
                    (int) $sourceField->getKey()
                ] =
                    (int) $field->getKey();
            }
        }

        foreach (
            $published
                ->relationships
                ->sortBy('sort_order')
            as $sourceRelationship
        ) {
            $fromEntityId =
                $entityMap[
                    (int) $sourceRelationship
                        ->from_canonical_entity_id
                ]
                ?? null;

            $toEntityId =
                $entityMap[
                    (int) $sourceRelationship
                        ->to_canonical_entity_id
                ]
                ?? null;

            $fromFieldId =
                $fieldMap[
                    (int) $sourceRelationship
                        ->from_canonical_field_id
                ]
                ?? null;

            $toFieldId =
                $fieldMap[
                    (int) $sourceRelationship
                        ->to_canonical_field_id
                ]
                ?? null;

            if (
                $fromEntityId === null
                || $toEntityId === null
                || $fromFieldId === null
                || $toFieldId === null
            ) {
                continue;
            }

            DataTransformationBiCanonicalRelationship::query()
                ->create([
                    'canonical_registry_version_id' =>
                        (int) $draft->getKey(),

                    'company_id' =>
                        (int) $draft->company_id,

                    'from_canonical_entity_id' =>
                        $fromEntityId,

                    'from_canonical_field_id' =>
                        $fromFieldId,

                    'to_canonical_entity_id' =>
                        $toEntityId,

                    'to_canonical_field_id' =>
                        $toFieldId,

                    'relationship_type' =>
                        (string) $sourceRelationship
                            ->relationship_type,

                    'label' =>
                        $sourceRelationship->label,

                    'description' =>
                        $sourceRelationship->description,

                    'status' =>
                        (string) $sourceRelationship->status,

                    'sort_order' =>
                        (int) $sourceRelationship->sort_order,

                    'created_by_user_id' =>
                        (int) $actor->getKey(),

                    'updated_by_user_id' =>
                        (int) $actor->getKey(),
                ]);
        }
    }

    private function assertAdmin(
        TransformationImplementationRequest $request,
        User $actor
    ): void {
        $this->authorization
            ->assertCanManage(
                $request,
                $actor
            );

        if ((string) $actor->role !== 'admin') {
            throw new AuthorizationException(
                'El modelo canónico corresponde a Admin LAUDA.'
            );
        }

        if (
            ! $request->exists
            || (int) $request->getKey() <= 0
            || (string) $request->capability_key
                !== 'data_transformation_bi'
        ) {
            throw new AuthorizationException(
                'La solicitud no pertenece a Transformación de Datos para BI.'
            );
        }
    }

    private function assertDraftScope(
        TransformationImplementationRequest $request,
        DataTransformationBiCanonicalRegistryVersion $registry
    ): void {
        if (
            ! $registry->exists
            || (int) $registry->company_id
                !== (int) $request->company_id
        ) {
            throw new AuthorizationException(
                'El modelo canónico no pertenece a esta empresa.'
            );
        }

        if (
            (string) $registry->status
                !== DataTransformationBiCanonicalRegistryVersion
                    ::STATUS_DRAFT
        ) {
            throw ValidationException::withMessages([
                'canonical_registry' => [
                    'Solo una versión en borrador puede modificarse.',
                ],
            ]);
        }
    }

    private function assertEntityScope(
        TransformationImplementationRequest $request,
        DataTransformationBiCanonicalRegistryVersion $registry,
        DataTransformationBiCanonicalEntity $entity
    ): void {
        if (
            ! $entity->exists
            || (int) $entity->company_id
                !== (int) $request->company_id
            || (int) $entity->canonical_registry_version_id
                !== (int) $registry->getKey()
        ) {
            throw new AuthorizationException(
                'La entidad canónica no pertenece a este modelo.'
            );
        }
    }

    private function validatedKey(
        string $value,
        string $field
    ): string {
        $value =
            trim(
                strtolower(
                    $value
                )
            );

        if (
            $value === ''
            || preg_match(
                self::KEY_PATTERN,
                $value
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'La clave debe usar minúsculas, números y guion bajo, comenzando por una letra.',
                ],
            ]);
        }

        return $value;
    }

    private function validatedLabel(
        string $value,
        string $field
    ): string {
        $value =
            trim(
                $value
            );

        if (
            $value === ''
            || mb_strlen($value)
                > self::MAX_LABEL_LENGTH
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'El nombre es obligatorio y no puede exceder 191 caracteres.',
                ],
            ]);
        }

        return $value;
    }

    private function nullableLabel(
        mixed $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
                > self::MAX_LABEL_LENGTH
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'El nombre no puede exceder 191 caracteres.',
                ],
            ]);
        }

        return $value;
    }

    private function nullableDescription(
        mixed $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
                > self::MAX_DESCRIPTION_LENGTH
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'La descripción no puede exceder 4000 caracteres.',
                ],
            ]);
        }

        return $value;
    }
}
