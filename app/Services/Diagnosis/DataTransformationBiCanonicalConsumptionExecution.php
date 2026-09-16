<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalConsumptionExecution
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 500;

    private const MODE_SNAPSHOT = 'snapshot';

    private const MODE_INCREMENTAL = 'incremental';

    private const MODE_UP_TO_DATE = 'up_to_date';

    public function __construct(
        private readonly DataTransformationBiCanonicalDatasetConsumption $snapshotConsumption,
        private readonly DataTransformationBiCanonicalIncrementalConsumption $incrementalConsumption
    ) {
    }

    /**
     * Execute one already-resolved P24 plan.
     *
     * P25 performs no dataset resolution of its own.
     *
     * snapshot rows remain owned by P22:
     *   domain, identity, fields
     *
     * incremental rows remain owned by P23:
     *   domain, change_type, identity, before, after
     *
     * up_to_date yields no rows.
     *
     * @param array<string,mixed> $plan
     *
     * @return \Generator<int,array<string,mixed>>
     */
    public function iteratePlan(
        array $plan,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): \Generator {
        $pageSize =
            $this->pageSize(
                $pageSize
            );

        $plan =
            $this->validatedPlan(
                $plan
            );

        switch ($plan['mode']) {
            case self::MODE_SNAPSHOT:
                yield from $this->snapshotConsumption
                    ->iterateResolvedSnapshot(
                        $this->snapshotEnvelope(
                            $plan
                        ),
                        $pageSize
                    );

                return;

            case self::MODE_INCREMENTAL:
                yield from $this->incrementalConsumption
                    ->iterateResolvedPair(
                        $this->incrementalPair(
                            $plan
                        ),
                        $pageSize
                    );

                return;

            case self::MODE_UP_TO_DATE:
                /*
                 * The consumer baseline already represents the current
                 * canonical content. No dataset read is necessary.
                 */
                return;
        }

        throw new RuntimeException(
            'El plan canónico contiene un modo de ejecución desconocido.'
        );
    }

    /**
     * Validate only the execution contract supplied by P24.
     * P24 remains the decision authority.
     *
     * @param array<string,mixed> $plan
     *
     * @return array{
     *     available:true,
     *     mode:string,
     *     reason:string,
     *     target:array<string,mixed>,
     *     base:array<string,mixed>|null,
     *     compatibility:array<string,mixed>|null,
     *     same_content:bool|null
     * }
     */
    private function validatedPlan(
        array $plan
    ): array {
        $expectedKeys = [
            'available',
            'mode',
            'reason',
            'target',
            'base',
            'compatibility',
            'same_content',
        ];

        if (
            array_keys($plan)
            !== $expectedKeys
        ) {
            throw new RuntimeException(
                'El plan canónico no respeta el contrato P24.'
            );
        }

        if (
            ($plan['available'] ?? null)
            !== true
        ) {
            throw new RuntimeException(
                'No se puede ejecutar un plan canónico no disponible.'
            );
        }

        $mode =
            $plan['mode']
            ?? null;

        if (
            ! is_string($mode)
            || ! in_array(
                $mode,
                [
                    self::MODE_SNAPSHOT,
                    self::MODE_INCREMENTAL,
                    self::MODE_UP_TO_DATE,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'El plan canónico contiene un modo inválido.'
            );
        }

        $reason =
            $plan['reason']
            ?? null;

        if (
            ! is_string($reason)
            || trim($reason) === ''
        ) {
            throw new RuntimeException(
                'El plan canónico requiere reason no vacío.'
            );
        }

        $target =
            $this->canonicalSide(
                $plan['target']
                ?? null,
                'target'
            );

        $base =
            $plan['base']
            ?? null;

        $compatibility =
            $plan['compatibility']
            ?? null;

        $sameContent =
            $plan['same_content']
            ?? null;

        if ($mode === self::MODE_SNAPSHOT) {
            if (
                $base !== null
                || $compatibility !== null
                || $sameContent !== null
            ) {
                throw new RuntimeException(
                    'snapshot no debe contener estado incremental.'
                );
            }

            return [
                'available' =>
                    true,

                'mode' =>
                    $mode,

                'reason' =>
                    trim($reason),

                'target' =>
                    $target,

                'base' =>
                    null,

                'compatibility' =>
                    null,

                'same_content' =>
                    null,
            ];
        }

        if (! is_array($base)) {
            throw new RuntimeException(
                "{$mode} requiere un baseline canónico."
            );
        }

        $base =
            $this->canonicalSide(
                $base,
                'base'
            );

        $targetIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $target['context']
                );

        $baseIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $base['context']
                );

        if (
            $targetIdentity['company_id']
            !== $baseIdentity['company_id']
            || $targetIdentity['implementation_request_id']
                !== $baseIdentity['implementation_request_id']
        ) {
            throw new RuntimeException(
                'Base y target no pertenecen al mismo contexto empresarial.'
            );
        }

        if ($mode === self::MODE_INCREMENTAL) {
            if (
                ! is_array($compatibility)
                || $sameContent !== false
            ) {
                throw new RuntimeException(
                    'incremental requiere compatibilidad '
                    .'y same_content=false.'
                );
            }

            if (
                $baseIdentity['processing_run_id']
                >= $targetIdentity['processing_run_id']
            ) {
                throw new RuntimeException(
                    'incremental requiere dirección base → target válida.'
                );
            }

            return [
                'available' =>
                    true,

                'mode' =>
                    $mode,

                'reason' =>
                    trim($reason),

                'target' =>
                    $target,

                'base' =>
                    $base,

                'compatibility' =>
                    $compatibility,

                'same_content' =>
                    false,
            ];
        }

        if ($sameContent !== true) {
            throw new RuntimeException(
                'up_to_date requiere same_content=true.'
            );
        }

        if (
            $compatibility !== null
            && ! is_array($compatibility)
        ) {
            throw new RuntimeException(
                'up_to_date contiene compatibilidad inválida.'
            );
        }

        if (
            $baseIdentity['processing_run_id']
            > $targetIdentity['processing_run_id']
        ) {
            throw new RuntimeException(
                'up_to_date contiene un baseline posterior al target.'
            );
        }

        return [
            'available' =>
                true,

            'mode' =>
                $mode,

            'reason' =>
                trim($reason),

            'target' =>
                $target,

            'base' =>
                $base,

            'compatibility' =>
                $compatibility,

            'same_content' =>
                true,
        ];
    }

    /**
     * Structural validation for one pinned P24 side.
     * Downstream P22/P23 retain their deeper execution validation.
     *
     * @param mixed $side
     *
     * @return array{
     *     context:array<string,mixed>,
     *     manifest:array<string,mixed>
     * }
     */
    private function canonicalSide(
        mixed $side,
        string $label
    ): array {
        if (! is_array($side)) {
            throw new RuntimeException(
                "El lado {$label} del plan no es válido."
            );
        }

        if (
            array_keys($side)
            !== [
                'context',
                'manifest',
            ]
        ) {
            throw new RuntimeException(
                "El lado {$label} no respeta el contrato P24."
            );
        }

        $context =
            $side['context']
            ?? null;

        $manifest =
            $side['manifest']
            ?? null;

        if (
            ! is_array($context)
            || ! is_array($manifest)
        ) {
            throw new RuntimeException(
                "El lado {$label} requiere context y manifest."
            );
        }

        $identity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $context
                );

        $dataset =
            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $context
                );

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
                "El manifest {$label} no pertenece a su contexto."
            );
        }

        $manifestDataset =
            $manifest['dataset']
            ?? null;

        if (! is_array($manifestDataset)) {
            throw new RuntimeException(
                "El manifest {$label} no contiene dataset válido."
            );
        }

        $manifestDataset =
            DataTransformationBiCanonicalDatasetContext
                ::descriptor(
                    $manifestDataset
                );

        if ($manifestDataset !== $dataset) {
            throw new RuntimeException(
                "El manifest {$label} no coincide con su dataset fijado."
            );
        }

        $totalRows =
            $manifest['total_rows']
            ?? null;

        if (
            ! is_int($totalRows)
            || $totalRows < 0
            || $totalRows
                !== $dataset[
                    'normalized_row_count'
                ]
        ) {
            throw new RuntimeException(
                "El manifest {$label} no reconcilia total_rows."
            );
        }

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
                "El manifest {$label} no contiene fingerprint válido."
            );
        }

        return [
            'context' =>
                $context,

            'manifest' =>
                $manifest,
        ];
    }

    /**
     * Adapt snapshot mode into the already-resolved P22 envelope.
     *
     * @param array<string,mixed> $plan
     *
     * @return array{
     *     available:true,
     *     reason:string,
     *     context:array<string,mixed>,
     *     manifest:array<string,mixed>
     * }
     */
    private function snapshotEnvelope(
        array $plan
    ): array {
        if (
            ($plan['mode'] ?? null)
            !== self::MODE_SNAPSHOT
        ) {
            throw new RuntimeException(
                'Solo snapshot puede adaptarse al contrato P22.'
            );
        }

        /** @var array<string,mixed> $target */
        $target =
            $plan['target'];

        return [
            'available' =>
                true,

            'reason' =>
                $plan['reason'],

            'context' =>
                $target['context'],

            'manifest' =>
                $target['manifest'],
        ];
    }

    /**
     * Adapt incremental mode into the already-resolved P23/P20 pair.
     *
     * @param array<string,mixed> $plan
     *
     * @return array{
     *     available:true,
     *     reason:string,
     *     base:array<string,mixed>,
     *     target:array<string,mixed>,
     *     compatibility:array<string,mixed>,
     *     same_content:false
     * }
     */
    private function incrementalPair(
        array $plan
    ): array {
        if (
            ($plan['mode'] ?? null)
            !== self::MODE_INCREMENTAL
            || ! is_array(
                $plan['base']
                ?? null
            )
            || ! is_array(
                $plan['compatibility']
                ?? null
            )
            || ($plan['same_content'] ?? null)
                !== false
        ) {
            throw new RuntimeException(
                'El plan no puede adaptarse al contrato incremental P23.'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                $plan['reason'],

            'base' =>
                $plan['base'],

            'target' =>
                $plan['target'],

            'compatibility' =>
                $plan['compatibility'],

            'same_content' =>
                false,
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
                'El tamaño de ejecución canónica '
                .'debe estar entre 1 y 500.'
            );
        }

        return $pageSize;
    }
}
