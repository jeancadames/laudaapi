<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiProjectedRelationshipNavigator
{
    private const MAX_SOURCE_ROWS = 500;

    public function __construct(
        private readonly DataTransformationBiUsableDatasetResolver $usableDatasetResolver,
        private readonly DataTransformationBiProjectedDatasetReader $projectedDatasetReader,
        private readonly DataTransformationBiCanonicalIdentity $canonicalIdentity,
        private readonly DataTransformationBiCanonicalDomainProjection $projection
    ) {
    }

    /**
     * @return array<int,array<string,string>>
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
                static fn (
                    array $relationship
                ): bool =>
                    (
                        $relationship[
                            'from_domain'
                        ]
                        ?? null
                    )
                    === $fromDomain
            )
        );
    }

    /**
     * Resolve one canonical relationship for a bounded list of projected
     * source rows inside one already-pinned dataset.
     *
     * @param array<string,mixed> $dataset
     * @param array<int,array<string,mixed>> $sourceRows
     *
     * @return array<int,array{
     *     source_identity:array<string,mixed>,
     *     relationship:array<string,string>,
     *     source_relation_value_missing:bool,
     *     target_found:bool,
     *     target:array<string,mixed>|null
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
            > self::MAX_SOURCE_ROWS
        ) {
            throw new InvalidArgumentException(
                'La navegación proyectada por lote admite '
                .'un máximo de 500 filas fuente por llamada.'
            );
        }

        $relationship =
            $this->relationship(
                $fromDomain,
                $toDomain
            );

        $fromDomain =
            (string) $relationship[
                'from_domain'
            ];

        $toDomain =
            (string) $relationship[
                'to_domain'
            ];

        $fromField =
            (string) $relationship[
                'from_field'
            ];

        $toField =
            (string) $relationship[
                'to_field'
            ];

        /*
         * Source rows remain ordered exactly as supplied.
         *
         * Internal source hashes are used only to reject duplicate logical
         * rows. They never cross the projected boundary.
         */
        $sources = [];
        $seenSourceHashes = [];
        $requiredTargetHashes = [];

        foreach (
            $sourceRows
            as $sourceRow
        ) {
            $canonicalSource =
                $this->canonicalSourceRow(
                    $fromDomain,
                    $sourceRow
                );

            $sourceIdentity =
                $canonicalSource[
                    'identity'
                ];

            $sourceHash =
                $this->canonicalIdentity
                    ->hashForIdentityValues(
                        $fromDomain,
                        $sourceIdentity
                    );

            if (
                isset(
                    $seenSourceHashes[
                        $sourceHash
                    ]
                )
            ) {
                throw new InvalidArgumentException(
                    'La navegación proyectada recibió '
                    .'una fila fuente canónica duplicada.'
                );
            }

            $seenSourceHashes[
                $sourceHash
            ] =
                true;

            $relationValue =
                $canonicalSource[
                    'fields'
                ][
                    $fromField
                ]
                ?? null;

            $missing =
                $relationValue === null
                || (
                    is_string($relationValue)
                    && trim($relationValue) === ''
                );

            $targetHash =
                null;

            if (! $missing) {
                $targetHash =
                    $this->canonicalIdentity
                        ->hashForIdentityValues(
                            $toDomain,
                            [
                                $toField =>
                                    $relationValue,
                            ]
                        );

                $requiredTargetHashes[
                    $targetHash
                ] =
                    true;
            }

            $sources[] = [
                'identity' =>
                    $sourceIdentity,

                'target_hash' =>
                    $targetHash,

                'missing' =>
                    $missing,
            ];
        }

        /*
         * Exactly one bounded projected lookup for this relationship/chunk.
         *
         * P21-B delegates to P14's indexed bulk lookup and strips the raw
         * prepared-row envelope before returning target rows.
         */
        $targetRows =
            $this->projectedDatasetReader
                ->findDomainRowsByCanonicalIdentityHashesInDataset(
                    $companyId,
                    $dataset,
                    $toDomain,
                    array_keys(
                        $requiredTargetHashes
                    )
                );

        $resolved = [];

        foreach (
            $sources
            as $source
        ) {
            $targetHash =
                $source[
                    'target_hash'
                ];

            if ($targetHash === null) {
                $resolved[] = [
                    'source_identity' =>
                        $source[
                            'identity'
                        ],

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
                $targetRows[
                    $targetHash
                ]
                ?? null;

            /*
             * P16 guarantees referential integrity for newly completed
             * datasets. Projected navigation remains defensive and
             * fail-closed if a nonblank relation cannot be resolved.
             */
            if (! is_array($target)) {
                throw new RuntimeException(
                    'Una relación canónica proyectada no pudo '
                    .'resolverse dentro del dataset fijado.'
                );
            }

            $resolved[] = [
                'source_identity' =>
                    $source[
                        'identity'
                    ],

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
     * Resolve the current usable dataset once, freeze it, then stream one
     * projected relationship in bounded chunks.
     *
     * @return \Generator<int,array{
     *     source_identity:array<string,mixed>,
     *     relationship:array<string,string>,
     *     source_relation_value_missing:bool,
     *     target_found:bool,
     *     target:array<string,mixed>|null
     * }>
     */
    public function iterateResolvedRelationship(
        int $companyId,
        int $implementationRequestId,
        string $fromDomain,
        string $toDomain,
        int $chunkSize = self::MAX_SOURCE_ROWS
    ): \Generator {
        if (
            $chunkSize <= 0
            || $chunkSize > self::MAX_SOURCE_ROWS
        ) {
            throw new InvalidArgumentException(
                'El tamaño del lote de relaciones proyectadas '
                .'debe estar entre 1 y 500.'
            );
        }

        /*
         * Validate relationship before dataset access.
         */
        $this->relationship(
            $fromDomain,
            $toDomain
        );

        /*
         * Resolve P13 exactly once for the entire relationship stream.
         */
        $resolvedDataset =
            $this->usableDatasetResolver
                ->forRequest(
                    $companyId,
                    $implementationRequestId
                );

        if (
            ($resolvedDataset['available'] ?? false)
            !== true
            || ! is_array(
                $resolvedDataset[
                    'dataset'
                ]
                ?? null
            )
        ) {
            return;
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolvedDataset[
                'dataset'
            ];

        $chunk = [];

        foreach (
            $this->projectedDatasetReader
                ->iterateDomainInDataset(
                    $companyId,
                    $dataset,
                    $fromDomain,
                    $chunkSize
                )
            as $sourceRow
        ) {
            if (! is_array($sourceRow)) {
                throw new RuntimeException(
                    'El lector proyectado produjo '
                    .'una fila fuente inválida.'
                );
            }

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
     * @param array<int,array<string,mixed>> $chunk
     */
    private function yieldResolvedChunk(
        int $companyId,
        array $dataset,
        string $fromDomain,
        string $toDomain,
        array $chunk
    ): \Generator {
        $resolved =
            $this->resolveBulkTargetsInDataset(
                $companyId,
                $dataset,
                $fromDomain,
                $chunk,
                $toDomain
            );

        foreach (
            $resolved
            as $item
        ) {
            yield $item;
        }
    }

    /**
     * @param array<string,mixed> $sourceRow
     *
     * @return array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }
     */
    private function canonicalSourceRow(
        string $fromDomain,
        array $sourceRow
    ): array {
        if (
            array_keys(
                $sourceRow
            )
            !== [
                'domain',
                'identity',
                'fields',
            ]
        ) {
            throw new InvalidArgumentException(
                'Cada fila fuente debe usar exactamente '
                .'el contrato proyectado domain, identity, fields.'
            );
        }

        if (
            ($sourceRow['domain'] ?? null)
            !== $fromDomain
        ) {
            throw new InvalidArgumentException(
                'El dominio de la fila fuente no coincide '
                .'con fromDomain.'
            );
        }

        $identity =
            $sourceRow[
                'identity'
            ]
            ?? null;

        $fields =
            $sourceRow[
                'fields'
            ]
            ?? null;

        if (
            ! is_array($identity)
            || ! is_array($fields)
        ) {
            throw new InvalidArgumentException(
                'La fila fuente proyectada requiere '
                .'identity y fields válidos.'
            );
        }

        /*
         * Re-project fields through P21-A. This validates canonical field
         * order, required values, logical runtime types and identity.
         */
        try {
            $canonical =
                $this->projection
                    ->project(
                        $fromDomain,
                        $fields
                    );
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException(
                'La fila fuente proyectada no cumple '
                .'el contrato canónico.',
                0,
                $exception
            );
        }

        if (
            $canonical !== $sourceRow
        ) {
            throw new InvalidArgumentException(
                'La fila fuente no coincide con '
                .'la proyección canónica autoritativa.'
            );
        }

        return $canonical;
    }

    /**
     * @return array<string,string>
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
                    static fn (
                        array $relationship
                    ): bool =>
                        (
                            $relationship[
                                'from_domain'
                            ]
                            ?? null
                        )
                        === $fromDomain
                        && (
                            $relationship[
                                'to_domain'
                            ]
                            ?? null
                        )
                        === $toDomain
                )
            );

        if ($matches === []) {
            throw new InvalidArgumentException(
                "No existe una relación canónica proyectada "
                ."{$fromDomain} -> {$toDomain}."
            );
        }

        if (count($matches) !== 1) {
            throw new RuntimeException(
                "La relación canónica proyectada "
                ."{$fromDomain} -> {$toDomain} es ambigua."
            );
        }

        return $matches[0];
    }

    private function canonicalDomain(
        string $domain
    ): string {
        $domain =
            trim(
                $domain
            );

        if (
            ! in_array(
                $domain,
                DataTransformationBiStandardIntakeSchema
                    ::domainKeys(),
                true
            )
        ) {
            throw new InvalidArgumentException(
                "Dominio canónico no soportado: {$domain}."
            );
        }

        return $domain;
    }
}
