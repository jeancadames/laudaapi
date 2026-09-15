<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiNormalizedRow;
use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalRowSignatureReader
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 5000;

    /**
     * Stream canonical row signatures from one already-resolved dataset.
     *
     * canonical_identity_hash identifies the logical canonical row.
     * normalized_sha256 identifies its normalized canonical content.
     *
     * normalized_payload is deliberately not read.
     *
     * Ordering by canonical identity allows two historical snapshots to be
     * compared later using a bounded streaming merge.
     *
     * @param array<string,mixed> $dataset
     * @return \Generator<int,array{
     *     normalized_row_id:int,
     *     canonical_identity_hash:string,
     *     normalized_sha256:string
     * }>
     */
    public function iterateDomainSignaturesInDataset(
        int $companyId,
        array $dataset,
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

        [$runId, $batchId] =
            $this->datasetIdentity(
                $dataset
            );

        $afterCanonicalHash =
            null;

        while (true) {
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

            if (
                $afterCanonicalHash
                !== null
            ) {
                $query->where(
                    'canonical_identity_hash',
                    '>',
                    $afterCanonicalHash
                );
            }

            $records =
                $query
                    ->orderBy(
                        'canonical_identity_hash'
                    )
                    ->limit(
                        $pageSize + 1
                    )
                    ->get([
                        'id',
                        'canonical_identity_hash',
                        'normalized_sha256',
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

            $lastCanonicalHash =
                null;

            foreach ($records as $row) {
                $canonicalIdentityHash =
                    strtolower(
                        trim(
                            (string) $row
                                ->canonical_identity_hash
                        )
                    );

                $normalizedSha256 =
                    strtolower(
                        trim(
                            (string) $row
                                ->normalized_sha256
                        )
                    );

                if (
                    preg_match(
                        '/^[a-f0-9]{64}$/',
                        $canonicalIdentityHash
                    )
                    !== 1
                ) {
                    throw new RuntimeException(
                        'Una fila del dataset preparado no contiene '
                        .'identidad canónica SHA-256 válida.'
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
                        'Una fila del dataset preparado no contiene '
                        .'firma normalizada SHA-256 válida.'
                    );
                }

                if (
                    $lastCanonicalHash !== null
                    && strcmp(
                        $canonicalIdentityHash,
                        $lastCanonicalHash
                    )
                    <= 0
                ) {
                    throw new RuntimeException(
                        'Las firmas canónicas del dataset no avanzaron '
                        .'en orden estricto.'
                    );
                }

                $lastCanonicalHash =
                    $canonicalIdentityHash;

                yield [
                    'normalized_row_id' =>
                        (int) $row->id,

                    'canonical_identity_hash' =>
                        $canonicalIdentityHash,

                    'normalized_sha256' =>
                        $normalizedSha256,
                ];
            }

            if (! $hasMore) {
                break;
            }

            if (
                $lastCanonicalHash === null
                || (
                    $afterCanonicalHash !== null
                    && strcmp(
                        $lastCanonicalHash,
                        $afterCanonicalHash
                    )
                    <= 0
                )
            ) {
                throw new RuntimeException(
                    'La paginación de firmas canónicas '
                    .'no avanzó de forma válida.'
                );
            }

            $afterCanonicalHash =
                $lastCanonicalHash;
        }
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
                'El dataset versionado no contiene '
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
        int $pageSize
    ): int {
        return $pageSize > 0
            ? min(
                $pageSize,
                self::MAX_PAGE_SIZE
            )
            : self::DEFAULT_PAGE_SIZE;
    }
}
