<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiNormalizedRow;
use App\Models\DataTransformationBiProcessingRun;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final class DataTransformationBiPostNormalizationIntegrityGate
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly DataTransformationBiCanonicalIdentity
            $canonicalIdentity
    ) {
    }

    /**
     * Assert that one fully-normalized run is internally usable.
     *
     * This method performs no writes. It is intended to execute inside
     * the normalization transaction before that transaction is committed.
     */
    public function assertRun(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch,
        int $expectedNormalizedCount
    ): void {
        $this->assertTraceability(
            $run,
            $batch,
            $expectedNormalizedCount
        );

        $physicalCount =
            $this->rowsQuery(
                $run,
                $batch
            )
                ->count();

        if (
            $physicalCount
            !== $expectedNormalizedCount
        ) {
            throw new RuntimeException(
                'El gate de integridad detectó un conteo físico '
                .'normalizado inconsistente.'
            );
        }

        $this->assertCanonicalIdentities(
            $run,
            $batch
        );

        $this->assertCanonicalRelationships(
            $run,
            $batch
        );
    }

    private function assertTraceability(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch,
        int $expectedNormalizedCount
    ): void {
        if (
            ! $run->exists
            || $run->getKey() === null
            || ! $batch->exists
            || $batch->getKey() === null
        ) {
            throw new RuntimeException(
                'El gate de integridad requiere un run y batch persistidos.'
            );
        }

        if ($expectedNormalizedCount < 0) {
            throw new RuntimeException(
                'El conteo normalizado esperado no puede ser negativo.'
            );
        }

        if (
            (int) $run
                ->data_transformation_bi_intake_batch_id
                !== (int) $batch->getKey()
            || (int) $run->company_id
                !== (int) $batch->company_id
            || (int) $run
                ->transformation_implementation_request_id
                !== (int) $batch
                    ->transformation_implementation_request_id
        ) {
            throw new RuntimeException(
                'El gate de integridad detectó pérdida de trazabilidad '
                .'entre el processing run y el intake batch.'
            );
        }

        if (
            $expectedNormalizedCount
            !== (int) $batch->staged_row_count
        ) {
            throw new RuntimeException(
                'El dataset normalizado no cubre todas las filas staged.'
            );
        }
    }

    private function assertCanonicalIdentities(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch
    ): void {
        $duplicates =
            $this->rowsQuery(
                $run,
                $batch
            )
                ->whereNotNull(
                    'canonical_identity_hash'
                )
                ->select([
                    'domain_key',
                    'canonical_identity_hash',
                ])
                ->groupBy([
                    'domain_key',
                    'canonical_identity_hash',
                ])
                ->havingRaw(
                    'COUNT(*) > 1'
                )
                ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'El dataset normalizado contiene identidades '
                .'canónicas duplicadas.'
            );
        }

        $this->rowsQuery(
            $run,
            $batch
        )
            ->select([
                'id',
                'domain_key',
                'canonical_identity_hash',
                'normalized_payload',
            ])
            ->orderBy('id')
            ->chunkById(
                self::CHUNK_SIZE,
                function ($rows): void {
                    foreach ($rows as $row) {
                        $domain =
                            (string) $row->domain_key;

                        $storedHash =
                            strtolower(
                                trim(
                                    (string) $row
                                        ->canonical_identity_hash
                                )
                            );

                        if (
                            preg_match(
                                '/^[a-f0-9]{64}$/',
                                $storedHash
                            )
                            !== 1
                        ) {
                            throw new RuntimeException(
                                'El dataset normalizado contiene '
                                .'una identidad canónica ausente '
                                .'o inválida.'
                            );
                        }

                        $payload =
                            $row->normalized_payload;

                        if (! is_array($payload)) {
                            throw new RuntimeException(
                                'El dataset normalizado contiene '
                                .'un payload canónico inválido.'
                            );
                        }

                        $expectedHash =
                            $this->canonicalIdentity
                                ->hashForNormalizedPayload(
                                    $domain,
                                    $payload
                                );

                        if (
                            ! hash_equals(
                                $expectedHash,
                                $storedHash
                            )
                        ) {
                            throw new RuntimeException(
                                'Una identidad canónica persistida '
                                .'no coincide con su payload normalizado.'
                            );
                        }
                    }
                },
                'id'
            );
    }

    private function assertCanonicalRelationships(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch
    ): void {
        foreach (
            DataTransformationBiStandardIntakeSchema
                ::relationships()
            as $relationship
        ) {
            $fromDomain =
                (string) $relationship[
                    'from_domain'
                ];

            $fromField =
                (string) $relationship[
                    'from_field'
                ];

            $toDomain =
                (string) $relationship[
                    'to_domain'
                ];

            $toField =
                (string) $relationship[
                    'to_field'
                ];

            $targetIdentity =
                DataTransformationBiStandardIntakeSchema
                    ::identityKeys()[$toDomain]
                ?? [];

            if (
                $targetIdentity
                !== [
                    $toField,
                ]
            ) {
                throw new RuntimeException(
                    "La relación {$fromDomain}.{$fromField} "
                    ."no apunta a una identidad canónica simple "
                    ."soportada en {$toDomain}.{$toField}."
                );
            }

            $this->rowsQuery(
                $run,
                $batch
            )
                ->where(
                    'domain_key',
                    $fromDomain
                )
                ->select([
                    'id',
                    'normalized_payload',
                ])
                ->orderBy('id')
                ->chunkById(
                    self::CHUNK_SIZE,
                    function ($rows) use (
                        $run,
                        $batch,
                        $fromDomain,
                        $fromField,
                        $toDomain,
                        $toField
                    ): void {
                        $requiredHashes = [];

                        foreach ($rows as $row) {
                            $payload =
                                $row->normalized_payload;

                            if (! is_array($payload)) {
                                throw new RuntimeException(
                                    'El dataset normalizado contiene '
                                    .'un payload canónico inválido.'
                                );
                            }

                            $value =
                                $payload[$fromField]
                                ?? null;

                            if ($this->blank($value)) {
                                continue;
                            }

                            $hash =
                                $this->canonicalIdentity
                                    ->hashForIdentityValues(
                                        $toDomain,
                                        [
                                            $toField =>
                                                $value,
                                        ]
                                    );

                            $requiredHashes[$hash] =
                                true;
                        }

                        if ($requiredHashes === []) {
                            return;
                        }

                        $foundHashes =
                            $this->rowsQuery(
                                $run,
                                $batch
                            )
                                ->where(
                                    'domain_key',
                                    $toDomain
                                )
                                ->whereIn(
                                    'canonical_identity_hash',
                                    array_keys(
                                        $requiredHashes
                                    )
                                )
                                ->pluck(
                                    'canonical_identity_hash'
                                )
                                ->map(
                                    static fn (
                                        mixed $hash
                                    ): string =>
                                        strtolower(
                                            trim(
                                                (string) $hash
                                            )
                                        )
                                )
                                ->all();

                        foreach ($foundHashes as $hash) {
                            unset(
                                $requiredHashes[$hash]
                            );
                        }

                        if ($requiredHashes !== []) {
                            throw new RuntimeException(
                                "El dataset normalizado contiene "
                                ."relaciones canónicas no resolubles: "
                                ."{$fromDomain}.{$fromField} -> "
                                ."{$toDomain}.{$toField}."
                            );
                        }
                    },
                    'id'
                );
        }
    }

    private function rowsQuery(
        DataTransformationBiProcessingRun $run,
        DataTransformationBiIntakeBatch $batch
    ): Builder {
        return DataTransformationBiNormalizedRow::query()
            ->where(
                'company_id',
                (int) $run->company_id
            )
            ->where(
                'data_transformation_bi_processing_run_id',
                (int) $run->getKey()
            )
            ->where(
                'data_transformation_bi_intake_batch_id',
                (int) $batch->getKey()
            );
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
}
