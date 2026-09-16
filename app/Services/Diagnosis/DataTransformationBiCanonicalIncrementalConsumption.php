<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalIncrementalConsumption
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 500;

    public function __construct(
        private readonly DataTransformationBiCanonicalDatasetManifestResolver $manifestResolver,
        private readonly DataTransformationBiCanonicalDatasetChangeSet $changeSet,
        private readonly DataTransformationBiProjectedDatasetReader $projectedDatasetReader,
        private readonly DataTransformationBiCanonicalIdentity $canonicalIdentity
    ) {
    }

    /**
     * Resolve one compatible base → target pair through P19 exactly once
     * and expose the semantic incremental stream.
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     change_type:string,
     *     identity:array<string,mixed>,
     *     before:array<string,mixed>|null,
     *     after:array<string,mixed>|null
     * }>
     */
    public function iterateDatasetChanges(
        int $companyId,
        int $implementationRequestId,
        int $baseProcessingRunId,
        int $targetProcessingRunId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        $pair =
            $this->manifestResolver
                ->pair(
                    $companyId,
                    $implementationRequestId,
                    $baseProcessingRunId,
                    $targetProcessingRunId,
                    $pageSize
                );

        if (
            ($pair['available'] ?? false)
            !== true
        ) {
            $reason =
                trim(
                    (string) (
                        $pair['reason']
                        ?? 'dataset_pair_not_available'
                    )
                );

            throw new RuntimeException(
                'No se puede construir el consumo incremental canónico: '
                .$reason.'.'
            );
        }

        yield from $this->iterateResolvedPair(
            $pair,
            $pageSize
        );
    }

    /**
     * Consume one already-resolved P19 pair without resolving datasets again.
     *
     * P20 owns stable change detection. P21 owns projected row access.
     * canonical_identity_hash is used only internally to join both contracts.
     *
     * @param array<string,mixed> $pair
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     change_type:string,
     *     identity:array<string,mixed>,
     *     before:array<string,mixed>|null,
     *     after:array<string,mixed>|null
     * }>
     */
    public function iterateResolvedPair(
        array $pair,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        [
            $companyId,
            $baseDataset,
            $targetDataset,
        ] =
            $this->pinnedPair(
                $pair
            );

        $currentDomain = null;
        $chunk = [];

        foreach (
            $this->changeSet
                ->iterateResolvedPair(
                    $pair,
                    $pageSize
                )
            as $change
        ) {
            $change =
                $this->stableChange(
                    $change
                );

            $domain =
                $change['domain'];

            if (
                $currentDomain !== null
                && $domain !== $currentDomain
            ) {
                yield from $this->projectChunk(
                    $companyId,
                    $baseDataset,
                    $targetDataset,
                    $currentDomain,
                    $chunk
                );

                $chunk = [];
            }

            $currentDomain =
                $domain;

            $chunk[] =
                $change;

            if (
                count($chunk)
                >= $pageSize
            ) {
                yield from $this->projectChunk(
                    $companyId,
                    $baseDataset,
                    $targetDataset,
                    $currentDomain,
                    $chunk
                );

                $chunk = [];
            }
        }

        if (
            $currentDomain !== null
            && $chunk !== []
        ) {
            yield from $this->projectChunk(
                $companyId,
                $baseDataset,
                $targetDataset,
                $currentDomain,
                $chunk
            );
        }
    }

    /**
     * @param array<int,array<string,mixed>> $changes
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     change_type:string,
     *     identity:array<string,mixed>,
     *     before:array<string,mixed>|null,
     *     after:array<string,mixed>|null
     * }>
     */
    private function projectChunk(
        int $companyId,
        array $baseDataset,
        array $targetDataset,
        string $domain,
        array $changes
    ): \Generator {
        if (
            $changes === []
            || count($changes) > self::MAX_PAGE_SIZE
        ) {
            throw new RuntimeException(
                'El bloque incremental canónico debe contener entre 1 y 500 cambios.'
            );
        }

        $baseHashes = [];
        $targetHashes = [];

        foreach ($changes as $change) {
            if (
                ! is_array($change)
                || ($change['domain'] ?? null)
                    !== $domain
            ) {
                throw new RuntimeException(
                    'El bloque incremental contiene dominios mezclados.'
                );
            }

            $hash =
                $change[
                    'canonical_identity_hash'
                ];

            switch (
                $change['change_type']
            ) {
                case 'added':
                    $targetHashes[] =
                        $hash;
                    break;

                case 'removed':
                    $baseHashes[] =
                        $hash;
                    break;

                case 'modified':
                case 'unchanged':
                    $baseHashes[] =
                        $hash;

                    $targetHashes[] =
                        $hash;
                    break;

                default:
                    throw new RuntimeException(
                        'El bloque incremental contiene un change_type no soportado.'
                    );
            }
        }

        $baseRows =
            $baseHashes === []
                ? []
                : $this->projectedDatasetReader
                    ->findDomainRowsByCanonicalIdentityHashesInDataset(
                        $companyId,
                        $baseDataset,
                        $domain,
                        $baseHashes
                    );

        $targetRows =
            $targetHashes === []
                ? []
                : $this->projectedDatasetReader
                    ->findDomainRowsByCanonicalIdentityHashesInDataset(
                        $companyId,
                        $targetDataset,
                        $domain,
                        $targetHashes
                    );

        foreach ($changes as $change) {
            $hash =
                $change[
                    'canonical_identity_hash'
                ];

            $changeType =
                $change[
                    'change_type'
                ];

            $needsBase =
                in_array(
                    $changeType,
                    [
                        'removed',
                        'modified',
                        'unchanged',
                    ],
                    true
                );

            $needsTarget =
                in_array(
                    $changeType,
                    [
                        'added',
                        'modified',
                        'unchanged',
                    ],
                    true
                );

            $before =
                $needsBase
                    ? (
                        $baseRows[$hash]
                        ?? null
                    )
                    : null;

            $after =
                $needsTarget
                    ? (
                        $targetRows[$hash]
                        ?? null
                    )
                    : null;

            if (
                $needsBase
                && ! is_array($before)
            ) {
                throw new RuntimeException(
                    "Falta la fila proyectada base para {$domain}."
                );
            }

            if (
                $needsTarget
                && ! is_array($after)
            ) {
                throw new RuntimeException(
                    "Falta la fila proyectada target para {$domain}."
                );
            }

            yield $this->semanticChangeRecord(
                $change,
                $before,
                $after
            );
        }
    }

    /**
     * @param array<string,mixed> $change
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     *
     * @return array{
     *     domain:string,
     *     change_type:string,
     *     identity:array<string,mixed>,
     *     before:array<string,mixed>|null,
     *     after:array<string,mixed>|null
     * }
     */
    private function semanticChangeRecord(
        array $change,
        ?array $before,
        ?array $after
    ): array {
        $change =
            $this->stableChange(
                $change
            );

        $domain =
            $change['domain'];

        $hash =
            $change[
                'canonical_identity_hash'
            ];

        $changeType =
            $change[
                'change_type'
            ];

        $expectsBefore =
            in_array(
                $changeType,
                [
                    'removed',
                    'modified',
                    'unchanged',
                ],
                true
            );

        $expectsAfter =
            in_array(
                $changeType,
                [
                    'added',
                    'modified',
                    'unchanged',
                ],
                true
            );

        if (
            $expectsBefore
            !== ($before !== null)
            || $expectsAfter
                !== ($after !== null)
        ) {
            throw new RuntimeException(
                'before/after no coincide con la semántica del cambio canónico.'
            );
        }

        $before =
            $before === null
                ? null
                : $this->projectedRow(
                    $domain,
                    $hash,
                    $before,
                    'before'
                );

        $after =
            $after === null
                ? null
                : $this->projectedRow(
                    $domain,
                    $hash,
                    $after,
                    'after'
                );

        if (
            $before !== null
            && $after !== null
            && $before['identity']
                !== $after['identity']
        ) {
            throw new RuntimeException(
                'before y after no representan la misma identidad semántica.'
            );
        }

        $identity =
            $after['identity']
            ?? $before['identity']
            ?? null;

        if (! is_array($identity)) {
            throw new RuntimeException(
                'El cambio incremental no contiene identidad semántica.'
            );
        }

        return [
            'domain' =>
                $domain,

            'change_type' =>
                $changeType,

            'identity' =>
                $identity,

            'before' =>
                $before,

            'after' =>
                $after,
        ];
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }
     */
    private function projectedRow(
        string $domain,
        string $expectedCanonicalIdentityHash,
        array $row,
        string $side
    ): array {
        if (
            array_keys($row)
            !== [
                'domain',
                'identity',
                'fields',
            ]
            || ($row['domain'] ?? null)
                !== $domain
            || ! is_array(
                $row['identity']
                ?? null
            )
            || ! is_array(
                $row['fields']
                ?? null
            )
        ) {
            throw new RuntimeException(
                "La fila proyectada {$side} no respeta el contrato P21."
            );
        }

        $actualHash =
            $this->canonicalIdentity
                ->hashForIdentityValues(
                    $domain,
                    $row['identity']
                );

        if (
            ! hash_equals(
                $expectedCanonicalIdentityHash,
                $actualHash
            )
        ) {
            throw new RuntimeException(
                "La identidad semántica {$side} no coincide con el cambio canónico."
            );
        }

        return [
            'domain' =>
                $domain,

            'identity' =>
                $row['identity'],

            'fields' =>
                $row['fields'],
        ];
    }

    /**
     * @param array<string,mixed> $change
     *
     * @return array{
     *     domain:string,
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }
     */
    private function stableChange(
        array $change
    ): array {
        $expectedKeys = [
            'domain',
            'canonical_identity_hash',
            'change_type',
            'base_normalized_sha256',
            'target_normalized_sha256',
        ];

        if (
            array_keys($change)
            !== $expectedKeys
        ) {
            throw new RuntimeException(
                'La fila P20 no respeta el contrato estable de cambio.'
            );
        }

        $domain =
            $change['domain'];

        if (
            ! is_string($domain)
            || ! in_array(
                $domain,
                DataTransformationBiCanonicalDatasetContext
                    ::domains(),
                true
            )
        ) {
            throw new RuntimeException(
                'La fila P20 contiene un dominio canónico inválido.'
            );
        }

        $hash =
            $change[
                'canonical_identity_hash'
            ];

        if (
            ! is_string($hash)
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $hash
            )
            !== 1
        ) {
            throw new RuntimeException(
                'La fila P20 contiene canonical_identity_hash inválido.'
            );
        }

        $changeType =
            $change[
                'change_type'
            ];

        if (
            ! in_array(
                $changeType,
                [
                    'added',
                    'removed',
                    'modified',
                    'unchanged',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'La fila P20 contiene change_type inválido.'
            );
        }

        foreach (
            [
                'base_normalized_sha256',
                'target_normalized_sha256',
            ]
            as $field
        ) {
            $value =
                $change[$field];

            if (
                $value !== null
                && (
                    ! is_string($value)
                    || preg_match(
                        '/^[a-f0-9]{64}$/',
                        $value
                    )
                    !== 1
                )
            ) {
                throw new RuntimeException(
                    "La fila P20 contiene {$field} inválido."
                );
            }
        }

        return $change;
    }

    /**
     * Extract pinned datasets from an already resolved P19 pair.
     * P20 remains authoritative for complete pair validation when its
     * iterateResolvedPair() stream begins.
     *
     * @param array<string,mixed> $pair
     *
     * @return array{0:int,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function pinnedPair(
        array $pair
    ): array {
        if (
            ($pair['available'] ?? false)
            !== true
        ) {
            throw new RuntimeException(
                'El par incremental canónico no está disponible.'
            );
        }

        $base =
            $pair['base']
            ?? null;

        $target =
            $pair['target']
            ?? null;

        if (
            ! is_array($base)
            || ! is_array($target)
        ) {
            throw new RuntimeException(
                'El par incremental no contiene base y target válidos.'
            );
        }

        $baseContext =
            $base['context']
            ?? null;

        $targetContext =
            $target['context']
            ?? null;

        if (
            ! is_array($baseContext)
            || ! is_array($targetContext)
        ) {
            throw new RuntimeException(
                'El par incremental no contiene contextos canónicos válidos.'
            );
        }

        $baseIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $baseContext
                );

        $targetIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $targetContext
                );

        if (
            $baseIdentity['company_id']
                !== $targetIdentity['company_id']
            || $baseIdentity['implementation_request_id']
                !== $targetIdentity['implementation_request_id']
        ) {
            throw new RuntimeException(
                'Base y target no pertenecen al mismo contexto empresarial.'
            );
        }

        if (
            $targetIdentity['processing_run_id']
            <= $baseIdentity['processing_run_id']
        ) {
            throw new RuntimeException(
                'El par incremental requiere dirección base → target válida.'
            );
        }

        return [
            $baseIdentity['company_id'],

            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $baseContext
                ),

            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $targetContext
                ),
        ];
    }

    private function pageSize(
        int $pageSize
    ): int {
        if (
            $pageSize <= 0
            || $pageSize > self::MAX_PAGE_SIZE
        ) {
            throw new InvalidArgumentException(
                'El tamaño de bloque incremental debe estar entre 1 y 500.'
            );
        }

        return $pageSize;
    }
}
