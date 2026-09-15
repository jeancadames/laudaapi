<?php

namespace App\Services\Diagnosis;

use RuntimeException;

final class DataTransformationBiProjectedDatasetReader
{
    private const DEFAULT_PAGE_SIZE = 500;

    public function __construct(
        private readonly DataTransformationBiPreparedDatasetReader $preparedDatasetReader,
        private readonly DataTransformationBiCanonicalDomainProjection $projection
    ) {
    }

    /**
     * Adapt one internal P14 prepared row into the storage-independent
     * canonical domain projection.
     *
     * P14 technical metadata is validated here but deliberately excluded
     * from the returned consumer contract.
     *
     * @param array<string,mixed> $preparedRow
     *
     * @return array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }
     */
    public function projectPreparedRow(
        string $domain,
        array $preparedRow
    ): array {
        $normalizedRowId =
            $preparedRow[
                'normalized_row_id'
            ]
            ?? null;

        if (
            ! is_int($normalizedRowId)
            || $normalizedRowId <= 0
        ) {
            throw new RuntimeException(
                'La fila preparada no contiene '
                .'normalized_row_id técnico válido.'
            );
        }

        $identityHash =
            $preparedRow[
                'identity_hash'
            ]
            ?? null;

        if (
            ! is_string($identityHash)
            || trim($identityHash) === ''
        ) {
            throw new RuntimeException(
                'La fila preparada no contiene '
                .'identity_hash técnico válido.'
            );
        }

        $payload =
            $preparedRow[
                'payload'
            ]
            ?? null;

        if (! is_array($payload)) {
            throw new RuntimeException(
                'La fila preparada no contiene '
                .'payload canónico válido.'
            );
        }

        /*
         * The technical row id, technical hash and raw P14 envelope end
         * here. Consumers receive only the canonical projected contract.
         */
        return $this->projection
            ->project(
                $domain,
                $payload
            );
    }

    /**
     * Iterate the current P13 usable dataset through P14 exactly once.
     *
     * Dataset resolution semantics remain owned by P14.
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }>
     */
    public function iterateDomain(
        int $companyId,
        int $implementationRequestId,
        string $domain,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        foreach (
            $this->preparedDatasetReader
                ->iterateDomain(
                    $companyId,
                    $implementationRequestId,
                    $domain,
                    $pageSize
                )
            as $preparedRow
        ) {
            if (! is_array($preparedRow)) {
                throw new RuntimeException(
                    'P14 produjo una fila preparada inválida.'
                );
            }

            yield $this->projectPreparedRow(
                $domain,
                $preparedRow
            );
        }
    }

    /**
     * Iterate one already-pinned canonical dataset.
     *
     * No P13/P18 resolution occurs here; run + batch are frozen by the
     * supplied dataset descriptor and P14's pinned iterator.
     *
     * @param array<string,mixed> $dataset
     *
     * @return \Generator<int,array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }>
     */
    public function iterateDomainInDataset(
        int $companyId,
        array $dataset,
        string $domain,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        foreach (
            $this->preparedDatasetReader
                ->iterateDomainInDataset(
                    $companyId,
                    $dataset,
                    $domain,
                    $pageSize
                )
            as $preparedRow
        ) {
            if (! is_array($preparedRow)) {
                throw new RuntimeException(
                    'P14 produjo una fila preparada fijada inválida.'
                );
            }

            yield $this->projectPreparedRow(
                $domain,
                $preparedRow
            );
        }
    }

    /**
     * Resolve a bounded set of canonical identities inside one pinned
     * dataset and terminate the raw P14 envelope before returning rows.
     *
     * Result keys remain canonical identity hashes so relationship
     * consumers can perform O(1) target matching without exposing hashes
     * inside each projected row.
     *
     * @param array<string,mixed> $dataset
     * @param array<int,string> $canonicalIdentityHashes
     *
     * @return array<string,array{
     *     domain:string,
     *     identity:array<string,mixed>,
     *     fields:array<string,mixed>
     * }>
     */
    public function findDomainRowsByCanonicalIdentityHashesInDataset(
        int $companyId,
        array $dataset,
        string $domain,
        array $canonicalIdentityHashes
    ): array {
        $preparedRows =
            $this->preparedDatasetReader
                ->findDomainRowsByCanonicalIdentityHashesInDataset(
                    $companyId,
                    $dataset,
                    $domain,
                    $canonicalIdentityHashes
                );

        $projected = [];

        foreach (
            $preparedRows
            as $canonicalIdentityHash => $preparedRow
        ) {
            if (
                ! is_string($canonicalIdentityHash)
                || preg_match(
                    '/^[a-f0-9]{64}$/',
                    $canonicalIdentityHash
                )
                !== 1
            ) {
                throw new RuntimeException(
                    'P14 retornó una clave de identidad '
                    .'canónica inválida.'
                );
            }

            if (! is_array($preparedRow)) {
                throw new RuntimeException(
                    'P14 retornó una fila preparada inválida '
                    .'durante el lookup por lote.'
                );
            }

            $projected[
                $canonicalIdentityHash
            ] =
                $this->projectPreparedRow(
                    $domain,
                    $preparedRow
                );
        }

        return $projected;
    }

}
