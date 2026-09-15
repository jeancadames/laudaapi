<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiNormalizedRow;
use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiPreparedDatasetReader
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 5000;

    public function __construct(
        private readonly DataTransformationBiUsableDatasetResolver
            $usableDatasetResolver
    ) {
    }

    /**
     * @return array<int,string>
     */
    public function supportedDomains(): array
    {
        return array_keys(
            DataTransformationBiStandardIntakeSchema::domains()
        );
    }

    /**
     * Read one bounded page from the current usable dataset.
     *
     * @return array<string,mixed>
     */
    public function readDomainPage(
        int $companyId,
        int $implementationRequestId,
        string $domain,
        int $limit = self::DEFAULT_PAGE_SIZE,
        ?int $afterId = null
    ): array {
        $domain =
            $this->canonicalDomain(
                $domain
            );

        $pageSize =
            $this->pageSize(
                $limit
            );

        $afterId =
            $this->afterId(
                $afterId
            );

        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        if (! $this->hasUsableDataset($resolved)) {
            return $this->emptyResult(
                $resolved,
                $domain,
                $pageSize
            );
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        return $this->readResolvedDomainPage(
            $companyId,
            $dataset,
            $domain,
            $pageSize,
            $afterId,
            (string) (
                $resolved['reason']
                ?? 'latest_successful_normalized_run'
            )
        );
    }

    /**
     * Aggregate row counts for every canonical domain in the current
     * usable dataset.
     *
     * Every supported domain is returned, including domains with zero rows.
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     dataset:array<string,mixed>|null,
     *     total_rows:int,
     *     domains:array<string,int>
     * }
     */
    public function domainCounts(
        int $companyId,
        int $implementationRequestId
    ): array {
        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        $counts =
            array_fill_keys(
                $this->supportedDomains(),
                0
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

                'total_rows' =>
                    0,

                'domains' =>
                    $counts,
            ];
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        [$runId, $batchId] =
            $this->datasetIdentity(
                $dataset
            );

        $persisted =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $runId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->selectRaw(
                    'domain_key, COUNT(*) AS aggregate_count'
                )
                ->groupBy(
                    'domain_key'
                )
                ->pluck(
                    'aggregate_count',
                    'domain_key'
                );

        foreach ($persisted as $domain => $count) {
            if (! array_key_exists(
                (string) $domain,
                $counts
            )) {
                throw new RuntimeException(
                    'El dataset preparado contiene '
                    .'un dominio fuera del contrato canónico.'
                );
            }

            $counts[(string) $domain] =
                (int) $count;
        }

        $totalRows =
            array_sum(
                $counts
            );

        $expectedRows =
            max(
                0,
                (int) (
                    $dataset['normalized_row_count']
                    ?? 0
                )
            );

        if ($totalRows !== $expectedRows) {
            throw new RuntimeException(
                'Los conteos por dominio no coinciden '
                .'con el dataset utilizable.'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                (string) (
                    $resolved['reason']
                    ?? 'latest_successful_normalized_run'
                ),

            'dataset' =>
                $dataset,

            'total_rows' =>
                $totalRows,

            'domains' =>
                $counts,
        ];
    }

    /**
     * Iterate one canonical domain without loading it completely in memory.
     *
     * The usable dataset identity is resolved ONCE at iteration start.
     * All subsequent pages remain pinned to that exact processing run and
     * intake batch even if a newer usable dataset becomes available later.
     *
     * @return \Generator<int,array{
     *     normalized_row_id:int,
     *     identity_hash:string,
     *     payload:array<string,mixed>
     * }>
     */
    public function iterateDomain(
        int $companyId,
        int $implementationRequestId,
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
         * Important: resolve exactly once.
         *
         * Calling readDomainPage() repeatedly here would re-resolve P13 and
         * could switch datasets between pages.
         */
        $resolved =
            $this->usableDatasetResolver->forRequest(
                $companyId,
                $implementationRequestId
            );

        if (! $this->hasUsableDataset($resolved)) {
            return;
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        /*
         * Validate and freeze the technical identity before yielding rows.
         */
        $this->datasetIdentity(
            $dataset
        );

        $reason =
            (string) (
                $resolved['reason']
                ?? 'latest_successful_normalized_run'
            );

        $afterId =
            null;

        while (true) {
            $page =
                $this->readResolvedDomainPage(
                    $companyId,
                    $dataset,
                    $domain,
                    $pageSize,
                    $afterId,
                    $reason
                );

            foreach ($page['rows'] as $row) {
                yield $row;
            }

            if (
                ($page['page']['has_more'] ?? false)
                !== true
            ) {
                break;
            }

            $nextAfterId =
                $page['page']['next_after_id']
                ?? null;

            if (
                ! is_int($nextAfterId)
                || $nextAfterId <= 0
                || (
                    $afterId !== null
                    && $nextAfterId <= $afterId
                )
            ) {
                throw new RuntimeException(
                    'La paginación del dataset preparado '
                    .'no avanzó de forma válida.'
                );
            }

            $afterId =
                $nextAfterId;
        }
    }

    /**
     * Resolve one canonical row by its NORMALIZED identity inside an
     * already-resolved P13 dataset snapshot.
     *
     * This is intentionally snapshot-based so relationship navigation
     * cannot switch to a newer dataset between source and target lookup.
     *
     * @param array<string,mixed> $dataset
     * @return array{
     *     normalized_row_id:int,
     *     identity_hash:string,
     *     payload:array<string,mixed>
     * }|null
     */
    public function findDomainRowByCanonicalIdentityHashInDataset(
        int $companyId,
        array $dataset,
        string $domain,
        string $canonicalIdentityHash
    ): ?array {
        $domain =
            $this->canonicalDomain(
                $domain
            );

        $canonicalIdentityHash =
            strtolower(
                trim(
                    $canonicalIdentityHash
                )
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/',
                $canonicalIdentityHash
            )
            !== 1
        ) {
            throw new InvalidArgumentException(
                'La identidad canónica debe ser un SHA-256 válido.'
            );
        }

        [$runId, $batchId] =
            $this->datasetIdentity(
                $dataset
            );

        $row =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $runId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->where(
                    'domain_key',
                    $domain
                )
                ->where(
                    'canonical_identity_hash',
                    $canonicalIdentityHash
                )
                ->first([
                    'id',
                    'identity_hash',
                    'normalized_payload',
                ]);

        return $row
            ? $this->canonicalRow(
                $row
            )
            : null;
    }

    /**
     * Read a page pinned to an already resolved dataset identity.
     *
     * This method MUST NOT re-resolve the P13 pointer.
     *
     * @param array<string,mixed> $dataset
     * @return array<string,mixed>
     */
    private function readResolvedDomainPage(
        int $companyId,
        array $dataset,
        string $domain,
        int $pageSize,
        ?int $afterId,
        string $reason
    ): array {
        [$runId, $batchId] =
            $this->datasetIdentity(
                $dataset
            );

        $query =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $runId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->where(
                    'domain_key',
                    $domain
                );

        if ($afterId !== null) {
            $query->where(
                'id',
                '>',
                $afterId
            );
        }

        $records =
            $query
                ->orderBy('id')
                ->limit(
                    $pageSize + 1
                )
                ->get([
                    'id',
                    'identity_hash',
                    'normalized_payload',
                ]);

        $hasMore =
            $records->count()
            > $pageSize;

        if ($hasMore) {
            $records =
                $records->take(
                    $pageSize
                );
        }

        $rows =
            $records
                ->map(
                    fn (
                        DataTransformationBiNormalizedRow $row
                    ): array =>
                        $this->canonicalRow(
                            $row
                        )
                )
                ->values()
                ->all();

        $nextAfterId =
            $hasMore
            && $rows !== []
                ? (int) $rows[
                    array_key_last($rows)
                ]['normalized_row_id']
                : null;

        return [
            'available' =>
                true,

            'reason' =>
                $reason,

            'dataset' =>
                $dataset,

            'domain' =>
                $domain,

            'rows' =>
                $rows,

            'page' => [
                'limit' =>
                    $pageSize,

                'returned' =>
                    count($rows),

                'has_more' =>
                    $hasMore,

                'next_after_id' =>
                    $nextAfterId,
            ],
        ];
    }

    /**
     * @return array{
     *     normalized_row_id:int,
     *     identity_hash:string,
     *     payload:array<string,mixed>
     * }
     */
    private function canonicalRow(
        DataTransformationBiNormalizedRow $row
    ): array {
        $payload =
            $row->normalized_payload;

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Una fila del dataset preparado '
                .'no contiene payload canónico válido.'
            );
        }

        $identityHash =
            trim(
                (string) $row->identity_hash
            );

        if ($identityHash === '') {
            throw new RuntimeException(
                'Una fila del dataset preparado '
                .'no contiene identidad canónica válida.'
            );
        }

        return [
            'normalized_row_id' =>
                (int) $row->id,

            'identity_hash' =>
                $identityHash,

            'payload' =>
                $payload,
        ];
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
                $resolved['dataset'] ?? null
            )
        );
    }

    /**
     * @param array<string,mixed> $dataset
     * @return array{0:int,1:int}
     */
    private function datasetIdentity(
        array $dataset
    ): array {
        $runId =
            (int) (
                $dataset['processing_run_id']
                ?? 0
            );

        $batchId =
            (int) (
                $dataset['intake_batch_id']
                ?? 0
            );

        if (
            $runId <= 0
            || $batchId <= 0
        ) {
            throw new RuntimeException(
                'El dataset utilizable no contiene '
                .'una identidad técnica válida.'
            );
        }

        return [
            $runId,
            $batchId,
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
        int $limit
    ): int {
        return $limit > 0
            ? min(
                $limit,
                self::MAX_PAGE_SIZE
            )
            : self::DEFAULT_PAGE_SIZE;
    }

    private function afterId(
        ?int $afterId
    ): ?int {
        return $afterId !== null
        && $afterId > 0
            ? $afterId
            : null;
    }

    /**
     * @param array<string,mixed> $resolved
     * @return array<string,mixed>
     */
    private function emptyResult(
        array $resolved,
        string $domain,
        int $pageSize
    ): array {
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

            'domain' =>
                $domain,

            'rows' =>
                [],

            'page' => [
                'limit' =>
                    $pageSize,

                'returned' =>
                    0,

                'has_more' =>
                    false,

                'next_after_id' =>
                    null,
            ],
        ];
    }
}
