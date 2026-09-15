<?php

namespace App\Services\Diagnosis;

use ArrayIterator;
use InvalidArgumentException;
use Iterator;
use IteratorIterator;
use RuntimeException;
use Traversable;

final class DataTransformationBiCanonicalDatasetDelta
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 5000;

    public const CHANGE_ADDED = 'added';

    public const CHANGE_REMOVED = 'removed';

    public const CHANGE_MODIFIED = 'modified';

    public const CHANGE_UNCHANGED = 'unchanged';

    public function __construct(
        private readonly DataTransformationBiVersionedDatasetResolver
            $versionedDatasetResolver,
        private readonly DataTransformationBiCanonicalRowSignatureReader
            $signatureReader
    ) {
    }

    /**
     * Compare one canonical domain between two explicit historical datasets.
     *
     * The versioned pair is resolved exactly once. Both signature streams
     * remain pinned to their respective run + batch for the entire merge.
     *
     * @return \Generator<int,array{
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_row_id:int|null,
     *     target_normalized_row_id:int|null,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }>
     */
    public function iterateDomainDelta(
        int $companyId,
        int $implementationRequestId,
        int $baseProcessingRunId,
        int $targetProcessingRunId,
        string $domain,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        $domain =
            $this->canonicalDomain(
                $domain
            );

        $pageSize =
            $this->pageSize(
                $pageSize
            );

        /*
         * Preserve the original P18 contract: resolve the ordered,
         * compatible historical pair exactly once.
         */
        $pair =
            $this->versionedDatasetResolver
                ->pair(
                    $companyId,
                    $implementationRequestId,
                    $baseProcessingRunId,
                    $targetProcessingRunId
                );

        if (
            ($pair['available'] ?? false)
            !== true
            || ! is_array(
                $pair['base']
                ?? null
            )
            || ! is_array(
                $pair['target']
                ?? null
            )
        ) {
            $reason =
                trim(
                    (string) (
                        $pair['reason']
                        ?? 'dataset_pair_not_available'
                    )
                );

            throw new RuntimeException(
                'No se puede comparar el par de datasets canónicos: '
                .$reason.'.'
            );
        }

        /** @var array<string,mixed> $baseDataset */
        $baseDataset =
            $pair['base'];

        /** @var array<string,mixed> $targetDataset */
        $targetDataset =
            $pair['target'];

        /*
         * P20-A delegates the actual canonical comparison to the pinned
         * primitive so downstream dataset-wide consumers can reuse the
         * already-frozen descriptors without resolving the pair again.
         */
        yield from $this->iterateDomainDeltaInDatasets(
            $companyId,
            $baseDataset,
            $targetDataset,
            $domain,
            $pageSize
        );
    }

    /**
     * Compare one canonical domain between two already-resolved datasets.
     *
     * The caller owns dataset resolution and compatibility. This primitive
     * never resolves P13/P18 and remains pinned to the supplied run+batch
     * descriptors for the complete stream.
     *
     * @param array<string,mixed> $baseDataset
     * @param array<string,mixed> $targetDataset
     *
     * @return \Generator<int,array{
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_row_id:int|null,
     *     target_normalized_row_id:int|null,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }>
     */
    public function iterateDomainDeltaInDatasets(
        int $companyId,
        array $baseDataset,
        array $targetDataset,
        string $domain,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        $domain =
            $this->canonicalDomain(
                $domain
            );

        $pageSize =
            $this->pageSize(
                $pageSize
            );

        $baseSignatures =
            $this->signatureReader
                ->iterateDomainSignaturesInDataset(
                    $companyId,
                    $baseDataset,
                    $domain,
                    $pageSize
                );

        $targetSignatures =
            $this->signatureReader
                ->iterateDomainSignaturesInDataset(
                    $companyId,
                    $targetDataset,
                    $domain,
                    $pageSize
                );

        yield from self::mergeSignatureStreams(
            $baseSignatures,
            $targetSignatures
        );
    }

    /**
     * Pure streaming merge primitive.
     *
     * Both inputs must be strictly ordered by canonical_identity_hash.
     * Memory usage remains O(1) outside the bounded pages maintained by the
     * signature readers.
     *
     * @param iterable<int,array<string,mixed>> $baseSignatures
     * @param iterable<int,array<string,mixed>> $targetSignatures
     *
     * @return \Generator<int,array{
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_row_id:int|null,
     *     target_normalized_row_id:int|null,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }>
     */
    public static function mergeSignatureStreams(
        iterable $baseSignatures,
        iterable $targetSignatures
    ): \Generator {
        $baseIterator =
            self::iterator(
                $baseSignatures
            );

        $targetIterator =
            self::iterator(
                $targetSignatures
            );

        $baseIterator->rewind();
        $targetIterator->rewind();

        $basePreviousHash =
            null;

        $targetPreviousHash =
            null;

        $base =
            self::currentSignature(
                $baseIterator,
                'base',
                $basePreviousHash
            );

        $target =
            self::currentSignature(
                $targetIterator,
                'target',
                $targetPreviousHash
            );

        while (
            $base !== null
            || $target !== null
        ) {
            if ($base === null) {
                yield self::deltaRow(
                    self::CHANGE_ADDED,
                    null,
                    $target
                );

                $targetPreviousHash =
                    $target[
                        'canonical_identity_hash'
                    ];

                $targetIterator->next();

                $target =
                    self::currentSignature(
                        $targetIterator,
                        'target',
                        $targetPreviousHash
                    );

                continue;
            }

            if ($target === null) {
                yield self::deltaRow(
                    self::CHANGE_REMOVED,
                    $base,
                    null
                );

                $basePreviousHash =
                    $base[
                        'canonical_identity_hash'
                    ];

                $baseIterator->next();

                $base =
                    self::currentSignature(
                        $baseIterator,
                        'base',
                        $basePreviousHash
                    );

                continue;
            }

            $comparison =
                strcmp(
                    $base[
                        'canonical_identity_hash'
                    ],
                    $target[
                        'canonical_identity_hash'
                    ]
                );

            if ($comparison < 0) {
                yield self::deltaRow(
                    self::CHANGE_REMOVED,
                    $base,
                    null
                );

                $basePreviousHash =
                    $base[
                        'canonical_identity_hash'
                    ];

                $baseIterator->next();

                $base =
                    self::currentSignature(
                        $baseIterator,
                        'base',
                        $basePreviousHash
                    );

                continue;
            }

            if ($comparison > 0) {
                yield self::deltaRow(
                    self::CHANGE_ADDED,
                    null,
                    $target
                );

                $targetPreviousHash =
                    $target[
                        'canonical_identity_hash'
                    ];

                $targetIterator->next();

                $target =
                    self::currentSignature(
                        $targetIterator,
                        'target',
                        $targetPreviousHash
                    );

                continue;
            }

            $changeType =
                hash_equals(
                    $base[
                        'normalized_sha256'
                    ],
                    $target[
                        'normalized_sha256'
                    ]
                )
                    ? self::CHANGE_UNCHANGED
                    : self::CHANGE_MODIFIED;

            yield self::deltaRow(
                $changeType,
                $base,
                $target
            );

            $basePreviousHash =
                $base[
                    'canonical_identity_hash'
                ];

            $targetPreviousHash =
                $target[
                    'canonical_identity_hash'
                ];

            $baseIterator->next();
            $targetIterator->next();

            $base =
                self::currentSignature(
                    $baseIterator,
                    'base',
                    $basePreviousHash
                );

            $target =
                self::currentSignature(
                    $targetIterator,
                    'target',
                    $targetPreviousHash
                );
        }
    }

    /**
     * @param iterable<int,array<string,mixed>> $signatures
     */
    private static function iterator(
        iterable $signatures
    ): Iterator {
        if (is_array($signatures)) {
            return new ArrayIterator(
                $signatures
            );
        }

        if ($signatures instanceof Iterator) {
            return $signatures;
        }

        if ($signatures instanceof Traversable) {
            return new IteratorIterator(
                $signatures
            );
        }

        throw new InvalidArgumentException(
            'El stream de firmas canónicas no es iterable.'
        );
    }

    /**
     * @return array{
     *     normalized_row_id:int,
     *     canonical_identity_hash:string,
     *     normalized_sha256:string
     * }|null
     */
    private static function currentSignature(
        Iterator $iterator,
        string $stream,
        ?string $previousHash
    ): ?array {
        if (! $iterator->valid()) {
            return null;
        }

        $signature =
            $iterator->current();

        if (! is_array($signature)) {
            throw new RuntimeException(
                "El stream {$stream} contiene una firma inválida."
            );
        }

        $normalizedRowId =
            (int) (
                $signature[
                    'normalized_row_id'
                ]
                ?? 0
            );

        $canonicalIdentityHash =
            trim(
                (string) (
                    $signature[
                        'canonical_identity_hash'
                    ]
                    ?? ''
                )
            );

        $normalizedSha256 =
            trim(
                (string) (
                    $signature[
                        'normalized_sha256'
                    ]
                    ?? ''
                )
            );

        if ($normalizedRowId <= 0) {
            throw new RuntimeException(
                "El stream {$stream} contiene "
                .'normalized_row_id inválido.'
            );
        }

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $canonicalIdentityHash
            )
            !== 1
        ) {
            throw new RuntimeException(
                "El stream {$stream} contiene "
                .'canonical_identity_hash inválido.'
            );
        }

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $normalizedSha256
            )
            !== 1
        ) {
            throw new RuntimeException(
                "El stream {$stream} contiene "
                .'normalized_sha256 inválido.'
            );
        }

        if (
            $previousHash !== null
            && strcmp(
                $canonicalIdentityHash,
                $previousHash
            )
            <= 0
        ) {
            throw new RuntimeException(
                "El stream {$stream} de firmas canónicas "
                .'no mantiene orden estricto.'
            );
        }

        return [
            'normalized_row_id' =>
                $normalizedRowId,

            'canonical_identity_hash' =>
                $canonicalIdentityHash,

            'normalized_sha256' =>
                $normalizedSha256,
        ];
    }

    /**
     * @param array<string,mixed>|null $base
     * @param array<string,mixed>|null $target
     *
     * @return array{
     *     canonical_identity_hash:string,
     *     change_type:string,
     *     base_normalized_row_id:int|null,
     *     target_normalized_row_id:int|null,
     *     base_normalized_sha256:string|null,
     *     target_normalized_sha256:string|null
     * }
     */
    private static function deltaRow(
        string $changeType,
        ?array $base,
        ?array $target
    ): array {
        if (
            ! in_array(
                $changeType,
                [
                    self::CHANGE_ADDED,
                    self::CHANGE_REMOVED,
                    self::CHANGE_MODIFIED,
                    self::CHANGE_UNCHANGED,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Tipo de cambio canónico inválido.'
            );
        }

        $baseHash =
            $base[
                'canonical_identity_hash'
            ]
            ?? null;

        $targetHash =
            $target[
                'canonical_identity_hash'
            ]
            ?? null;

        if (
            $baseHash !== null
            && $targetHash !== null
            && $baseHash !== $targetHash
        ) {
            throw new RuntimeException(
                'No se pueden combinar firmas '
                .'de identidades canónicas distintas.'
            );
        }

        $canonicalIdentityHash =
            $targetHash
            ?? $baseHash;

        if (
            ! is_string(
                $canonicalIdentityHash
            )
            || preg_match(
                '/^[a-f0-9]{64}$/',
                $canonicalIdentityHash
            )
                !== 1
        ) {
            throw new RuntimeException(
                'El delta no contiene una '
                .'identidad canónica válida.'
            );
        }

        return [
            'canonical_identity_hash' =>
                $canonicalIdentityHash,

            'change_type' =>
                $changeType,

            'base_normalized_row_id' =>
                $base !== null
                    ? (int) $base[
                        'normalized_row_id'
                    ]
                    : null,

            'target_normalized_row_id' =>
                $target !== null
                    ? (int) $target[
                        'normalized_row_id'
                    ]
                    : null,

            'base_normalized_sha256' =>
                $base[
                    'normalized_sha256'
                ]
                ?? null,

            'target_normalized_sha256' =>
                $target[
                    'normalized_sha256'
                ]
                ?? null,
        ];
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

    private function pageSize(
        int $pageSize
    ): int {
        if (
            $pageSize <= 0
            || $pageSize > self::MAX_PAGE_SIZE
        ) {
            throw new InvalidArgumentException(
                'El tamaño de página del delta debe estar '
                .'entre 1 y 5000.'
            );
        }

        return $pageSize;
    }
}
