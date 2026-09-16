<?php

namespace App\Services\Diagnosis;

use InvalidArgumentException;
use RuntimeException;

final class DataTransformationBiCanonicalConsumptionPlan
{
    private const DEFAULT_PAGE_SIZE = 500;

    private const MAX_PAGE_SIZE = 500;

    private const MODE_SNAPSHOT = 'snapshot';

    private const MODE_INCREMENTAL = 'incremental';

    private const MODE_UP_TO_DATE = 'up_to_date';

    public function __construct(
        private readonly DataTransformationBiCanonicalDatasetManifestResolver $manifestResolver
    ) {
    }

    /**
     * Build one stateless consumption decision.
     *
     * The caller owns its baseline. P24 persists nothing.
     *
     * @return array{
     *     available:bool,
     *     mode:string|null,
     *     reason:string,
     *     target:array<string,mixed>|null,
     *     base:array<string,mixed>|null,
     *     compatibility:array<string,mixed>|null,
     *     same_content:bool|null
     * }
     */
    public function plan(
        int $companyId,
        int $implementationRequestId,
        ?int $baseProcessingRunId = null,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        if ($companyId <= 0) {
            throw new InvalidArgumentException(
                'El plan canónico requiere una empresa válida.'
            );
        }

        if ($implementationRequestId <= 0) {
            throw new InvalidArgumentException(
                'El plan canónico requiere una solicitud válida.'
            );
        }

        if (
            $baseProcessingRunId !== null
            && $baseProcessingRunId <= 0
        ) {
            throw new InvalidArgumentException(
                'El baseline canónico debe ser un processing run positivo.'
            );
        }

        $pageSize =
            $this->pageSize(
                $pageSize
            );

        /*
         * P19 is the sole authority for choosing the current usable target.
         */
        $current =
            $this->manifestResolver
                ->current(
                    $companyId,
                    $implementationRequestId,
                    $pageSize
                );

        if (
            ($current['available'] ?? false)
            !== true
        ) {
            return $this->unavailablePlan(
                $this->reason(
                    $current['reason']
                    ?? 'no_successful_normalized_dataset'
                )
            );
        }

        $target =
            $this->canonicalSide(
                [
                    'context' =>
                        $current['context']
                        ?? null,

                    'manifest' =>
                        $current['manifest']
                        ?? null,
                ],
                $companyId,
                $implementationRequestId,
                'target'
            );

        $targetIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $target['context']
                );

        $targetProcessingRunId =
            $targetIdentity[
                'processing_run_id'
            ];

        /*
         * No known consumer baseline means a full bootstrap snapshot.
         */
        if ($baseProcessingRunId === null) {
            return $this->availablePlan(
                self::MODE_SNAPSHOT,
                'baseline_not_provided',
                $target,
                null,
                null,
                null
            );
        }

        /*
         * Exact same run requires no row delivery.
         */
        if (
            $baseProcessingRunId
            === $targetProcessingRunId
        ) {
            return $this->availablePlan(
                self::MODE_UP_TO_DATE,
                'baseline_is_current_target',
                $target,
                $target,
                null,
                true
            );
        }

        /*
         * A consumer cannot legitimately be ahead of the platform's
         * currently selected usable canonical target.
         */
        if (
            $baseProcessingRunId
            > $targetProcessingRunId
        ) {
            throw new RuntimeException(
                'El baseline del consumidor es posterior '
                .'al target canónico actual.'
            );
        }

        /*
         * P19 remains the only compatibility authority.
         * If it cannot form a compatible pair, we safely bootstrap again.
         */
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
            return $this->availablePlan(
                self::MODE_SNAPSHOT,
                $this->reason(
                    $pair['reason']
                    ?? 'dataset_pair_not_usable'
                ),
                $target,
                null,
                null,
                null
            );
        }

        $base =
            $this->canonicalSide(
                $pair['base']
                ?? null,
                $companyId,
                $implementationRequestId,
                'base'
            );

        $pairTarget =
            $this->canonicalSide(
                $pair['target']
                ?? null,
                $companyId,
                $implementationRequestId,
                'target'
            );

        $baseIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $base['context']
                );

        $pairTargetIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $pairTarget['context']
                );

        if (
            $baseIdentity['processing_run_id']
            !== $baseProcessingRunId
        ) {
            throw new RuntimeException(
                'El par P19 no conserva el baseline solicitado.'
            );
        }

        if (
            $pairTargetIdentity['processing_run_id']
            !== $targetProcessingRunId
        ) {
            throw new RuntimeException(
                'El par P19 no conserva el target actual.'
            );
        }

        $this->assertSameTarget(
            $target,
            $pairTarget
        );

        $compatibility =
            $pair['compatibility']
            ?? null;

        if (! is_array($compatibility)) {
            throw new RuntimeException(
                'El par canónico disponible requiere compatibilidad válida.'
            );
        }

        $sameContent =
            $pair['same_content']
            ?? null;

        if (! is_bool($sameContent)) {
            throw new RuntimeException(
                'El par canónico disponible requiere same_content booleano.'
            );
        }

        /*
         * Different runs with identical canonical fingerprints require
         * no semantic row delivery either.
         */
        if ($sameContent) {
            return $this->availablePlan(
                self::MODE_UP_TO_DATE,
                'baseline_content_matches_current_target',
                $pairTarget,
                $base,
                $compatibility,
                true
            );
        }

        return $this->availablePlan(
            self::MODE_INCREMENTAL,
            $this->reason(
                $pair['reason']
                ?? 'compatible_versioned_dataset_pair'
            ),
            $pairTarget,
            $base,
            $compatibility,
            false
        );
    }

    /**
     * @return array{
     *     available:false,
     *     mode:null,
     *     reason:string,
     *     target:null,
     *     base:null,
     *     compatibility:null,
     *     same_content:null
     * }
     */
    private function unavailablePlan(
        string $reason
    ): array {
        return [
            'available' =>
                false,

            'mode' =>
                null,

            'reason' =>
                $this->reason(
                    $reason
                ),

            'target' =>
                null,

            'base' =>
                null,

            'compatibility' =>
                null,

            'same_content' =>
                null,
        ];
    }

    /**
     * @param array<string,mixed> $target
     * @param array<string,mixed>|null $base
     * @param array<string,mixed>|null $compatibility
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
    private function availablePlan(
        string $mode,
        string $reason,
        array $target,
        ?array $base,
        ?array $compatibility,
        ?bool $sameContent
    ): array {
        if (
            ! in_array(
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
                'El modo del plan canónico no es válido.'
            );
        }

        if ($mode === self::MODE_SNAPSHOT) {
            if (
                $base !== null
                || $compatibility !== null
                || $sameContent !== null
            ) {
                throw new RuntimeException(
                    'snapshot no debe transportar estado incremental.'
                );
            }
        }

        if ($mode === self::MODE_INCREMENTAL) {
            if (
                $base === null
                || $compatibility === null
                || $sameContent !== false
            ) {
                throw new RuntimeException(
                    'incremental requiere base, compatibilidad '
                    .'y same_content=false.'
                );
            }
        }

        if ($mode === self::MODE_UP_TO_DATE) {
            if (
                $base === null
                || $sameContent !== true
            ) {
                throw new RuntimeException(
                    'up_to_date requiere base y same_content=true.'
                );
            }
        }

        return [
            'available' =>
                true,

            'mode' =>
                $mode,

            'reason' =>
                $this->reason(
                    $reason
                ),

            'target' =>
                $target,

            'base' =>
                $base,

            'compatibility' =>
                $compatibility,

            'same_content' =>
                $sameContent,
        ];
    }

    /**
     * Validate a P19 context + manifest side without creating new metadata
     * or compatibility authority.
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
        int $companyId,
        int $implementationRequestId,
        string $label
    ): array {
        if (! is_array($side)) {
            throw new RuntimeException(
                "El lado {$label} del plan canónico no es válido."
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

        if (
            $identity['company_id']
            !== $companyId
            || $identity['implementation_request_id']
                !== $implementationRequestId
        ) {
            throw new RuntimeException(
                "El lado {$label} no pertenece al contexto solicitado."
            );
        }

        $dataset =
            DataTransformationBiCanonicalDatasetContext
                ::dataset(
                    $context
                );

        if (
            ($manifest['company_id'] ?? null)
            !== $companyId
            || (
                $manifest[
                    'implementation_request_id'
                ]
                ?? null
            )
            !== $implementationRequestId
        ) {
            throw new RuntimeException(
                "El manifest {$label} no pertenece al contexto solicitado."
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
                "El manifest {$label} no coincide con su contexto."
            );
        }

        $domains =
            $manifest['domains']
            ?? null;

        if (! is_array($domains)) {
            throw new RuntimeException(
                "El manifest {$label} no contiene dominios válidos."
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
                "El manifest {$label} no reconcilia su total de filas."
            );
        }

        $fingerprint =
            $this->manifestFingerprint(
                $manifest,
                $label
            );

        $expectedFingerprint =
            DataTransformationBiCanonicalDatasetManifest
                ::fingerprintDataset(
                    $dataset,
                    $domains
                );

        if (
            ! hash_equals(
                $expectedFingerprint,
                $fingerprint
            )
        ) {
            throw new RuntimeException(
                "El fingerprint {$label} no coincide con su manifest."
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
     * Ensure the current resolver and pair resolver refer to the exact same
     * target run and canonical dataset fingerprint.
     *
     * @param array<string,mixed> $currentTarget
     * @param array<string,mixed> $pairTarget
     */
    private function assertSameTarget(
        array $currentTarget,
        array $pairTarget
    ): void {
        $currentIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $currentTarget['context']
                );

        $pairIdentity =
            DataTransformationBiCanonicalDatasetContext
                ::identity(
                    $pairTarget['context']
                );

        if (
            $currentIdentity
            !== $pairIdentity
        ) {
            throw new RuntimeException(
                'El target del pair no coincide con el target actual.'
            );
        }

        $currentFingerprint =
            $this->manifestFingerprint(
                $currentTarget['manifest'],
                'current_target'
            );

        $pairFingerprint =
            $this->manifestFingerprint(
                $pairTarget['manifest'],
                'pair_target'
            );

        if (
            ! hash_equals(
                $currentFingerprint,
                $pairFingerprint
            )
        ) {
            throw new RuntimeException(
                'El target actual y el target del pair '
                .'no tienen el mismo fingerprint.'
            );
        }
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function manifestFingerprint(
        array $manifest,
        string $label
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
                "El manifest {$label} no contiene "
                .'dataset_fingerprint SHA-256 válido.'
            );
        }

        return $fingerprint;
    }

    private function reason(
        mixed $reason
    ): string {
        if (! is_string($reason)) {
            throw new RuntimeException(
                'El plan canónico requiere reason textual.'
            );
        }

        $reason =
            trim(
                $reason
            );

        if ($reason === '') {
            throw new RuntimeException(
                'El plan canónico requiere reason no vacío.'
            );
        }

        return $reason;
    }

    private function pageSize(
        int $pageSize
    ): int {
        if (
            $pageSize <= 0
            || $pageSize > self::MAX_PAGE_SIZE
        ) {
            throw new InvalidArgumentException(
                'El tamaño de página del plan canónico '
                .'debe estar entre 1 y 500.'
            );
        }

        return $pageSize;
    }
}
