<?php

namespace App\Services\Diagnosis;

use RuntimeException;

final class DataTransformationBiCanonicalDatasetChangeSet
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 5000;

    public function __construct(
        private readonly DataTransformationBiCanonicalDatasetManifestResolver $manifestResolver,
        private readonly DataTransformationBiCanonicalDatasetDelta $domainDelta
    ) {
    }

    /**
     * Resolve one compatible historical pair exactly once through P19,
     * freeze both sides and stream every canonical domain from that pair.
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }>
     */
    public function iterateDatasetDelta(
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
                'No se puede construir el change set canónico: '
                .$reason.'.'
            );
        }

        yield from $this->iterateResolvedPair(
            $pair,
            $pageSize
        );
    }

    /**
     * Stream a previously resolved P19 pair without re-resolving either
     * dataset. This is the primitive P20-C can consume while keeping the
     * exact same frozen contexts and manifests.
     *
     * @param array<string,mixed> $pair
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
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
            $this->pinnedDatasets(
                $pair
            );

        foreach (
            DataTransformationBiCanonicalDatasetContext
                ::domains()
            as $domain
        ) {
            foreach (
                $this->domainDelta
                    ->iterateDomainDeltaInDatasets(
                        $companyId,
                        $baseDataset,
                        $targetDataset,
                        $domain,
                        $pageSize
                    )
                as $deltaRow
            ) {
                if (! is_array($deltaRow)) {
                    throw new RuntimeException(
                        'El delta canónico produjo una fila inválida.'
                    );
                }

                if (
                    array_key_exists(
                        'domain',
                        $deltaRow
                    )
                ) {
                    throw new RuntimeException(
                        'La fila delta contiene un dominio inesperado.'
                    );
                }

                yield $this->stableChangeRecord(
                    $domain,
                    $deltaRow
                );
            }
        }
    }

    /**
     * Validate and extract the frozen technical datasets from a P19 pair.
     *
     * @param array<string,mixed> $pair
     *
     * @return array{
     *     0:int,
     *     1:array<string,mixed>,
     *     2:array<string,mixed>
     * }
     */

    /**
     * Resolve one compatible historical pair exactly once and summarize
     * its complete canonical dataset delta.
     *
     * @return array<string,mixed>
     */
    public function summarizeDatasetDelta(
        int $companyId,
        int $implementationRequestId,
        int $baseProcessingRunId,
        int $targetProcessingRunId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
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
                'No se puede resumir el change set canónico: '
                .$reason.'.'
            );
        }

        return $this->summarizeResolvedPair(
            $pair,
            $pageSize
        );
    }

    /**
     * Summarize a previously resolved P19 pair without re-resolving it.
     *
     * @param array<string,mixed> $pair
     * @return array<string,mixed>
     */
    public function summarizeResolvedPair(
        array $pair,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        $base =
            $pair['base']
            ?? null;

        $target =
            $pair['target']
            ?? null;

        $sameContent =
            $pair['same_content']
            ?? null;

        if (
            ! is_array($base)
            || ! is_array($target)
            || ! is_bool($sameContent)
        ) {
            throw new RuntimeException(
                'El par resuelto no contiene datos suficientes '
                .'para resumir el change set.'
            );
        }

        $baseManifest =
            $base['manifest']
            ?? null;

        $targetManifest =
            $target['manifest']
            ?? null;

        if (
            ! is_array($baseManifest)
            || ! is_array($targetManifest)
        ) {
            throw new RuntimeException(
                'El resumen requiere ambos manifests canónicos.'
            );
        }

        return $this->summarizeRows(
            $baseManifest,
            $targetManifest,
            $sameContent,
            $this->iterateResolvedPair(
                $pair,
                $pageSize
            )
        );
    }

    /**
     * Pure streaming reconciliation primitive used by P20-C.
     *
     * @param array<string,mixed> $baseManifest
     * @param array<string,mixed> $targetManifest
     * @param iterable<array<string,mixed>> $rows
     *
     * @return array<string,mixed>
     */
    public function summarizeRows(
        array $baseManifest,
        array $targetManifest,
        bool $sameContent,
        iterable $rows
    ): array {
        $base =
            $this->manifestSummary(
                $baseManifest,
                'base'
            );

        $target =
            $this->manifestSummary(
                $targetManifest,
                'target'
            );

        $fingerprintsEqual =
            hash_equals(
                $base['dataset_fingerprint'],
                $target['dataset_fingerprint']
            );

        if (
            $sameContent
            !== $fingerprintsEqual
        ) {
            throw new RuntimeException(
                'same_content no coincide con los fingerprints '
                .'del resumen canónico.'
            );
        }

        $domains =
            DataTransformationBiCanonicalDatasetContext
                ::domains();

        $domainPosition =
            array_flip(
                $domains
            );

        $summaryDomains = [];

        foreach ($domains as $domain) {
            $summaryDomains[$domain] = [
                'base_rows' =>
                    $base['domains'][$domain],

                'target_rows' =>
                    $target['domains'][$domain],

                'added' =>
                    0,

                'removed' =>
                    0,

                'modified' =>
                    0,

                'unchanged' =>
                    0,

                'changed_rows' =>
                    0,
            ];
        }

        $counts = [
            'added' =>
                0,

            'removed' =>
                0,

            'modified' =>
                0,

            'unchanged' =>
                0,
        ];

        $previousDomainPosition =
            null;

        $previousIdentityByDomain =
            [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new RuntimeException(
                    'El resumen recibió una fila delta inválida.'
                );
            }

            $domain =
                $row['domain']
                ?? null;

            if (
                ! is_string($domain)
                || ! array_key_exists(
                    $domain,
                    $domainPosition
                )
            ) {
                throw new RuntimeException(
                    'El resumen recibió un dominio no canónico.'
                );
            }

            $position =
                $domainPosition[$domain];

            if (
                $previousDomainPosition !== null
                && $position < $previousDomainPosition
            ) {
                throw new RuntimeException(
                    'El stream dataset-wide no respeta '
                    .'el orden canónico de dominios.'
                );
            }

            $identity =
                $this->sha256(
                    $row[
                        'canonical_identity_hash'
                    ]
                    ?? null,
                    'canonical_identity_hash'
                );

            $previousIdentity =
                $previousIdentityByDomain[
                    $domain
                ]
                ?? null;

            if (
                $previousIdentity !== null
                && strcmp(
                    $identity,
                    $previousIdentity
                )
                <= 0
            ) {
                throw new RuntimeException(
                    'Las identidades del dominio no avanzan '
                    .'en orden canónico estricto.'
                );
            }

            $changeType =
                $row[
                    'change_type'
                ]
                ?? null;

            if (
                ! is_string($changeType)
                || ! in_array(
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
                    'El resumen recibió un change_type inválido.'
                );
            }

            $summaryDomains[
                $domain
            ][
                $changeType
            ]++;

            $counts[
                $changeType
            ]++;

            $previousIdentityByDomain[
                $domain
            ] =
                $identity;

            $previousDomainPosition =
                $position;
        }

        foreach (
            $domains
            as $domain
        ) {
            $entry =
                &$summaryDomains[
                    $domain
                ];

            $baseRowsFromDelta =
                $entry['removed']
                + $entry['modified']
                + $entry['unchanged'];

            $targetRowsFromDelta =
                $entry['added']
                + $entry['modified']
                + $entry['unchanged'];

            if (
                $baseRowsFromDelta
                !== $entry['base_rows']
            ) {
                throw new RuntimeException(
                    "El dominio {$domain} no reconcilia "
                    .'con el manifest base.'
                );
            }

            if (
                $targetRowsFromDelta
                !== $entry['target_rows']
            ) {
                throw new RuntimeException(
                    "El dominio {$domain} no reconcilia "
                    .'con el manifest target.'
                );
            }

            $entry['changed_rows'] =
                $entry['added']
                + $entry['removed']
                + $entry['modified'];

            unset($entry);
        }

        $baseRowsFromDelta =
            $counts['removed']
            + $counts['modified']
            + $counts['unchanged'];

        $targetRowsFromDelta =
            $counts['added']
            + $counts['modified']
            + $counts['unchanged'];

        if (
            $baseRowsFromDelta
            !== $base['total_rows']
        ) {
            throw new RuntimeException(
                'El resumen global no reconcilia '
                .'con el manifest base.'
            );
        }

        if (
            $targetRowsFromDelta
            !== $target['total_rows']
        ) {
            throw new RuntimeException(
                'El resumen global no reconcilia '
                .'con el manifest target.'
            );
        }

        $changedRows =
            $counts['added']
            + $counts['removed']
            + $counts['modified'];

        if ($sameContent) {
            if (
                $counts['added'] !== 0
                || $counts['removed'] !== 0
                || $counts['modified'] !== 0
            ) {
                throw new RuntimeException(
                    'same_content=true es incompatible '
                    .'con cambios canónicos.'
                );
            }

            if (
                $base['total_rows']
                !== $target['total_rows']
                || $counts['unchanged']
                !== $base['total_rows']
            ) {
                throw new RuntimeException(
                    'same_content=true no reconcilia '
                    .'con las filas sin cambios.'
                );
            }
        }

        return [
            'base_dataset_fingerprint' =>
                $base[
                    'dataset_fingerprint'
                ],

            'target_dataset_fingerprint' =>
                $target[
                    'dataset_fingerprint'
                ],

            'same_content' =>
                $sameContent,

            'base_total_rows' =>
                $base[
                    'total_rows'
                ],

            'target_total_rows' =>
                $target[
                    'total_rows'
                ],

            'counts' =>
                $counts,

            'changed_rows' =>
                $changedRows,

            'domains' =>
                $summaryDomains,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     *
     * @return array{
     *     dataset_fingerprint:string,
     *     total_rows:int,
     *     domains:array<string,int>
     * }
     */
    private function manifestSummary(
        array $manifest,
        string $side
    ): array {
        $datasetFingerprint =
            $this->sha256(
                $manifest[
                    'dataset_fingerprint'
                ]
                ?? null,
                "{$side}.dataset_fingerprint"
            );

        $totalRows =
            $this->nonNegativeInt(
                $manifest[
                    'total_rows'
                ]
                ?? null,
                "{$side}.total_rows"
            );

        $entries =
            $manifest[
                'domains'
            ]
            ?? null;

        if (! is_array($entries)) {
            throw new RuntimeException(
                "El manifest {$side} no contiene dominios válidos."
            );
        }

        $canonicalDomains =
            DataTransformationBiCanonicalDatasetContext
                ::domains();

        if (
            count($entries)
            !== count(
                $canonicalDomains
            )
        ) {
            throw new RuntimeException(
                "El manifest {$side} no contiene "
                .'todos los dominios canónicos.'
            );
        }

        $domainCounts = [];
        $sum = 0;

        foreach (
            $canonicalDomains
            as $index => $domain
        ) {
            $entry =
                $entries[$index]
                ?? null;

            if (
                ! is_array($entry)
                || ($entry['domain'] ?? null)
                    !== $domain
            ) {
                throw new RuntimeException(
                    "El manifest {$side} no respeta "
                    .'el orden canónico de dominios.'
                );
            }

            $rowCount =
                $this->nonNegativeInt(
                    $entry[
                        'row_count'
                    ]
                    ?? null,
                    "{$side}.domains.{$domain}.row_count"
                );

            $this->sha256(
                $entry[
                    'fingerprint'
                ]
                ?? null,
                "{$side}.domains.{$domain}.fingerprint"
            );

            $domainCounts[
                $domain
            ] =
                $rowCount;

            $sum +=
                $rowCount;
        }

        if ($sum !== $totalRows) {
            throw new RuntimeException(
                "El manifest {$side} no reconcilia "
                .'sus dominios con total_rows.'
            );
        }

        return [
            'dataset_fingerprint' =>
                $datasetFingerprint,

            'total_rows' =>
                $totalRows,

            'domains' =>
                $domainCounts,
        ];
    }

    private function nonNegativeInt(
        mixed $value,
        string $field
    ): int {
        if (
            is_int($value)
            && $value >= 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && ctype_digit($value)
        ) {
            return (int) $value;
        }

        throw new RuntimeException(
            "El resumen requiere {$field} no negativo."
        );
    }

    private function sha256(
        mixed $value,
        string $field
    ): string {
        if (! is_string($value)) {
            throw new RuntimeException(
                "El resumen requiere {$field} SHA-256 válido."
            );
        }

        $value =
            strtolower(
                trim(
                    $value
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                "El resumen requiere {$field} SHA-256 válido."
            );
        }

        return $value;
    }

    private function pinnedDatasets(
        array $pair
    ): array {
        if (
            ($pair['available'] ?? false)
            !== true
        ) {
            throw new RuntimeException(
                'El par canónico resuelto no está disponible.'
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
                'El change set requiere ambos lados del par canónico.'
            );
        }

        $baseContext =
            $base['context']
            ?? null;

        $targetContext =
            $target['context']
            ?? null;

        $baseManifest =
            $base['manifest']
            ?? null;

        $targetManifest =
            $target['manifest']
            ?? null;

        if (
            ! is_array($baseContext)
            || ! is_array($targetContext)
            || ! is_array($baseManifest)
            || ! is_array($targetManifest)
        ) {
            throw new RuntimeException(
                'El change set requiere contextos y manifests canónicos.'
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
                'Los lados del change set no pertenecen '
                .'al mismo contexto empresarial.'
            );
        }

        if (
            $targetIdentity['processing_run_id']
            <= $baseIdentity['processing_run_id']
        ) {
            throw new RuntimeException(
                'El change set requiere dirección base → target válida.'
            );
        }

        $baseDataset =
            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $baseContext
                );

        $targetDataset =
            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $targetContext
                );

        $this->validateManifestContext(
            $baseManifest,
            $baseIdentity,
            $baseDataset,
            'base'
        );

        $this->validateManifestContext(
            $targetManifest,
            $targetIdentity,
            $targetDataset,
            'target'
        );

        $baseFingerprint =
            $this->manifestFingerprint(
                $baseManifest,
                'base'
            );

        $targetFingerprint =
            $this->manifestFingerprint(
                $targetManifest,
                'target'
            );

        $sameContent =
            $pair['same_content']
            ?? null;

        if (! is_bool($sameContent)) {
            throw new RuntimeException(
                'El par canónico no contiene same_content válido.'
            );
        }

        if (
            $sameContent
            !== hash_equals(
                $baseFingerprint,
                $targetFingerprint
            )
        ) {
            throw new RuntimeException(
                'same_content no coincide con los fingerprints canónicos.'
            );
        }

        if (! is_array(
            $pair['compatibility']
            ?? null
        )) {
            throw new RuntimeException(
                'El par canónico no contiene compatibilidad válida.'
            );
        }

        return [
            $baseIdentity[
                'company_id'
            ],
            $baseDataset,
            $targetDataset,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,int> $identity
     * @param array<string,mixed> $dataset
     */
    private function validateManifestContext(
        array $manifest,
        array $identity,
        array $dataset,
        string $side
    ): void {
        if (
            ($manifest['company_id'] ?? null)
            !== $identity['company_id']
            || (
                $manifest[
                    'implementation_request_id'
                ]
                ?? null
            )
            !== $identity[
                'implementation_request_id'
            ]
        ) {
            throw new RuntimeException(
                "El manifest {$side} no pertenece al contexto fijado."
            );
        }

        $manifestDataset =
            $manifest['dataset']
            ?? null;

        if (
            ! is_array($manifestDataset)
            || $manifestDataset !== $dataset
        ) {
            throw new RuntimeException(
                "El manifest {$side} no coincide "
                .'con su descriptor canónico.'
            );
        }
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function manifestFingerprint(
        array $manifest,
        string $side
    ): string {
        $fingerprint =
            $manifest[
                'dataset_fingerprint'
            ]
            ?? null;

        if (
            ! is_string($fingerprint)
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $fingerprint
            )
            !== 1
        ) {
            throw new RuntimeException(
                "El manifest {$side} no contiene "
                .'dataset_fingerprint SHA-256 válido.'
            );
        }

        return $fingerprint;
    }

    private function pageSize(
        int $pageSize
    ): int {
        if (
            $pageSize <= 0
            || $pageSize > self::MAX_PAGE_SIZE
        ) {
            throw new RuntimeException(
                'El tamaño de página del change set '
                .'debe estar entre 1 y 5000.'
            );
        }

        return $pageSize;
    }

    /**
     * Project the low-level P18 delta into the storage-stable P20 contract.
     *
     * P18 may retain physical normalized-row ids internally. Those ids
     * deliberately terminate at this boundary and never reach P20 consumers.
     *
     * @param array<string,mixed> $deltaRow
     *
     * @return array{
     *     domain:string,
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }
     */
    private function stableChangeRecord(
        string $domain,
        array $deltaRow
    ): array {
        $domains =
            DataTransformationBiCanonicalDatasetContext
                ::domains();

        if (
            ! in_array(
                $domain,
                $domains,
                true
            )
        ) {
            throw new RuntimeException(
                'El change record recibió un dominio no canónico.'
            );
        }

        $identity =
            $deltaRow[
                'canonical_identity_hash'
            ]
            ?? null;

        if (
            ! is_string($identity)
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $identity
            )
            !== 1
        ) {
            throw new RuntimeException(
                'El change record no contiene '
                .'canonical_identity_hash SHA-256 válido.'
            );
        }

        $changeType =
            $deltaRow[
                'change_type'
            ]
            ?? null;

        if (
            ! is_string($changeType)
            || ! in_array(
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
                'El change record contiene '
                .'change_type inválido.'
            );
        }

        $baseSha =
            $this->nullableChangeSha256(
                $deltaRow[
                    'base_normalized_sha256'
                ]
                ?? null,
                'base_normalized_sha256'
            );

        $targetSha =
            $this->nullableChangeSha256(
                $deltaRow[
                    'target_normalized_sha256'
                ]
                ?? null,
                'target_normalized_sha256'
            );

        if ($changeType === 'added') {
            if (
                $baseSha !== null
                || $targetSha === null
            ) {
                throw new RuntimeException(
                    'Un cambio added requiere solo '
                    .'target_normalized_sha256.'
                );
            }
        } elseif ($changeType === 'removed') {
            if (
                $baseSha === null
                || $targetSha !== null
            ) {
                throw new RuntimeException(
                    'Un cambio removed requiere solo '
                    .'base_normalized_sha256.'
                );
            }
        } elseif ($changeType === 'modified') {
            if (
                $baseSha === null
                || $targetSha === null
                || hash_equals(
                    $baseSha,
                    $targetSha
                )
            ) {
                throw new RuntimeException(
                    'Un cambio modified requiere dos fingerprints '
                    .'canónicos distintos.'
                );
            }
        } else {
            if (
                $baseSha === null
                || $targetSha === null
                || ! hash_equals(
                    $baseSha,
                    $targetSha
                )
            ) {
                throw new RuntimeException(
                    'Un cambio unchanged requiere dos fingerprints '
                    .'canónicos iguales.'
                );
            }
        }

        return [
            'domain' =>
                $domain,

            'canonical_identity_hash' =>
                $identity,

            'change_type' =>
                $changeType,

            'base_normalized_sha256' =>
                $baseSha,

            'target_normalized_sha256' =>
                $targetSha,
        ];
    }

    private function nullableChangeSha256(
        mixed $value,
        string $field
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            ! is_string($value)
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $value
            )
            !== 1
        ) {
            throw new RuntimeException(
                "{$field} debe ser SHA-256 hexadecimal "
                .'minúsculo o null.'
            );
        }

        return $value;
    }

}
