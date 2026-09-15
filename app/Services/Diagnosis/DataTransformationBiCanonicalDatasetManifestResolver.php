<?php

namespace App\Services\Diagnosis;

use RuntimeException;

final class DataTransformationBiCanonicalDatasetManifestResolver
{
    private const DEFAULT_PAGE_SIZE = 500;

    public function __construct(
        private readonly DataTransformationBiUsableDatasetResolver $usableDatasetResolver,
        private readonly DataTransformationBiVersionedDatasetResolver $versionedDatasetResolver,
        private readonly DataTransformationBiCanonicalDatasetManifest $manifestBuilder
    ) {
    }

    /**
     * Resolve the current usable canonical dataset exactly once through P13,
     * freeze it into the shared context and build its manifest without any
     * additional current/latest dataset resolution.
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     context:array<string,mixed>|null,
     *     manifest:array<string,mixed>|null
     * }
     */
    public function current(
        int $companyId,
        int $implementationRequestId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $resolved =
            $this->usableDatasetResolver
                ->forRequest(
                    $companyId,
                    $implementationRequestId
                );

        if (! $this->availableDataset($resolved)) {
            return $this->unavailable(
                (string) (
                    $resolved['reason']
                    ?? 'no_successful_normalized_dataset'
                )
            );
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        $context =
            DataTransformationBiCanonicalDatasetContext
                ::fromResolved(
                    $companyId,
                    $implementationRequestId,
                    $dataset
                );

        return [
            'available' =>
                true,

            'reason' =>
                (string) (
                    $resolved['reason']
                    ?? 'latest_successful_normalized_run'
                ),

            'context' =>
                $context,

            'manifest' =>
                $this->manifestBuilder
                    ->build(
                        $context,
                        $pageSize
                    ),
        ];
    }

    /**
     * Resolve one explicit historical processing run exactly once through
     * P18-A, freeze it into the same shared context and build its manifest.
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     context:array<string,mixed>|null,
     *     manifest:array<string,mixed>|null
     * }
     */
    public function forProcessingRun(
        int $companyId,
        int $implementationRequestId,
        int $processingRunId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $resolved =
            $this->versionedDatasetResolver
                ->forProcessingRun(
                    $companyId,
                    $implementationRequestId,
                    $processingRunId
                );

        if (! $this->availableDataset($resolved)) {
            return $this->unavailable(
                (string) (
                    $resolved['reason']
                    ?? 'processing_run_not_usable'
                )
            );
        }

        /** @var array<string,mixed> $dataset */
        $dataset =
            $resolved['dataset'];

        $context =
            DataTransformationBiCanonicalDatasetContext
                ::fromResolved(
                    $companyId,
                    $implementationRequestId,
                    $dataset
                );

        return [
            'available' =>
                true,

            'reason' =>
                (string) (
                    $resolved['reason']
                    ?? 'explicit_usable_processing_run'
                ),

            'context' =>
                $context,

            'manifest' =>
                $this->manifestBuilder
                    ->build(
                        $context,
                        $pageSize
                    ),
        ];
    }

    /**
     * Resolve one directional historical pair exactly once through P18-A.
     *
     * Both manifests are built from the descriptors returned by that single
     * pair resolution. Neither side is independently re-resolved.
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     base:array<string,mixed>|null,
     *     target:array<string,mixed>|null,
     *     compatibility:array<string,mixed>|null,
     *     same_content:bool|null
     * }
     */
    public function pair(
        int $companyId,
        int $implementationRequestId,
        int $baseProcessingRunId,
        int $targetProcessingRunId,
        int $pageSize = self::DEFAULT_PAGE_SIZE
    ): array {
        $resolved =
            $this->versionedDatasetResolver
                ->pair(
                    $companyId,
                    $implementationRequestId,
                    $baseProcessingRunId,
                    $targetProcessingRunId
                );

        if (
            ($resolved['available'] ?? false)
            !== true
        ) {
            return [
                'available' =>
                    false,

                'reason' =>
                    (string) (
                        $resolved['reason']
                        ?? 'dataset_pair_not_usable'
                    ),

                'base' =>
                    null,

                'target' =>
                    null,

                'compatibility' =>
                    null,

                'same_content' =>
                    null,
            ];
        }

        $baseDataset =
            $resolved['base']
            ?? null;

        $targetDataset =
            $resolved['target']
            ?? null;

        if (
            ! is_array($baseDataset)
            || ! is_array($targetDataset)
        ) {
            throw new RuntimeException(
                'El par versionado no contiene '
                .'descriptores canónicos válidos.'
            );
        }

        $baseContext =
            DataTransformationBiCanonicalDatasetContext
                ::fromResolved(
                    $companyId,
                    $implementationRequestId,
                    $baseDataset
                );

        $targetContext =
            DataTransformationBiCanonicalDatasetContext
                ::fromResolved(
                    $companyId,
                    $implementationRequestId,
                    $targetDataset
                );

        $baseManifest =
            $this->manifestBuilder
                ->build(
                    $baseContext,
                    $pageSize
                );

        $targetManifest =
            $this->manifestBuilder
                ->build(
                    $targetContext,
                    $pageSize
                );

        $baseFingerprint =
            $this->manifestFingerprint(
                $baseManifest
            );

        $targetFingerprint =
            $this->manifestFingerprint(
                $targetManifest
            );

        $compatibility =
            $resolved['compatibility']
            ?? null;

        if (! is_array($compatibility)) {
            throw new RuntimeException(
                'El par versionado no contiene '
                .'compatibilidad válida.'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                (string) (
                    $resolved['reason']
                    ?? 'compatible_versioned_dataset_pair'
                ),

            'base' => [
                'context' =>
                    $baseContext,

                'manifest' =>
                    $baseManifest,
            ],

            'target' => [
                'context' =>
                    $targetContext,

                'manifest' =>
                    $targetManifest,
            ],

            'compatibility' =>
                $compatibility,

            'same_content' =>
                hash_equals(
                    $baseFingerprint,
                    $targetFingerprint
                ),
        ];
    }

    /**
     * @param array<string,mixed> $resolved
     */
    private function availableDataset(
        array $resolved
    ): bool {
        return
            ($resolved['available'] ?? false)
                === true
            && is_array(
                $resolved['dataset']
                ?? null
            );
    }

    /**
     * @return array{
     *     available:false,
     *     reason:string,
     *     context:null,
     *     manifest:null
     * }
     */
    private function unavailable(
        string $reason
    ): array {
        return [
            'available' =>
                false,

            'reason' =>
                $reason,

            'context' =>
                null,

            'manifest' =>
                null,
        ];
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function manifestFingerprint(
        array $manifest
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
                'El manifest no contiene '
                .'un fingerprint SHA-256 válido.'
            );
        }

        return $fingerprint;
    }
}
