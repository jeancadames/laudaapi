<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiProcessingRun;

final class DataTransformationBiProcessingHistoryReadModel
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    /**
     * Read-only traceability of intake batches and their processing runs.
     *
     * Only operational metadata and aggregate counters are projected.
     *
     * @return array{
     *     summary:array{
     *         total_batches:int,
     *         total_runs:int,
     *         shown_batches:int,
     *         has_more:bool
     *     },
     *     entries:array<int, array<string, mixed>>
     * }
     */
    public function forRequest(
        int $companyId,
        int $implementationRequestId,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        if (
            $companyId <= 0
            || $implementationRequestId <= 0
        ) {
            return $this->emptyResult();
        }

        $limit =
            max(
                1,
                min(
                    self::MAX_LIMIT,
                    $limit
                )
            );

        $batchScope =
            DataTransformationBiIntakeBatch::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequestId
                );

        $totalBatches =
            (clone $batchScope)
                ->count();

        $batches =
            $batchScope
                ->orderByDesc('id')
                ->limit(
                    $limit
                )
                ->get([
                    'id',
                    'company_id',
                    'transformation_implementation_request_id',
                    'transformation_implementation_definition_id',
                    'definition_version',
                    'schema_version',
                    'status',
                    'domain_count',
                    'source_row_count',
                    'staged_row_count',
                    'rejected_row_count',
                    'started_at',
                    'completed_at',
                    'failed_at',
                    'purged_at',
                    'created_at',
                ]);

        if ($batches->isEmpty()) {
            return [
                'summary' => [
                    'total_batches' =>
                        $totalBatches,

                    'total_runs' =>
                        0,

                    'shown_batches' =>
                        0,

                    'has_more' =>
                        false,
                ],

                'entries' =>
                    [],
            ];
        }

        $batchIds =
            $batches
                ->pluck('id')
                ->map(
                    static fn ($id): int =>
                        (int) $id
                )
                ->all();

        $runScope =
            DataTransformationBiProcessingRun::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequestId
                );

        $totalRuns =
            (clone $runScope)
                ->count();

        $runsByBatch =
            $runScope
                ->whereIn(
                    'data_transformation_bi_intake_batch_id',
                    $batchIds
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'data_transformation_bi_intake_batch_id',
                    'company_id',
                    'transformation_implementation_request_id',
                    'transformation_implementation_definition_id',
                    'definition_version',
                    'schema_version',
                    'profiling_version',
                    'normalization_version',
                    'status',
                    'source_row_count',
                    'profiled_row_count',
                    'normalized_row_count',
                    'issue_count',
                    'blocking_issue_count',
                    'warning_issue_count',
                    'started_at',
                    'completed_at',
                    'failed_at',
                    'created_at',
                ])
                ->groupBy(
                    'data_transformation_bi_intake_batch_id'
                );

        $entries =
            $batches
                ->values()
                ->map(
                    function (
                        DataTransformationBiIntakeBatch $batch,
                        int $index
                    ) use ($runsByBatch): array {
                        $runs =
                            collect(
                                $runsByBatch->get(
                                    (int) $batch->id,
                                    collect()
                                )
                            )
                                ->values()
                                ->map(
                                    function (
                                        DataTransformationBiProcessingRun $run
                                    ): array {
                                        $issueCount =
                                            max(
                                                0,
                                                (int) $run->issue_count
                                            );

                                        $blockingCount =
                                            max(
                                                0,
                                                (int) $run
                                                    ->blocking_issue_count
                                            );

                                        $warningCount =
                                            max(
                                                0,
                                                (int) $run
                                                    ->warning_issue_count
                                            );

                                        return [
                                            'run_id' =>
                                                (int) $run->id,

                                            'status' =>
                                                (string) $run->status,

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

                                            'source_row_count' =>
                                                max(
                                                    0,
                                                    (int) $run
                                                        ->source_row_count
                                                ),

                                            'profiled_row_count' =>
                                                max(
                                                    0,
                                                    (int) $run
                                                        ->profiled_row_count
                                                ),

                                            'normalized_row_count' =>
                                                max(
                                                    0,
                                                    (int) $run
                                                        ->normalized_row_count
                                                ),

                                            'issue_count' =>
                                                $issueCount,

                                            'blocking_issue_count' =>
                                                $blockingCount,

                                            'warning_issue_count' =>
                                                $warningCount,

                                            'informational_issue_count' =>
                                                max(
                                                    0,
                                                    $issueCount
                                                    - $blockingCount
                                                    - $warningCount
                                                ),

                                            'started_at' =>
                                                $run->started_at
                                                    ?->toISOString(),

                                            'completed_at' =>
                                                $run->completed_at
                                                    ?->toISOString(),

                                            'failed_at' =>
                                                $run->failed_at
                                                    ?->toISOString(),

                                            'created_at' =>
                                                $run->created_at
                                                    ?->toISOString(),
                                        ];
                                    }
                                )
                                ->all();

                        return [
                            'batch_id' =>
                                (int) $batch->id,

                            'is_latest' =>
                                $index === 0,

                            'status' =>
                                (string) $batch->status,

                            'definition_version' =>
                                $batch->definition_version !== null
                                    ? (int) $batch->definition_version
                                    : null,

                            'schema_version' =>
                                (int) $batch->schema_version,

                            'domain_count' =>
                                max(
                                    0,
                                    (int) $batch->domain_count
                                ),

                            'source_row_count' =>
                                max(
                                    0,
                                    (int) $batch->source_row_count
                                ),

                            'staged_row_count' =>
                                max(
                                    0,
                                    (int) $batch->staged_row_count
                                ),

                            'rejected_row_count' =>
                                max(
                                    0,
                                    (int) $batch->rejected_row_count
                                ),

                            'started_at' =>
                                $batch->started_at
                                    ?->toISOString(),

                            'completed_at' =>
                                $batch->completed_at
                                    ?->toISOString(),

                            'failed_at' =>
                                $batch->failed_at
                                    ?->toISOString(),

                            'purged_at' =>
                                $batch->purged_at
                                    ?->toISOString(),

                            'created_at' =>
                                $batch->created_at
                                    ?->toISOString(),

                            'run_count' =>
                                count($runs),

                            'runs' =>
                                $runs,
                        ];
                    }
                )
                ->all();

        return [
            'summary' => [
                'total_batches' =>
                    $totalBatches,

                'total_runs' =>
                    $totalRuns,

                'shown_batches' =>
                    count($entries),

                'has_more' =>
                    $totalBatches
                    > count($entries),
            ],

            'entries' =>
                $entries,
        ];
    }

    /**
     * @return array{
     *     summary:array{
     *         total_batches:int,
     *         total_runs:int,
     *         shown_batches:int,
     *         has_more:bool
     *     },
     *     entries:array<int, array<string, mixed>>
     * }
     */
    private function emptyResult(): array
    {
        return [
            'summary' => [
                'total_batches' =>
                    0,

                'total_runs' =>
                    0,

                'shown_batches' =>
                    0,

                'has_more' =>
                    false,
            ],

            'entries' =>
                [],
        ];
    }
}
