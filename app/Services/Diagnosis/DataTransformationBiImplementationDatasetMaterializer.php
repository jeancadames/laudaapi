<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiImplementationDataset;
use App\Models\DataTransformationBiImplementationRow;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFieldMapping;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\DataTransformationBiSourceAssetMapping;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class DataTransformationBiImplementationDatasetMaterializer
{
    private const INSERT_CHUNK_SIZE = 500;

    public function __construct(
        private readonly TransformationImplementationAuthorizationGate
            $authorizationGate,

        private readonly DataTransformationBiCanonicalModelService
            $canonicalModelService,

        private readonly DataTransformationBiSourceAssetRowReader
            $rowReader,

        private readonly DataTransformationBiMappingProjector
            $projector
    ) {
    }

    public function materialize(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        DataTransformationBiSourceAssetMapping $mapping,
        User $actor
    ): DataTransformationBiImplementationDataset {
        return DB::transaction(
            function () use (
                $request,
                $session,
                $asset,
                $mapping,
                $actor
            ): DataTransformationBiImplementationDataset {
                /*
                 * Modern commercial authorization remains the first
                 * implementation-write boundary.
                 */
                $this->authorizationGate
                    ->assertActiveForRequest(
                        $request,
                        true
                    );

                /*
                 * Keep the same serialization boundary already used by
                 * SourceAsset Mapping writes and source-file replacement.
                 */
                $lockedAsset =
                    DataTransformationBiSourceAsset::query()
                        ->whereKey(
                            (int) $asset->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
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

                $lockedMapping =
                    DataTransformationBiSourceAssetMapping::query()
                        ->whereKey(
                            (int) $mapping->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
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

                if ($lockedMapping === null) {
                    throw new AuthorizationException(
                        'El mapeo no pertenece a esta fuente y sesión.'
                    );
                }

                if (
                    (string) $lockedMapping->status
                    !== DataTransformationBiSourceAssetMapping
                        ::STATUS_VALIDATED
                ) {
                    throw ValidationException::withMessages([
                        'mapping' => [
                            'Solo un mapeo VALIDATED puede materializarse.',
                        ],
                    ]);
                }

                /*
                 * Idempotency is mapping-version based.
                 *
                 * A validated mapping is immutable, therefore one exact
                 * mapping can own only one modern implementation dataset.
                 */
                $existing =
                    DataTransformationBiImplementationDataset::query()
                        ->where(
                            'data_transformation_bi_source_asset_mapping_id',
                            (int) $lockedMapping->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existing !== null) {
                    $this->assertReusableDataset(
                        $request,
                        $session,
                        $lockedAsset,
                        $lockedMapping,
                        $existing
                    );

                    return $existing;
                }

                $registry =
                    $this->canonicalModelService
                        ->publishedRegistry(
                            $request,
                            $actor,
                            true
                        );

                if ($registry === null) {
                    throw ValidationException::withMessages([
                        'canonical_registry' => [
                            'No existe un modelo canónico publicado.',
                        ],
                    ]);
                }

                /*
                 * SourceAsset Mapping is pinned to the company-owned
                 * canonical semantic version by version number.
                 *
                 * The mapping table deliberately does NOT own a registry-row
                 * foreign key. publishedRegistry() resolves the current
                 * company-owned published registry; its version must match
                 * the immutable mapping pin.
                 *
                 * The resulting ImplementationDataset then freezes the exact
                 * resolved registry row id for downstream reproducibility.
                 */
                if (
                    (int) $registry->version
                    !== (int) $lockedMapping
                        ->canonical_registry_version
                ) {
                    throw ValidationException::withMessages([
                        'mapping' => [
                            'El mapeo no pertenece al modelo canónico publicado vigente.',
                        ],
                    ]);
                }

                $registryPayload =
                    $this->canonicalModelService
                        ->registryPayload(
                            $registry
                        );

                $canonicalFields =
                    $this->canonicalFieldsForMapping(
                        $registryPayload,
                        (string) $lockedMapping
                            ->canonical_entity_key
                    );

                $fieldMappings =
                    $lockedMapping
                        ->fieldMappings()
                        ->orderBy(
                            'canonical_field_key'
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                foreach ($fieldMappings as $fieldMapping) {
                    if (
                        (string) $fieldMapping->status
                        !== DataTransformationBiSourceAssetFieldMapping
                            ::STATUS_VALIDATED
                    ) {
                        throw ValidationException::withMessages([
                            'mapping' => [
                                'Todas las decisiones del mapeo deben estar VALIDATED.',
                            ],
                        ]);
                    }

                    if (
                        (string) $fieldMapping->mapping_type
                        === DataTransformationBiSourceAssetFieldMapping
                            ::TYPE_TRANSFORM
                    ) {
                        throw ValidationException::withMessages([
                            'mapping' => [
                                'Las transformaciones todavía no son ejecutables.',
                            ],
                        ]);
                    }
                }

                $artifact =
                    DataTransformationBiSourceAssetFile::query()
                        ->whereKey(
                            (int) $lockedMapping
                                ->data_transformation_bi_source_asset_file_id
                        )
                        ->where(
                            'data_transformation_bi_source_asset_id',
                            (int) $lockedAsset->getKey()
                        )
                        ->where(
                            'company_id',
                            (int) $request->company_id
                        )
                        ->where(
                            'status',
                            DataTransformationBiSourceAssetFile
                                ::STATUS_UPLOADED
                        )
                        ->lockForUpdate()
                        ->first();

                if ($artifact === null) {
                    throw ValidationException::withMessages([
                        'source_asset' => [
                            'El archivo fuente fijado ya no está disponible.',
                        ],
                    ]);
                }

                if (
                    ! hash_equals(
                        strtolower(
                            (string) $lockedMapping->source_sha256
                        ),
                        strtolower(
                            (string) $artifact->source_sha256
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'mapping' => [
                            'El SHA del archivo fuente ya no coincide con el mapeo validado.',
                        ],
                    ]);
                }

                $profile =
                    is_array(
                        $lockedAsset->profiling_snapshot
                    )
                        ? $lockedAsset->profiling_snapshot
                        : [];

                if (
                    (int) (
                        $profile['source_file_id']
                        ?? 0
                    )
                    !== (int) $artifact->getKey()

                    || (int) (
                        $profile['version']
                        ?? 0
                    )
                    !== (int) $lockedMapping
                        ->source_profile_version

                    || ! hash_equals(
                        strtolower(
                            (string) (
                                $profile['source_sha256']
                                ?? ''
                            )
                        ),
                        strtolower(
                            (string) $artifact->source_sha256
                        )
                    )
                ) {
                    throw ValidationException::withMessages([
                        'mapping' => [
                            'El profiling vigente ya no coincide con el archivo fijado.',
                        ],
                    ]);
                }

                $disk =
                    Storage::disk(
                        (string) $artifact->source_disk
                    );

                if (
                    ! $disk->exists(
                        (string) $artifact->source_path
                    )
                ) {
                    throw new RuntimeException(
                        'El archivo fuente fijado no existe en almacenamiento privado.'
                    );
                }

                $localPath =
                    $disk->path(
                        (string) $artifact->source_path
                    );

                $dataset =
                    DataTransformationBiImplementationDataset::query()
                        ->create([
                            'company_id' =>
                                (int) $request->company_id,

                            'transformation_implementation_request_id' =>
                                (int) $request->getKey(),

                            'data_transformation_bi_intake_session_id' =>
                                (int) $session->getKey(),

                            'data_transformation_bi_source_asset_id' =>
                                (int) $lockedAsset->getKey(),

                            'data_transformation_bi_source_asset_file_id' =>
                                (int) $artifact->getKey(),

                            'data_transformation_bi_source_asset_mapping_id' =>
                                (int) $lockedMapping->getKey(),

                            'data_transformation_bi_canonical_registry_version_id' =>
                                (int) $registry->getKey(),

                            'canonical_registry_version' =>
                                (int) $registry->version,

                            'canonical_entity_key' =>
                                (string) $lockedMapping
                                    ->canonical_entity_key,

                            'mapping_version' =>
                                (int) $lockedMapping->mapping_version,

                            'source_profile_version' =>
                                (int) $lockedMapping
                                    ->source_profile_version,

                            'source_sheet_index' =>
                                (int) $lockedMapping
                                    ->source_sheet_index,

                            'source_sheet_name' =>
                                $lockedMapping->source_sheet_name,

                            'source_sha256' =>
                                strtolower(
                                    (string) $artifact->source_sha256
                                ),

                            'status' =>
                                DataTransformationBiImplementationDataset
                                    ::STATUS_BUILDING,

                            'row_count' =>
                                0,

                            'dataset_sha256' =>
                                null,

                            'materialized_by_user_id' =>
                                (int) $actor->getKey(),

                            'materialized_at' =>
                                null,

                            'failure_code' =>
                                null,

                            'failure_message' =>
                                null,
                        ]);

                $mappingPayload =
                    $fieldMappings
                        ->map(
                            static fn (
                                DataTransformationBiSourceAssetFieldMapping $field
                            ): array => [
                                'canonical_field_key' =>
                                    (string) $field
                                        ->canonical_field_key,

                                'mapping_type' =>
                                    (string) $field
                                        ->mapping_type,

                                'source_column_key' =>
                                    $field->source_column_key,

                                'default_value' =>
                                    $field->default_value,

                                'transformation_key' =>
                                    $field->transformation_key,
                            ]
                        )
                        ->values()
                        ->all();

                $datasetHash =
                    hash_init(
                        'sha256'
                    );

                $buffer = [];
                $rowCount = 0;

                foreach (
                    $this->rowReader->iterate(
                        $localPath,
                        (string) $artifact->original_filename,
                        is_array(
                            $artifact->reader_configuration
                        )
                            ? $artifact->reader_configuration
                            : [],
                        is_array(
                            $artifact->source_structure_snapshot
                        )
                            ? $artifact->source_structure_snapshot
                            : [],
                        (int) $lockedMapping->source_sheet_index
                    )
                    as $sourceRow
                ) {
                    $sourceRowNumber =
                        (int) $sourceRow[
                            'source_row_number'
                        ];

                    $sourceValues =
                        is_array(
                            $sourceRow['values']
                            ?? null
                        )
                            ? $sourceRow['values']
                            : [];

                    $projection =
                        $this->projector->project(
                            $sourceValues,
                            $canonicalFields,
                            $mappingPayload
                        );

                    $canonicalPayload =
                        $projection['payload'];

                    $sourceRowJson =
                        $this->canonicalJson(
                            $sourceValues
                        );

                    $canonicalPayloadJson =
                        $this->canonicalJson(
                            $canonicalPayload
                        );

                    $sourceRowSha =
                        hash(
                            'sha256',
                            $sourceRowJson
                        );

                    $canonicalPayloadSha =
                        hash(
                            'sha256',
                            $canonicalPayloadJson
                        );

                    hash_update(
                        $datasetHash,
                        $sourceRowNumber
                        ."\0"
                        .$sourceRowSha
                        ."\0"
                        .$canonicalPayloadSha
                        ."\n"
                    );

                    $buffer[] = [
                        'data_transformation_bi_implementation_dataset_id' =>
                            (int) $dataset->getKey(),

                        'company_id' =>
                            (int) $request->company_id,

                        'canonical_entity_key' =>
                            (string) $lockedMapping
                                ->canonical_entity_key,

                        'source_row_number' =>
                            $sourceRowNumber,

                        'source_row_sha256' =>
                            $sourceRowSha,

                        'canonical_payload' =>
                            $canonicalPayloadJson,

                        'canonical_payload_sha256' =>
                            $canonicalPayloadSha,

                        'projection_meta' =>
                            $this->canonicalJson(
                                $projection[
                                    'projection_meta'
                                ]
                            ),

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ];

                    $rowCount++;

                    if (
                        count($buffer)
                        >= self::INSERT_CHUNK_SIZE
                    ) {
                        DataTransformationBiImplementationRow::query()
                            ->insert(
                                $buffer
                            );

                        $buffer = [];
                    }
                }

                if ($buffer !== []) {
                    DataTransformationBiImplementationRow::query()
                        ->insert(
                            $buffer
                        );
                }

                $persistedRows =
                    DataTransformationBiImplementationRow::query()
                        ->where(
                            'data_transformation_bi_implementation_dataset_id',
                            (int) $dataset->getKey()
                        )
                        ->count();

                if ($persistedRows !== $rowCount) {
                    throw new RuntimeException(
                        'La materialización no persistió exactamente una fila derivada por fila fuente.'
                    );
                }

                $dataset->forceFill([
                    'status' =>
                        DataTransformationBiImplementationDataset
                            ::STATUS_READY,

                    'row_count' =>
                        $rowCount,

                    'dataset_sha256' =>
                        hash_final(
                            $datasetHash
                        ),

                    'materialized_at' =>
                        now(),

                    'failure_code' =>
                        null,

                    'failure_message' =>
                        null,
                ])->save();

                return $dataset->fresh()
                    ?? $dataset;
            }
        );
    }

    /**
     * @param array<string,mixed> $registry
     *
     * @return array<int,array{
     *     field_key:string,
     *     data_type:string,
     *     required:bool,
     *     is_identity:bool
     * }>
     */
    private function canonicalFieldsForMapping(
        array $registry,
        string $entityKey
    ): array {
        foreach (
            is_array(
                $registry['entities']
                ?? null
            )
                ? $registry['entities']
                : []
            as $entity
        ) {
            if (
                ! is_array($entity)
                || (string) (
                    $entity['key']
                    ?? ''
                ) !== $entityKey
            ) {
                continue;
            }

            $result = [];

            foreach (
                is_array(
                    $entity['fields']
                    ?? null
                )
                    ? $entity['fields']
                    : []
                as $fieldKey => $field
            ) {
                if (! is_array($field)) {
                    continue;
                }

                $result[] = [
                    'field_key' =>
                        (string) $fieldKey,

                    'data_type' =>
                        (string) (
                            $field['type']
                            ?? ''
                        ),

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
                ];
            }

            if ($result === []) {
                throw ValidationException::withMessages([
                    'canonical_registry' => [
                        'La entidad canónica no contiene campos activos.',
                    ],
                ]);
            }

            return $result;
        }

        throw ValidationException::withMessages([
            'canonical_registry' => [
                'La entidad canónica fijada no existe en el registro publicado.',
            ],
        ]);
    }

    private function assertReusableDataset(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        DataTransformationBiSourceAsset $asset,
        DataTransformationBiSourceAssetMapping $mapping,
        DataTransformationBiImplementationDataset $dataset
    ): void {
        if (
            (string) $dataset->status
            !== DataTransformationBiImplementationDataset
                ::STATUS_READY

            || (int) $dataset->company_id
            !== (int) $request->company_id

            || (int) $dataset
                ->transformation_implementation_request_id
            !== (int) $request->getKey()

            || (int) $dataset
                ->data_transformation_bi_intake_session_id
            !== (int) $session->getKey()

            || (int) $dataset
                ->data_transformation_bi_source_asset_id
            !== (int) $asset->getKey()

            || (int) $dataset
                ->data_transformation_bi_source_asset_mapping_id
            !== (int) $mapping->getKey()

            || (int) $dataset->mapping_version
            !== (int) $mapping->mapping_version

            || (int) $dataset->source_profile_version
            !== (int) $mapping->source_profile_version

            || (int) $dataset->source_sheet_index
            !== (int) $mapping->source_sheet_index

            || (int) $dataset->canonical_registry_version
            !== (int) $mapping->canonical_registry_version

            || ! hash_equals(
                strtolower(
                    (string) $dataset->source_sha256
                ),
                strtolower(
                    (string) $mapping->source_sha256
                )
            )

            || $dataset->dataset_sha256 === null

            || $dataset->materialized_at === null
        ) {
            throw new RuntimeException(
                'Existe un dataset para este mapeo, pero no satisface el contrato idempotente READY.'
            );
        }
    }

    private function canonicalJson(
        mixed $value
    ): string {
        try {
            return json_encode(
                $this->canonicalize(
                    $value
                ),
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'No se pudo serializar una fila canónica de forma estable.',
                0,
                $exception
            );
        }
    }

    private function canonicalize(
        mixed $value
    ): mixed {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed =>
                    $this->canonicalize(
                        $item
                    ),
                $value
            );
        }

        ksort(
            $value,
            SORT_STRING
        );

        foreach (
            $value
            as $key => $item
        ) {
            $value[$key] =
                $this->canonicalize(
                    $item
                );
        }

        return $value;
    }
}
