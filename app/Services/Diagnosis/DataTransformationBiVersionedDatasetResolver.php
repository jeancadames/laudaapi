<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiNormalizedRow;
use App\Models\DataTransformationBiProcessingRun;

final class DataTransformationBiVersionedDatasetResolver
{
    /**
     * Resolve one explicit historical normalized dataset.
     *
     * Unlike P13, this does not search for "latest". The caller names the
     * exact processing run and this service proves that the run still forms
     * a coherent, usable dataset for the requested company/request.
     *
     * @return array{
     *     available:bool,
     *     reason:string,
     *     dataset:null|array{
     *         processing_run_id:int,
     *         intake_batch_id:int,
     *         definition_version:int|null,
     *         schema_version:int,
     *         profiling_version:int,
     *         normalization_version:int,
     *         normalized_row_count:int,
     *         has_rows:bool,
     *         completed_at:string|null
     *     }
     * }
     */
    public function forProcessingRun(
        int $companyId,
        int $implementationRequestId,
        int $processingRunId
    ): array {
        if (
            $companyId <= 0
            || $implementationRequestId <= 0
            || $processingRunId <= 0
        ) {
            return $this->unavailable(
                'invalid_dataset_identity'
            );
        }

        $run =
            DataTransformationBiProcessingRun::query()
                ->whereKey(
                    $processingRunId
                )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequestId
                )
                ->where(
                    'status',
                    DataTransformationBiProcessingRun::STATUS_COMPLETED
                )
                ->where(
                    'blocking_issue_count',
                    0
                )
                ->whereHas(
                    'batch',
                    function ($query) use (
                        $companyId,
                        $implementationRequestId
                    ): void {
                        $query
                            ->where(
                                'company_id',
                                $companyId
                            )
                            ->where(
                                'transformation_implementation_request_id',
                                $implementationRequestId
                            )
                            ->where(
                                'status',
                                DataTransformationBiIntakeBatch
                                    ::STATUS_COMPLETED
                            );
                    }
                )
                ->select([
                    'id',
                    'data_transformation_bi_intake_batch_id',
                    'company_id',
                    'transformation_implementation_request_id',
                    'definition_version',
                    'schema_version',
                    'profiling_version',
                    'normalization_version',
                    'status',
                    'normalized_row_count',
                    'blocking_issue_count',
                    'completed_at',
                ])
                ->withCount(
                    'normalizedRows'
                )
                ->first();

        if ($run === null) {
            return $this->unavailable(
                'processing_run_not_usable'
            );
        }

        $recordedCount =
            max(
                0,
                (int) $run->normalized_row_count
            );

        $persistedCount =
            max(
                0,
                (int) $run->normalized_rows_count
            );

        if (
            $persistedCount
            !== $recordedCount
        ) {
            return $this->unavailable(
                'normalized_row_count_mismatch'
            );
        }

        $batchId =
            (int) $run
                ->data_transformation_bi_intake_batch_id;

        if ($batchId <= 0) {
            return $this->unavailable(
                'invalid_intake_batch_identity'
            );
        }

        /*
         * P14/P17 always read by company + run + batch. Validate the same
         * exact physical scope here so an explicit historical descriptor
         * can never point at a partially inconsistent dataset.
         */
        $scopedCount =
            DataTransformationBiNormalizedRow::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'data_transformation_bi_processing_run_id',
                    $processingRunId
                )
                ->where(
                    'data_transformation_bi_intake_batch_id',
                    $batchId
                )
                ->count();

        if (
            $scopedCount
            !== $recordedCount
        ) {
            return $this->unavailable(
                'normalized_dataset_scope_mismatch'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                'specific_successful_normalized_run',

            'dataset' => [
                'processing_run_id' =>
                    (int) $run->id,

                'intake_batch_id' =>
                    $batchId,

                'definition_version' =>
                    $run->definition_version !== null
                        ? (int) $run->definition_version
                        : null,

                'schema_version' =>
                    (int) $run->schema_version,

                'profiling_version' =>
                    (int) $run->profiling_version,

                'normalization_version' =>
                    (int) $run->normalization_version,

                'normalized_row_count' =>
                    $recordedCount,

                'has_rows' =>
                    $recordedCount > 0,

                'completed_at' =>
                    $run->completed_at
                        ?->toISOString(),
            ],
        ];
    }

    /**
     * Resolve an ordered historical pair suitable for canonical delta.
     *
     * The target must be newer than the base because added/removed semantics
     * are directional. Schema and normalization versions must match so a
     * payload delta represents data change rather than pipeline semantics.
     *
     * @return array<string,mixed>
     */
    public function pair(
        int $companyId,
        int $implementationRequestId,
        int $baseProcessingRunId,
        int $targetProcessingRunId
    ): array {
        if (
            $baseProcessingRunId <= 0
            || $targetProcessingRunId <= 0
            || $targetProcessingRunId
                <= $baseProcessingRunId
        ) {
            return $this->unavailablePair(
                'invalid_dataset_pair_order'
            );
        }

        $base =
            $this->forProcessingRun(
                $companyId,
                $implementationRequestId,
                $baseProcessingRunId
            );

        if (
            ($base['available'] ?? false)
            !== true
            || ! is_array(
                $base['dataset']
                ?? null
            )
        ) {
            return $this->unavailablePair(
                'base_dataset_not_usable'
            );
        }

        $target =
            $this->forProcessingRun(
                $companyId,
                $implementationRequestId,
                $targetProcessingRunId
            );

        if (
            ($target['available'] ?? false)
            !== true
            || ! is_array(
                $target['dataset']
                ?? null
            )
        ) {
            return $this->unavailablePair(
                'target_dataset_not_usable'
            );
        }

        /** @var array<string,mixed> $baseDataset */
        $baseDataset =
            $base['dataset'];

        /** @var array<string,mixed> $targetDataset */
        $targetDataset =
            $target['dataset'];

        if (
            (int) $baseDataset['schema_version']
            !== (int) $targetDataset['schema_version']
        ) {
            return $this->unavailablePair(
                'schema_version_mismatch'
            );
        }

        if (
            (int) $baseDataset['normalization_version']
            !== (int) $targetDataset['normalization_version']
        ) {
            return $this->unavailablePair(
                'normalization_version_mismatch'
            );
        }

        return [
            'available' =>
                true,

            'reason' =>
                'compatible_versioned_dataset_pair',

            'base' =>
                $baseDataset,

            'target' =>
                $targetDataset,

            'compatibility' => [
                'schema_version' =>
                    (int) $baseDataset[
                        'schema_version'
                    ],

                'normalization_version' =>
                    (int) $baseDataset[
                        'normalization_version'
                    ],
            ],
        ];
    }

    /**
     * @return array{
     *     available:false,
     *     reason:string,
     *     dataset:null
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

            'dataset' =>
                null,
        ];
    }

    /**
     * @return array{
     *     available:false,
     *     reason:string,
     *     base:null,
     *     target:null,
     *     compatibility:null
     * }
     */
    private function unavailablePair(
        string $reason
    ): array {
        return [
            'available' =>
                false,

            'reason' =>
                $reason,

            'base' =>
                null,

            'target' =>
                null,

            'compatibility' =>
                null,
        ];
    }
}
