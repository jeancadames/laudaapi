<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFieldMapping;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\DataTransformationBiSourceAssetMapping;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DataTransformationBiSourceAssetMappingService
{
    private const KEY_PATTERN =
        '/^[a-z][a-z0-9_]{0,99}$/';

    public function __construct(
        private readonly DataTransformationBiIntakeActorAuthorizationService
            $authorization,

        private readonly DataTransformationBiCanonicalRegistry
            $canonicalRegistry
    ) {
    }

    /**
     * Build the Admin LAUDA mapping workspace for one profiled source.
     *
     * READ ONLY:
     * - does not create a mapping;
     * - does not update mapping state;
     * - does not materialize or normalize data;
     * - exposes structural/profile metadata only, never source values.
     *
     * @return array<string,mixed>
     */
    public function workspace(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        User $actor
    ): array {
        $this->assertAdminScope(
            $implementationRequest,
            $session,
            $asset,
            $actor
        );

        $profile =
            is_array(
                $asset->profiling_snapshot
            )
                ? $asset->profiling_snapshot
                : [];

        $profileSheets =
            is_array(
                $profile['sheets']
                ?? null
            )
                ? $profile['sheets']
                : [];

        $firstSheetIndex =
            null;

        foreach ($profileSheets as $candidate) {
            if (
                is_array($candidate)
                && array_key_exists(
                    'index',
                    $candidate
                )
            ) {
                $firstSheetIndex =
                    (int) $candidate['index'];

                break;
            }
        }

        if ($firstSheetIndex === null) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'El profiling técnico no contiene hojas disponibles para mapear.',
                ],
            ]);
        }

        /*
         * Reuse the same integrity gate as write operations so the workspace
         * is never built from an obsolete profile/artifact combination.
         */
        $context =
            $this->currentProfileContext(
                $implementationRequest,
                $asset,
                $firstSheetIndex
            );

        $currentProfile =
            $context['profile'];

        $sheets =
            is_array(
                $currentProfile['sheets']
                ?? null
            )
                ? $currentProfile['sheets']
                : [];

        $safeSheets =
            [];

        foreach ($sheets as $sheet) {
            if (
                ! is_array($sheet)
                || ! array_key_exists(
                    'index',
                    $sheet
                )
            ) {
                continue;
            }

            $safeColumns =
                [];

            $columns =
                is_array(
                    $sheet['columns']
                    ?? null
                )
                    ? $sheet['columns']
                    : [];

            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }

                $columnKey =
                    trim(
                        (string) (
                            $column['key']
                            ?? ''
                        )
                    );

                if ($columnKey === '') {
                    continue;
                }

                $safeColumns[] = [
                    'key' =>
                        $columnKey,

                    'index' =>
                        isset($column['index'])
                            ? (int) $column['index']
                            : null,

                    'header' =>
                        array_key_exists(
                            'header',
                            $column
                        )
                        && $column['header'] !== null
                            ? (string) $column['header']
                            : null,

                    'primitive_types' =>
                        is_array(
                            $column['primitive_types']
                            ?? null
                        )
                            ? $column['primitive_types']
                            : [],

                    'empty_count' =>
                        isset($column['empty_count'])
                            ? (int) $column['empty_count']
                            : null,

                    'non_empty_count' =>
                        isset($column['non_empty_count'])
                            ? (int) $column['non_empty_count']
                            : null,

                    'empty_ratio' =>
                        isset($column['empty_ratio'])
                            ? (float) $column['empty_ratio']
                            : null,

                    'non_empty_ratio' =>
                        isset($column['non_empty_ratio'])
                            ? (float) $column['non_empty_ratio']
                            : null,

                    'min_length' =>
                        isset($column['min_length'])
                            ? (int) $column['min_length']
                            : null,

                    'max_length' =>
                        isset($column['max_length'])
                            ? (int) $column['max_length']
                            : null,

                    'profiled_value_count' =>
                        isset($column['profiled_value_count'])
                            ? (int) $column['profiled_value_count']
                            : null,
                ];
            }

            $safeSheets[] = [
                'index' =>
                    (int) $sheet['index'],

                'name' =>
                    isset($sheet['name'])
                    && $sheet['name'] !== null
                        ? (string) $sheet['name']
                        : null,

                'source_row_count' =>
                    isset($sheet['source_row_count'])
                        ? (int) $sheet['source_row_count']
                        : null,

                'profiled_row_count' =>
                    isset($sheet['profiled_row_count'])
                        ? (int) $sheet['profiled_row_count']
                        : null,

                'column_count' =>
                    count(
                        $safeColumns
                    ),

                'columns' =>
                    $safeColumns,
            ];
        }

        $mappings =
            DataTransformationBiSourceAssetMapping::query()
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->where(
                    'data_transformation_bi_source_asset_id',
                    (int) $asset->getKey()
                )
                ->with([
                    'fieldMappings' =>
                        static function ($query): void {
                            $query
                                ->orderBy(
                                    'canonical_field_key'
                                )
                                ->orderBy(
                                    'id'
                                );
                        },
                ])
                ->orderBy(
                    'canonical_entity_key'
                )
                ->orderBy(
                    'source_sheet_index'
                )
                ->orderByDesc(
                    'mapping_version'
                )
                ->get();

        return [
            'canonical_registry' => [
                'version' =>
                    $this->canonicalRegistry
                        ->version(),

                'entities' =>
                    array_values(
                        $this->canonicalRegistry
                            ->entities()
                    ),

                'relationships' =>
                    $this->canonicalRegistry
                        ->relationships(),
            ],

            'source' => [
                'id' =>
                    (int) $asset->getKey(),

                'display_name' =>
                    (string) $asset->display_name,

                'source_object_name' =>
                    (string) $asset->source_object_name,

                'profile_version' =>
                    (int) (
                        $currentProfile['version']
                        ?? 0
                    ),

                'sheets' =>
                    $safeSheets,
            ],

            'mappings' =>
                $mappings
                    ->map(
                        fn (
                            DataTransformationBiSourceAssetMapping $mapping
                        ): array =>
                            $this->mappingPayload(
                                $mapping
                            )
                    )
                    ->values()
                    ->all(),
        ];
    }

    public function startDraft(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        string $canonicalEntityKey,
        int $sourceSheetIndex,
        User $actor
    ): DataTransformationBiSourceAssetMapping {
        $this->assertAdminScope(
            $implementationRequest,
            $session,
            $asset,
            $actor
        );

        $canonicalEntityKey =
            $this->validatedCanonicalKey(
                $canonicalEntityKey,
                'canonical_entity_key'
            );

        if (
            ! $this->canonicalRegistry
                ->supportsEntity(
                    $canonicalEntityKey
                )
        ) {
            throw ValidationException::withMessages([
                'canonical_entity_key' => [
                    'La entidad objetivo no existe en el '
                    .'modelo canónico vigente de LAUDA.',
                ],
            ]);
        }

        $currentRegistryVersion =
            $this->canonicalRegistry
                ->version();

        if ($sourceSheetIndex < 0) {
            throw ValidationException::withMessages([
                'source_sheet_index' => [
                    'El índice de hoja no puede ser negativo.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $asset,
                $canonicalEntityKey,
                $currentRegistryVersion,
                $sourceSheetIndex,
                $actor
            ): DataTransformationBiSourceAssetMapping {
                $lockedAsset =
                    DataTransformationBiSourceAsset::query()
                        ->whereKey(
                            (int) $asset->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $implementationRequest->company_id
                        )
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            (int) $session->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($lockedAsset === null) {
                    throw new AuthorizationException(
                        'La fuente no pertenece a esta solicitud y sesión.'
                    );
                }

                [
                    'artifact' => $artifact,
                    'profile' => $profile,
                    'sheet' => $sheet,
                ] =
                    $this->currentProfileContext(
                        $implementationRequest,
                        $lockedAsset,
                        $sourceSheetIndex,
                        true
                    );

                $sourceSha256 =
                    strtolower(
                        (string) $artifact->source_sha256
                    );

                $existing =
                    DataTransformationBiSourceAssetMapping::query()
                        ->where(
                            'data_transformation_bi_source_asset_id',
                            (int) $lockedAsset->getKey()
                        )
                        ->where(
                            'canonical_entity_key',
                            $canonicalEntityKey
                        )
                        ->where(
                            'canonical_registry_version',
                            $currentRegistryVersion
                        )
                        ->where(
                            'source_sheet_index',
                            $sourceSheetIndex
                        )
                        ->where(
                            'source_sha256',
                            $sourceSha256
                        )
                        ->orderByDesc(
                            'mapping_version'
                        )
                        ->lockForUpdate()
                        ->first();

                /*
                 * Re-entering the workspace must be idempotent while the
                 * latest mapping for this exact artifact is still editable.
                 *
                 * Once validated, startDraft() opens a new semantic version
                 * even though the source bytes/SHA have not changed.
                 */
                if (
                    $existing !== null
                    && in_array(
                        (string) $existing->status,
                        [
                            DataTransformationBiSourceAssetMapping
                                ::STATUS_DRAFT,

                            DataTransformationBiSourceAssetMapping
                                ::STATUS_READY,

                            DataTransformationBiSourceAssetMapping
                                ::STATUS_BLOCKED,
                        ],
                        true
                    )
                ) {
                    return $existing;
                }

                /*
                 * Older mappings for the same logical source/entity/sheet are
                 * historical once a different artifact SHA is current.
                 */
                DataTransformationBiSourceAssetMapping::query()
                    ->where(
                        'data_transformation_bi_source_asset_id',
                        (int) $lockedAsset->getKey()
                    )
                    ->where(
                        'canonical_entity_key',
                        $canonicalEntityKey
                    )
                    ->where(
                        'source_sheet_index',
                        $sourceSheetIndex
                    )
                    ->where(
                        function ($query) use (
                            $sourceSha256,
                            $currentRegistryVersion
                        ): void {
                            $query
                                ->where(
                                    'source_sha256',
                                    '<>',
                                    $sourceSha256
                                )
                                ->orWhere(
                                    'canonical_registry_version',
                                    '<>',
                                    $currentRegistryVersion
                                );
                        }
                    )
                    ->where(
                        'status',
                        '<>',
                        DataTransformationBiSourceAssetMapping::STATUS_STALE
                    )
                    ->update([
                        'status' =>
                            DataTransformationBiSourceAssetMapping
                                ::STATUS_STALE,

                        'validated_at' =>
                            null,

                        'validated_by_user_id' =>
                            null,

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),

                        'updated_at' =>
                            now(),
                    ]);

                $mappingVersion =
                    (
                        (int) (
                            DataTransformationBiSourceAssetMapping::query()
                                ->where(
                                    'data_transformation_bi_source_asset_id',
                                    (int) $lockedAsset->getKey()
                                )
                                ->where(
                                    'canonical_entity_key',
                                    $canonicalEntityKey
                                )
                                ->where(
                                    'source_sheet_index',
                                    $sourceSheetIndex
                                )
                                ->max(
                                    'mapping_version'
                                )
                            ?? 0
                        )
                    )
                    + 1;

                $mapping =
                    new DataTransformationBiSourceAssetMapping();

                $mapping->forceFill([
                    'data_transformation_bi_source_asset_id' =>
                        (int) $lockedAsset->getKey(),

                    'data_transformation_bi_source_asset_file_id' =>
                        (int) $artifact->getKey(),

                    'data_transformation_bi_intake_session_id' =>
                        (int) $session->getKey(),

                    'company_id' =>
                        (int) $implementationRequest->company_id,

                    'canonical_entity_key' =>
                        $canonicalEntityKey,

                    'canonical_registry_version' =>
                        $currentRegistryVersion,

                    'source_sha256' =>
                        $sourceSha256,

                    'source_profile_version' =>
                        (int) (
                            $profile['version']
                            ?? 1
                        ),

                    'source_sheet_index' =>
                        $sourceSheetIndex,

                    'source_sheet_name' =>
                        isset($sheet['name'])
                        && is_string($sheet['name'])
                            ? $sheet['name']
                            : null,

                    'mapping_version' =>
                        $mappingVersion,

                    'status' =>
                        DataTransformationBiSourceAssetMapping::STATUS_DRAFT,

                    'created_by_user_id' =>
                        (int) $actor->getKey(),

                    'updated_by_user_id' =>
                        (int) $actor->getKey(),
                ]);

                $mapping->save();

                return $mapping;
            }
        );
    }

    /**
     * Replace all target-field decisions of one draft mapping.
     *
     * @param array<int,array<string,mixed>> $decisions
     */
    public function replaceFieldMappings(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        DataTransformationBiSourceAssetMapping $mapping,
        array $decisions,
        User $actor
    ): DataTransformationBiSourceAssetMapping {
        $this->assertAdminScope(
            $implementationRequest,
            $session,
            $asset,
            $actor
        );

        $this->assertMappingScope(
            $implementationRequest,
            $session,
            $asset,
            $mapping
        );

        $this->assertCurrentCanonicalRegistry(
            $mapping
        );

        if (
            (string) $mapping->status
                === DataTransformationBiSourceAssetMapping::STATUS_STALE
        ) {
            throw ValidationException::withMessages([
                'mapping' => [
                    'El mapeo pertenece a una versión anterior del archivo fuente.',
                ],
            ]);
        }

        if (
            (string) $mapping->status
                === DataTransformationBiSourceAssetMapping::STATUS_VALIDATED
        ) {
            throw ValidationException::withMessages([
                'mapping' => [
                    'Un mapeo validado no puede reemplazarse sin abrir una nueva versión.',
                ],
            ]);
        }

        $context =
            $this->currentProfileContext(
                $implementationRequest,
                $asset,
                (int) $mapping->source_sheet_index
            );

        $artifact =
            $context['artifact'];

        if (
            (int) $mapping->data_transformation_bi_source_asset_file_id
                !== (int) $artifact->getKey()
            || (int) $mapping->source_profile_version
                !== (int) (
                    $context['profile']['version']
                    ?? 0
                )
            || ! hash_equals(
                strtolower(
                    (string) $mapping->source_sha256
                ),
                strtolower(
                    (string) $artifact->source_sha256
                )
            )
        ) {
            throw ValidationException::withMessages([
                'mapping' => [
                    'El mapeo ya no corresponde al archivo fuente vigente.',
                ],
            ]);
        }

        $columnsByKey =
            $this->columnsByKey(
                $context['sheet']
            );

        $normalized =
            [];

        $targetKeys =
            [];

        foreach ($decisions as $index => $decision) {
            if (! is_array($decision)) {
                throw ValidationException::withMessages([
                    "decisions.$index" => [
                        'Cada decisión de mapeo debe ser un objeto válido.',
                    ],
                ]);
            }

            $canonicalFieldKey =
                $this->validatedCanonicalKey(
                    (string) (
                        $decision['canonical_field_key']
                        ?? ''
                    ),
                    "decisions.$index.canonical_field_key"
                );

            if (
                ! $this->canonicalRegistry
                    ->supportsField(
                        (string) $mapping->canonical_entity_key,
                        $canonicalFieldKey
                    )
            ) {
                throw ValidationException::withMessages([
                    "decisions.$index.canonical_field_key" => [
                        'El campo objetivo no pertenece a la '
                        .'entidad canónica seleccionada.',
                    ],
                ]);
            }

            if (isset($targetKeys[$canonicalFieldKey])) {
                throw ValidationException::withMessages([
                    "decisions.$index.canonical_field_key" => [
                        'El campo canónico está repetido en el mapeo.',
                    ],
                ]);
            }

            $targetKeys[$canonicalFieldKey] =
                true;

            $mappingType =
                trim(
                    (string) (
                        $decision['mapping_type']
                        ?? ''
                    )
                );

            if (
                ! in_array(
                    $mappingType,
                    [
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DIRECT,

                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DEFAULT,

                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_TRANSFORM,

                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_UNMAPPED,
                    ],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    "decisions.$index.mapping_type" => [
                        'El tipo de mapeo no es válido.',
                    ],
                ]);
            }

            $sourceColumnKey =
                isset($decision['source_column_key'])
                    ? trim(
                        (string) $decision['source_column_key']
                    )
                    : null;

            $defaultValue =
                array_key_exists(
                    'default_value',
                    $decision
                )
                    ? $decision['default_value']
                    : null;

            $transformationKey =
                isset($decision['transformation_key'])
                    ? trim(
                        (string) $decision['transformation_key']
                    )
                    : null;

            $column =
                null;

            if (
                in_array(
                    $mappingType,
                    [
                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_DIRECT,

                        DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_TRANSFORM,
                    ],
                    true
                )
            ) {
                if (
                    $sourceColumnKey === null
                    || $sourceColumnKey === ''
                    || ! isset(
                        $columnsByKey[$sourceColumnKey]
                    )
                ) {
                    throw ValidationException::withMessages([
                        "decisions.$index.source_column_key" => [
                            'Selecciona una columna existente del profiling técnico.',
                        ],
                    ]);
                }

                $column =
                    $columnsByKey[$sourceColumnKey];
            } else {
                $sourceColumnKey =
                    null;
            }

            if (
                $mappingType
                    === DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_DEFAULT
                && $defaultValue === null
            ) {
                throw ValidationException::withMessages([
                    "decisions.$index.default_value" => [
                        'El mapeo por defecto requiere un valor.',
                    ],
                ]);
            }

            if (
                $mappingType
                    === DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_TRANSFORM
                && (
                    $transformationKey === null
                    || $transformationKey === ''
                    || ! preg_match(
                        self::KEY_PATTERN,
                        $transformationKey
                    )
                )
            ) {
                throw ValidationException::withMessages([
                    "decisions.$index.transformation_key" => [
                        'La transformación debe usar una clave controlada de LAUDA.',
                    ],
                ]);
            }

            if (
                $mappingType
                    !== DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_DEFAULT
            ) {
                $defaultValue =
                    null;
            }

            if (
                $mappingType
                    !== DataTransformationBiSourceAssetFieldMapping
                        ::TYPE_TRANSFORM
            ) {
                $transformationKey =
                    null;
            }

            $configuration =
                $decision['configuration_snapshot']
                ?? null;

            if (
                $configuration !== null
                && ! is_array($configuration)
            ) {
                throw ValidationException::withMessages([
                    "decisions.$index.configuration_snapshot" => [
                        'La configuración de transformación debe ser estructurada.',
                    ],
                ]);
            }

            $normalized[] = [
                'canonical_field_key' =>
                    $canonicalFieldKey,

                'source_column_key' =>
                    $sourceColumnKey,

                'source_column_index' =>
                    is_array($column)
                    && isset($column['index'])
                        ? (int) $column['index']
                        : null,

                'source_header' =>
                    is_array($column)
                    && array_key_exists(
                        'header',
                        $column
                    )
                    && $column['header'] !== null
                        ? (string) $column['header']
                        : null,

                'mapping_type' =>
                    $mappingType,

                'default_value' =>
                    $defaultValue !== null
                        ? (string) $defaultValue
                        : null,

                'transformation_key' =>
                    $transformationKey,

                'configuration_snapshot' =>
                    $configuration,

                'notes' =>
                    isset($decision['notes'])
                        ? trim(
                            (string) $decision['notes']
                        )
                        : null,
            ];
        }

        DB::transaction(
            function () use (
                $implementationRequest,
                $session,
                $asset,
                $mapping,
                $normalized,
                $actor
            ): void {
                /*
                 * Serialize mapping writes with source-file replacement.
                 *
                 * Upload uses the same SourceAsset as its first lock, so once
                 * this lock is held the current artifact cannot change between
                 * integrity validation and field-mapping persistence.
                 */
                $lockedAsset =
                    DataTransformationBiSourceAsset::query()
                        ->whereKey(
                            (int) $asset->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $implementationRequest->company_id
                        )
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            (int) $session->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($lockedAsset === null) {
                    throw new AuthorizationException(
                        'La fuente ya no pertenece a esta solicitud y sesión.'
                    );
                }

                $locked =
                    DataTransformationBiSourceAssetMapping::query()
                        ->whereKey(
                            (int) $mapping->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $implementationRequest->company_id
                        )
                        ->where(
                            'data_transformation_bi_intake_session_id',
                            (int) $session->getKey()
                        )
                        ->where(
                            'data_transformation_bi_source_asset_id',
                            (int) $lockedAsset->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($locked === null) {
                    throw new AuthorizationException(
                        'El mapeo ya no pertenece a esta fuente y sesión.'
                    );
                }

                $this->assertCurrentCanonicalRegistry(
                    $locked
                );

                if (
                    (string) $locked->status
                        === DataTransformationBiSourceAssetMapping
                            ::STATUS_STALE
                    || (string) $locked->status
                        === DataTransformationBiSourceAssetMapping
                            ::STATUS_VALIDATED
                ) {
                    throw ValidationException::withMessages([
                        'mapping' => [
                            'El mapeo ya no admite reemplazo de campos.',
                        ],
                    ]);
                }

                /*
                 * Repeat artifact/profile integrity checks inside the write
                 * transaction and under locks. The earlier validation builds
                 * the normalized decisions; this second validation guarantees
                 * those decisions still refer to the exact same artifact.
                 */
                $lockedContext =
                    $this->currentProfileContext(
                        $implementationRequest,
                        $lockedAsset,
                        (int) $locked->source_sheet_index,
                        true
                    );

                $lockedArtifact =
                    $lockedContext['artifact'];

                if (
                    (int) $locked
                        ->data_transformation_bi_source_asset_file_id
                        !== (int) $lockedArtifact->getKey()
                    || (int) $locked->source_profile_version
                        !== (int) (
                            $lockedContext['profile']['version']
                            ?? 0
                        )
                    || ! hash_equals(
                        strtolower(
                            (string) $locked->source_sha256
                        ),
                        strtolower(
                            (string) $lockedArtifact->source_sha256
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'mapping' => [
                            'El mapeo ya no corresponde al archivo fuente vigente.',
                        ],
                    ]);
                }

                $locked->fieldMappings()
                    ->delete();

                foreach ($normalized as $decision) {
                    $field =
                        new DataTransformationBiSourceAssetFieldMapping();

                    $field->forceFill([
                        'data_transformation_bi_source_asset_mapping_id' =>
                            (int) $locked->getKey(),

                        'company_id' =>
                            (int) $locked->company_id,

                        'canonical_field_key' =>
                            $decision['canonical_field_key'],

                        'source_column_key' =>
                            $decision['source_column_key'],

                        'source_column_index' =>
                            $decision['source_column_index'],

                        'source_header' =>
                            $decision['source_header'],

                        'mapping_type' =>
                            $decision['mapping_type'],

                        'default_value' =>
                            $decision['default_value'],

                        'transformation_key' =>
                            $decision['transformation_key'],

                        'configuration_snapshot' =>
                            $decision['configuration_snapshot'],

                        'status' =>
                            DataTransformationBiSourceAssetFieldMapping
                                ::STATUS_DRAFT,

                        'notes' =>
                            $decision['notes'],

                        'created_by_user_id' =>
                            (int) $actor->getKey(),

                        'updated_by_user_id' =>
                            (int) $actor->getKey(),
                    ]);

                    $field->save();
                }

                $locked->forceFill([
                    'status' =>
                        DataTransformationBiSourceAssetMapping
                            ::STATUS_DRAFT,

                    'updated_by_user_id' =>
                        (int) $actor->getKey(),

                    'validated_at' =>
                        null,

                    'validated_by_user_id' =>
                        null,
                ]);

                $locked->save();
            }
        );

        return DataTransformationBiSourceAssetMapping::query()
            ->with(
                'fieldMappings'
            )
            ->findOrFail(
                (int) $mapping->getKey()
            );
    }

    /**
     * Safe Admin representation of one source-centric mapping.
     *
     * Source SHA, private storage location and raw/sample values are not
     * exposed to the browser.
     *
     * @return array<string,mixed>
     */
    private function mappingPayload(
        DataTransformationBiSourceAssetMapping $mapping
    ): array {
        $fields =
            $mapping->relationLoaded(
                'fieldMappings'
            )
                ? $mapping->fieldMappings
                : $mapping
                    ->fieldMappings()
                    ->orderBy(
                        'canonical_field_key'
                    )
                    ->orderBy(
                        'id'
                    )
                    ->get();

        return [
            'id' =>
                (int) $mapping->getKey(),

            'canonical_entity_key' =>
                (string) $mapping->canonical_entity_key,

            'canonical_registry_version' =>
                (int) $mapping->canonical_registry_version,

            'source_profile_version' =>
                (int) $mapping->source_profile_version,

            'source_sheet_index' =>
                (int) $mapping->source_sheet_index,

            'source_sheet_name' =>
                $mapping->source_sheet_name !== null
                    ? (string) $mapping->source_sheet_name
                    : null,

            'mapping_version' =>
                (int) $mapping->mapping_version,

            'status' =>
                (string) $mapping->status,

            'notes' =>
                $mapping->notes !== null
                    ? (string) $mapping->notes
                    : null,

            'validated_at' =>
                $mapping->validated_at
                    ?->toISOString(),

            'created_at' =>
                $mapping->created_at
                    ?->toISOString(),

            'updated_at' =>
                $mapping->updated_at
                    ?->toISOString(),

            'fields' =>
                $fields
                    ->map(
                        static function (
                            DataTransformationBiSourceAssetFieldMapping $field
                        ): array {
                            return [
                                'id' =>
                                    (int) $field->getKey(),

                                'canonical_field_key' =>
                                    (string) $field->canonical_field_key,

                                'source_column_key' =>
                                    $field->source_column_key !== null
                                        ? (string) $field->source_column_key
                                        : null,

                                'source_column_index' =>
                                    $field->source_column_index !== null
                                        ? (int) $field->source_column_index
                                        : null,

                                'source_header' =>
                                    $field->source_header !== null
                                        ? (string) $field->source_header
                                        : null,

                                'mapping_type' =>
                                    (string) $field->mapping_type,

                                'default_value' =>
                                    $field->default_value !== null
                                        ? (string) $field->default_value
                                        : null,

                                'transformation_key' =>
                                    $field->transformation_key !== null
                                        ? (string) $field->transformation_key
                                        : null,

                                'configuration_snapshot' =>
                                    is_array(
                                        $field->configuration_snapshot
                                    )
                                        ? $field->configuration_snapshot
                                        : null,

                                'status' =>
                                    (string) $field->status,

                                'notes' =>
                                    $field->notes !== null
                                        ? (string) $field->notes
                                        : null,
                            ];
                        }
                    )
                    ->values()
                    ->all(),
        ];
    }

    private function assertCurrentCanonicalRegistry(
        DataTransformationBiSourceAssetMapping $mapping
    ): void {
        if (
            (int) $mapping->canonical_registry_version
                !== $this->canonicalRegistry->version()
            || ! $this->canonicalRegistry
                ->supportsEntity(
                    (string) $mapping->canonical_entity_key
                )
        ) {
            throw ValidationException::withMessages([
                'mapping' => [
                    'El mapeo pertenece a una versión anterior '
                    .'del modelo canónico de LAUDA.',
                ],
            ]);
        }
    }

    private function assertAdminScope(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        User $actor
    ): void {
        $this->authorization
            ->assertCanManage(
                $implementationRequest,
                $actor
            );

        if ((string) $actor->role !== 'admin') {
            throw new AuthorizationException(
                'El mapeo canónico de fuentes corresponde a Admin LAUDA.'
            );
        }

        if (
            ! $implementationRequest->exists
            || (int) $implementationRequest->getKey() <= 0
            || (string) $implementationRequest->capability_key
                !== 'data_transformation_bi'
            || (int) $session->company_id
                !== (int) $implementationRequest->company_id
            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $implementationRequest->getKey()
            || ! $asset->exists
            || (int) $asset->company_id
                !== (int) $implementationRequest->company_id
            || (int) $asset
                ->data_transformation_bi_intake_session_id
                !== (int) $session->getKey()
        ) {
            throw new AuthorizationException(
                'La fuente no pertenece a esta solicitud y sesión.'
            );
        }

        if (
            ! in_array(
                (string) $session->status,
                [
                    DataTransformationBiIntakeSession::STATUS_DRAFT,
                    DataTransformationBiIntakeSession::STATUS_READY,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'intake_session' => [
                    'La sesión ya no admite mapeo técnico de fuentes.',
                ],
            ]);
        }

        if (
            $asset->archived_at !== null
            || (string) $asset->status
                === DataTransformationBiSourceAsset::STATUS_ARCHIVED
        ) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'Una fuente archivada no puede mapearse.',
                ],
            ]);
        }
    }

    private function assertMappingScope(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        DataTransformationBiSourceAssetMapping $mapping
    ): void {
        if (
            ! $mapping->exists
            || (int) $mapping->company_id
                !== (int) $implementationRequest->company_id
            || (int) $mapping
                ->data_transformation_bi_intake_session_id
                !== (int) $session->getKey()
            || (int) $mapping
                ->data_transformation_bi_source_asset_id
                !== (int) $asset->getKey()
        ) {
            throw new AuthorizationException(
                'El mapeo no pertenece a esta fuente y sesión.'
            );
        }
    }

    /**
     * @return array{
     *   artifact:DataTransformationBiSourceAssetFile,
     *   profile:array<string,mixed>,
     *   sheet:array<string,mixed>
     * }
     */
    private function currentProfileContext(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiSourceAsset $asset,
        int $sourceSheetIndex,
        bool $lockArtifact = false
    ): array {
        if (
            (string) $asset->data_status
                !== DataTransformationBiSourceAsset::DATA_ANALYZED
            || (string) $asset->profiling_status
                !== DataTransformationBiSourceAsset::PROFILING_COMPLETED
            || ! is_array(
                $asset->profiling_snapshot
            )
        ) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'La fuente debe completar el profiling técnico antes del mapeo LAUDA.',
                ],
            ]);
        }

        $artifactQuery =
            DataTransformationBiSourceAssetFile::query()
                ->where(
                    'data_transformation_bi_source_asset_id',
                    (int) $asset->getKey()
                )
                ->where(
                    'company_id',
                    (int) $implementationRequest->company_id
                )
                ->where(
                    'status',
                    DataTransformationBiSourceAssetFile::STATUS_UPLOADED
                );

        if ($lockArtifact) {
            $artifactQuery->lockForUpdate();
        }

        $artifact =
            $artifactQuery->first();

        if ($artifact === null) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'La fuente no tiene un archivo vigente para mapear.',
                ],
            ]);
        }

        $profile =
            $asset->profiling_snapshot;

        $profileFileId =
            (int) (
                $profile['source_file_id']
                ?? 0
            );

        $profileSha256 =
            strtolower(
                trim(
                    (string) (
                        $profile['source_sha256']
                        ?? ''
                    )
                )
            );

        $artifactSha256 =
            strtolower(
                trim(
                    (string) $artifact->source_sha256
                )
            );

        if (
            $profileFileId
                !== (int) $artifact->getKey()
            || strlen($profileSha256) !== 64
            || strlen($artifactSha256) !== 64
            || ! hash_equals(
                $profileSha256,
                $artifactSha256
            )
        ) {
            throw ValidationException::withMessages([
                'source_asset' => [
                    'El profiling técnico ya no corresponde al archivo fuente vigente.',
                ],
            ]);
        }

        $sheets =
            is_array(
                $profile['sheets']
                ?? null
            )
                ? $profile['sheets']
                : [];

        $sheet =
            null;

        foreach ($sheets as $candidate) {
            if (
                is_array($candidate)
                && (int) (
                    $candidate['index']
                    ?? -1
                ) === $sourceSheetIndex
            ) {
                $sheet =
                    $candidate;

                break;
            }
        }

        if ($sheet === null) {
            throw ValidationException::withMessages([
                'source_sheet_index' => [
                    'La hoja seleccionada no existe en el profiling técnico vigente.',
                ],
            ]);
        }

        return [
            'artifact' =>
                $artifact,

            'profile' =>
                $profile,

            'sheet' =>
                $sheet,
        ];
    }

    /**
     * @param array<string,mixed> $sheet
     * @return array<string,array<string,mixed>>
     */
    private function columnsByKey(
        array $sheet
    ): array {
        $columns =
            is_array(
                $sheet['columns']
                ?? null
            )
                ? $sheet['columns']
                : [];

        $indexed =
            [];

        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }

            $key =
                trim(
                    (string) (
                        $column['key']
                        ?? ''
                    )
                );

            if ($key === '') {
                continue;
            }

            $indexed[$key] =
                $column;
        }

        return $indexed;
    }

    private function validatedCanonicalKey(
        string $value,
        string $field
    ): string {
        $value =
            trim(
                $value
            );

        if (
            $value === ''
            || ! preg_match(
                self::KEY_PATTERN,
                $value
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'La clave debe usar minúsculas, números y guion bajo, comenzando por una letra.',
                ],
            ]);
        }

        return $value;
    }
}
