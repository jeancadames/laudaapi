<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalRelationshipNavigator
{
    public function __construct(
        private readonly DataTransformationBiUsableDatasetResolver
            $usableDatasetResolver,
        private readonly DataTransformationBiPreparedDatasetReader
            $preparedDatasetReader,
        private readonly DataTransformationBiCanonicalIdentity
            $canonicalIdentity
    ) {
    }

    /**
     * @return array<int,array{
     *     from_domain:string,
     *     from_field:string,
     *     to_domain:string,
     *     to_field:string
     * }>
     */
    public function relationshipsFrom(
        string $fromDomain
    ): array {
        $fromDomain =
            $this->canonicalDomain(
                $fromDomain
            );

        return array_values(
            array_filter(
                DataTransformationBiStandardIntakeSchema
                    ::relationships(),
                static fn (array $relationship): bool =>
                    (
                        $relationship['from_domain']
                        ?? null
                    ) === $fromDomain
            )
        );
    }

    /**
     * Resolve one specific outbound canonical relationship.
     *
     * The P13 dataset pointer is resolved exactly once and the target
     * lookup remains pinned to that same run and batch.
     *
     * @param array<string,mixed> $sourcePayload
     * @return array<string,mixed>
     */
    public function resolveTarget(
        int $companyId,
        int $implementationRequestId,
        string $fromDomain,
        array $sourcePayload,
        string $toDomain
    ): array {
        $relationship =
            $this->relationship(
                $fromDomain,
                $toDomain
            );

        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        if (! $this->hasUsableDataset($resolved)) {
            return [
                'available' =>
                    false,

                'reason' =>
                    (string) (
                        $resolved['reason']
                        ?? 'no_successful_normalized_dataset'
                    ),

                'dataset' =>
                    null,

                'relationship' =>
                    $relationship,

                'relation_value_present' =>
                    false,

                'target_found' =>
                    false,

                'target' =>
                    null,
            ];
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        return $this->resolveAgainstDataset(
            $companyId,
            $dataset,
            $sourcePayload,
            $relationship,
            (string) (
                $resolved['reason']
                ?? 'latest_successful_normalized_run'
            )
        );
    }

    /**
     * Resolve every outbound relationship from one canonical source row.
     *
     * P13 is resolved once for the complete navigation operation.
     *
     * @param array<string,mixed> $sourcePayload
     * @return array{
     *     available:bool,
     *     reason:string,
     *     dataset:array<string,mixed>|null,
     *     from_domain:string,
     *     relationships:array<int,array<string,mixed>>
     * }
     */
    public function resolveTargets(
        int $companyId,
        int $implementationRequestId,
        string $fromDomain,
        array $sourcePayload
    ): array {
        $fromDomain =
            $this->canonicalDomain(
                $fromDomain
            );

        $relationships =
            $this->relationshipsFrom(
                $fromDomain
            );

        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        if (! $this->hasUsableDataset($resolved)) {
            return [
                'available' =>
                    false,

                'reason' =>
                    (string) (
                        $resolved['reason']
                        ?? 'no_successful_normalized_dataset'
                    ),

                'dataset' =>
                    null,

                'from_domain' =>
                    $fromDomain,

                'relationships' =>
                    [],
            ];
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        $reason =
            (string) (
                $resolved['reason']
                ?? 'latest_successful_normalized_run'
            );

        $resolvedRelationships = [];

        foreach ($relationships as $relationship) {
            $resolvedRelationships[] =
                $this->resolveAgainstDataset(
                    $companyId,
                    $dataset,
                    $sourcePayload,
                    $relationship,
                    $reason
                );
        }

        return [
            'available' =>
                true,

            'reason' =>
                $reason,

            'dataset' =>
                $dataset,

            'from_domain' =>
                $fromDomain,

            'relationships' =>
                $resolvedRelationships,
        ];
    }

    /**
     * @param array<string,mixed> $dataset
     * @param array<string,mixed> $sourcePayload
     * @param array<string,mixed> $relationship
     * @return array<string,mixed>
     */
    /**
     * Resolve one canonical relationship for a bounded set of source rows
     * against an already-frozen P13 dataset.
     *
     * Exactly one bulk target lookup is performed for all unique nonblank
     * relation values in this call.
     *
     * @param array<string,mixed> $dataset
     * @param array<int,array<string,mixed>> $sourceRows
     * @return array<int,array{
     *     source_normalized_row_id:int,
     *     relationship:array<string,mixed>,
     *     source_relation_value_missing:bool,
     *     target_found:bool,
     *     target:null|array<string,mixed>
     * }>
     */
    public function resolveBulkTargetsInDataset(
        int $companyId,
        array $dataset,
        string $fromDomain,
        array $sourceRows,
        string $toDomain
    ): array {
        if (
            count($sourceRows)
            > 500
        ) {
            throw new InvalidArgumentException(
                'La navegación canónica por lote admite '
                .'un máximo de 500 filas fuente por llamada.'
            );
        }

        $relationship =
            $this->relationship(
                $fromDomain,
                $toDomain
            );

        $fromField =
            (string) $relationship[
                'from_field'
            ];

        $toField =
            (string) $relationship[
                'to_field'
            ];

        $canonicalIdentity =
            app(
                DataTransformationBiCanonicalIdentity::class
            );

        $reader =
            app(
                DataTransformationBiPreparedDatasetReader::class
            );

        /*
         * sourceId => canonical target hash|null
         */
        $sourceTargetHashes = [];

        /*
         * canonical target hash => true
         */
        $requiredHashes = [];

        foreach ($sourceRows as $sourceRow) {
            if (! is_array($sourceRow)) {
                throw new InvalidArgumentException(
                    'Cada fila fuente debe usar el contrato '
                    .'canónico de P14.'
                );
            }

            $sourceId =
                (int) (
                    $sourceRow[
                        'normalized_row_id'
                    ]
                    ?? 0
                );

            if ($sourceId <= 0) {
                throw new InvalidArgumentException(
                    'Cada fila fuente debe contener '
                    .'normalized_row_id válido.'
                );
            }

            if (
                array_key_exists(
                    $sourceId,
                    $sourceTargetHashes
                )
            ) {
                throw new InvalidArgumentException(
                    'La navegación canónica por lote recibió '
                    .'una fila fuente duplicada.'
                );
            }

            $payload =
                $sourceRow['payload']
                ?? null;

            if (! is_array($payload)) {
                throw new InvalidArgumentException(
                    'Cada fila fuente debe contener '
                    .'payload canónico válido.'
                );
            }

            $relationValue =
                $payload[$fromField]
                ?? null;

            $missing =
                $relationValue === null
                || (
                    is_string($relationValue)
                    && trim($relationValue) === ''
                );

            if ($missing) {
                $sourceTargetHashes[$sourceId] =
                    null;

                continue;
            }

            $targetHash =
                $canonicalIdentity
                    ->hashForIdentityValues(
                        $toDomain,
                        [
                            $toField =>
                                $relationValue,
                        ]
                    );

            $sourceTargetHashes[$sourceId] =
                $targetHash;

            $requiredHashes[$targetHash] =
                true;
        }

        /*
         * P17-A performs one bounded, indexed query for all unique targets.
         * It also validates the supplied pinned dataset identity.
         */
        $targetRows =
            $reader
                ->findDomainRowsByCanonicalIdentityHashesInDataset(
                    $companyId,
                    $dataset,
                    $toDomain,
                    array_keys(
                        $requiredHashes
                    )
                );

        $resolved = [];

        foreach (
            $sourceTargetHashes
            as $sourceId => $targetHash
        ) {
            if ($targetHash === null) {
                $resolved[$sourceId] = [
                    'source_normalized_row_id' =>
                        $sourceId,

                    'relationship' =>
                        $relationship,

                    'source_relation_value_missing' =>
                        true,

                    'target_found' =>
                        false,

                    'target' =>
                        null,
                ];

                continue;
            }

            $target =
                $targetRows[$targetHash]
                ?? null;

            /*
             * P16 guarantees referential integrity for newly completed
             * datasets, but navigation remains defensive and fail-closed.
             */
            if (! is_array($target)) {
                throw new RuntimeException(
                    'Una relación canónica no pudo resolverse '
                    .'dentro del dataset fijado.'
                );
            }

            $resolved[$sourceId] = [
                'source_normalized_row_id' =>
                    $sourceId,

                'relationship' =>
                    $relationship,

                'source_relation_value_missing' =>
                    false,

                'target_found' =>
                    true,

                'target' =>
                    $target,
            ];
        }

        return $resolved;
    }

    /**
     * Stream one relationship for an entire source domain without N+1
     * lookups and without allowing the dataset pointer to move mid-stream.
     *
     * P13 is resolved exactly once. Source pages and target bulk lookups
     * both use the same frozen dataset descriptor.
     *
     * @return \Generator<int,array{
     *     source:array<string,mixed>,
     *     relationship:array<string,mixed>,
     *     source_relation_value_missing:bool,
     *     target_found:bool,
     *     target:null|array<string,mixed>
     * }>
     */
    public function iterateResolvedRelationship(
        int $companyId,
        int $implementationRequestId,
        string $fromDomain,
        string $toDomain,
        int $chunkSize = 500
    ): \Generator {
        if (
            $chunkSize <= 0
            || $chunkSize > 500
        ) {
            throw new InvalidArgumentException(
                'El tamaño del lote de relaciones debe estar '
                .'entre 1 y 500.'
            );
        }

        /*
         * Validate the relationship contract before touching the dataset.
         */
        $this->relationship(
            $fromDomain,
            $toDomain
        );

        /*
         * Resolve P13 exactly once for the whole stream.
         */
        $resolvedDataset =
            app(
                DataTransformationBiUsableDatasetResolver::class
            )
                ->forRequest(
                    $companyId,
                    $implementationRequestId
                );

        if (
            ($resolvedDataset['available'] ?? false)
            !== true
            || ! is_array(
                $resolvedDataset['dataset']
                ?? null
            )
        ) {
            return;
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolvedDataset['dataset'];

        $reader =
            app(
                DataTransformationBiPreparedDatasetReader::class
            );

        $chunk = [];

        foreach (
            $reader->iterateDomainInDataset(
                $companyId,
                $dataset,
                $fromDomain,
                $chunkSize
            )
            as $sourceRow
        ) {
            $chunk[] =
                $sourceRow;

            if (
                count($chunk)
                < $chunkSize
            ) {
                continue;
            }

            yield from $this->yieldResolvedChunk(
                $companyId,
                $dataset,
                $fromDomain,
                $toDomain,
                $chunk
            );

            $chunk = [];
        }

        if ($chunk !== []) {
            yield from $this->yieldResolvedChunk(
                $companyId,
                $dataset,
                $fromDomain,
                $toDomain,
                $chunk
            );
        }
    }

    /**
     * @param array<string,mixed> $dataset
     * @param array<int,array<string,mixed>> $sourceRows
     * @return \Generator<int,array<string,mixed>>
     */
    private function yieldResolvedChunk(
        int $companyId,
        array $dataset,
        string $fromDomain,
        string $toDomain,
        array $sourceRows
    ): \Generator {
        $targets =
            $this->resolveBulkTargetsInDataset(
                $companyId,
                $dataset,
                $fromDomain,
                $sourceRows,
                $toDomain
            );

        foreach ($sourceRows as $sourceRow) {
            $sourceId =
                (int) (
                    $sourceRow[
                        'normalized_row_id'
                    ]
                    ?? 0
                );

            $targetResult =
                $targets[$sourceId]
                ?? null;

            if (! is_array($targetResult)) {
                throw new RuntimeException(
                    'La navegación canónica por lote perdió '
                    .'correspondencia con una fila fuente.'
                );
            }

            yield [
                'source' =>
                    $sourceRow,

                'relationship' =>
                    $targetResult[
                        'relationship'
                    ],

                'source_relation_value_missing' =>
                    (bool) $targetResult[
                        'source_relation_value_missing'
                    ],

                'target_found' =>
                    (bool) $targetResult[
                        'target_found'
                    ],

                'target' =>
                    $targetResult[
                        'target'
                    ],
            ];
        }
    }

    private function resolveAgainstDataset(
        int $companyId,
        array $dataset,
        array $sourcePayload,
        array $relationship,
        string $reason
    ): array {
        $fromDomain =
            (string) $relationship['from_domain'];

        $fromField =
            (string) $relationship['from_field'];

        $toDomain =
            (string) $relationship['to_domain'];

        $toField =
            (string) $relationship['to_field'];

        $this->assertTargetIdentityContract(
            $toDomain,
            $toField
        );

        $sourceValue =
            $sourcePayload[$fromField]
            ?? null;

        if ($this->blank($sourceValue)) {
            return [
                'available' =>
                    true,

                'reason' =>
                    'source_relation_value_missing',

                'dataset' =>
                    $dataset,

                'relationship' =>
                    $relationship,

                'relation_value_present' =>
                    false,

                'target_found' =>
                    false,

                'target' =>
                    null,
            ];
        }

        $targetIdentityHash =
            $this->canonicalIdentity
                ->hashForIdentityValues(
                    $toDomain,
                    [
                        $toField =>
                            $sourceValue,
                    ]
                );

        $target =
            $this->preparedDatasetReader
                ->findDomainRowByCanonicalIdentityHashInDataset(
                    $companyId,
                    $dataset,
                    $toDomain,
                    $targetIdentityHash
                );

        if ($target === null) {
            throw new RuntimeException(
                "{$fromDomain}.{$fromField} no pudo resolverse "
                ."contra {$toDomain}.{$toField} dentro del "
                .'dataset canónico utilizable.'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                $reason,

            'dataset' =>
                $dataset,

            'relationship' =>
                $relationship,

            'relation_value_present' =>
                true,

            'target_found' =>
                true,

            'target' =>
                $target,
        ];
    }

    /**
     * @return array{
     *     from_domain:string,
     *     from_field:string,
     *     to_domain:string,
     *     to_field:string
     * }
     */
    private function relationship(
        string $fromDomain,
        string $toDomain
    ): array {
        $fromDomain =
            $this->canonicalDomain(
                $fromDomain
            );

        $toDomain =
            $this->canonicalDomain(
                $toDomain
            );

        $matches =
            array_values(
                array_filter(
                    DataTransformationBiStandardIntakeSchema
                        ::relationships(),
                    static fn (array $relationship): bool =>
                        (
                            $relationship['from_domain']
                            ?? null
                        ) === $fromDomain
                        && (
                            $relationship['to_domain']
                            ?? null
                        ) === $toDomain
                )
            );

        if ($matches === []) {
            throw new InvalidArgumentException(
                "No existe una relación canónica "
                ."{$fromDomain} -> {$toDomain}."
            );
        }

        if (count($matches) !== 1) {
            throw new RuntimeException(
                "La relación canónica {$fromDomain} -> {$toDomain} "
                .'es ambigua.'
            );
        }

        return $matches[0];
    }

    private function assertTargetIdentityContract(
        string $toDomain,
        string $toField
    ): void {
        $identity =
            DataTransformationBiStandardIntakeSchema
                ::identityKeys()[$toDomain]
            ?? [];

        if (
            $identity
            !== [
                $toField,
            ]
        ) {
            throw new RuntimeException(
                "La relación hacia {$toDomain}.{$toField} "
                .'no apunta a una identidad canónica simple soportada.'
            );
        }
    }

    private function canonicalDomain(
        string $domain
    ): string {
        $domain =
            trim($domain);

        if (
            ! array_key_exists(
                $domain,
                DataTransformationBiStandardIntakeSchema::domains()
            )
        ) {
            throw new InvalidArgumentException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        return $domain;
    }

    private function blank(
        mixed $value
    ): bool {
        return $value === null
            || (
                is_string($value)
                && trim($value) === ''
            );
    }

    /**
     * @param array<string,mixed> $resolved
     */
    private function hasUsableDataset(
        array $resolved
    ): bool {
        return (
            ($resolved['available'] ?? false)
            === true
            && is_array(
                $resolved['dataset']
                ?? null
            )
        );
    }
}
