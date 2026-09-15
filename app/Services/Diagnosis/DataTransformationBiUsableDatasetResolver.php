<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiProcessingRun;

final class DataTransformationBiUsableDatasetResolver
{
    /**
     * Maximum number of completed candidates inspected defensively.
     *
     * In normal operation the first candidate should be valid. Older
     * candidates are inspected only so a persistence inconsistency in a
     * newer run cannot displace the last known-good normalized dataset.
     */
    private const CANDIDATE_LIMIT = 50;

    /**
     * Resolve the canonical current usable dataset for one company/request.
     *
     * This is intentionally different from PreparationStatusReadModel:
     *
     * - PreparationStatusReadModel = latest attempt.
     * - This resolver = latest successful, internally consistent dataset.
     *
     * No row payload is exposed.
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
    public function forRequest(
        int $companyId,
        int $implementationRequestId
    ): array {
        if (
            $companyId <= 0
            || $implementationRequestId <= 0
        ) {
            return $this->unavailable();
        }

        $candidates =
            DataTransformationBiProcessingRun::query()
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
                                DataTransformationBiIntakeBatch::STATUS_COMPLETED
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
                ->orderByDesc('id')
                ->limit(
                    self::CANDIDATE_LIMIT
                )
                ->get();

        foreach ($candidates as $run) {
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

            /*
             * A completed flag alone is not enough to become the canonical
             * usable dataset. The aggregate persisted on the run must agree
             * with the actual number of normalized rows belonging to it.
             *
             * If a newer completed run is inconsistent, keep searching
             * backwards instead of displacing the last known-good dataset.
             */
            if (
                $persistedCount
                !== $recordedCount
            ) {
                continue;
            }

            return [
                'available' =>
                    true,

                'reason' =>
                    'latest_successful_normalized_run',

                'dataset' => [
                    'processing_run_id' =>
                        (int) $run->id,

                    'intake_batch_id' =>
                        (int) $run
                            ->data_transformation_bi_intake_batch_id,

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

        return $this->unavailable();
    }

    /**
     * Canonical derived pointer for downstream internal consumers.
     */
    public function currentProcessingRunId(
        int $companyId,
        int $implementationRequestId
    ): ?int {
        $resolved =
            $this->forRequest(
                $companyId,
                $implementationRequestId
            );

        $runId =
            $resolved['dataset']['processing_run_id']
            ?? null;

        return $runId !== null
            ? (int) $runId
            : null;
    }

    /**
     * @return array{
     *     available:false,
     *     reason:string,
     *     dataset:null
     * }
     */
    private function unavailable(): array
    {
        return [
            'available' =>
                false,

            'reason' =>
                'no_successful_normalized_dataset',

            'dataset' =>
                null,
        ];
    }
}
