<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeBatch;
use App\Models\DataTransformationBiProcessingRun;

final class DataTransformationBiPreparationStatusReadModel
{
    /**
     * Read-only projection of the latest Data BI preparation state.
     *
     * Intentionally excludes:
     * - source_path
     * - validation_snapshot
     * - source hashes
     * - normalized_payload
     * - normalization_meta
     * - failure_message
     * - any row-level business data
     */
    public function forRequest(
        int $companyId,
        int $implementationRequestId
    ): ?array {
        if (
            $companyId <= 0
            || $implementationRequestId <= 0
        ) {
            return null;
        }

        $batch =
            DataTransformationBiIntakeBatch::query()
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequestId
                )
                ->orderByDesc('id')
                ->first([
                    'id',
                    'company_id',
                    'transformation_implementation_request_id',
                    'status',
                    'domain_count',
                    'source_row_count',
                    'staged_row_count',
                    'rejected_row_count',
                    'completed_at',
                ]);

        if ($batch === null) {
            return null;
        }

        $run =
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
                    'data_transformation_bi_intake_batch_id',
                    (int) $batch->id
                )
                ->orderByDesc('id')
                ->first([
                    'id',
                    'data_transformation_bi_intake_batch_id',
                    'company_id',
                    'transformation_implementation_request_id',
                    'status',
                    'profiled_row_count',
                    'normalized_row_count',
                    'issue_count',
                    'blocking_issue_count',
                    'warning_issue_count',
                    'completed_at',
                ]);

        $stage =
            $this->stage(
                $batch,
                $run
            );

        $processing =
            null;

        if ($run !== null) {
            $issueCount =
                max(
                    0,
                    (int) $run->issue_count
                );

            $blockingCount =
                max(
                    0,
                    (int) $run->blocking_issue_count
                );

            $warningCount =
                max(
                    0,
                    (int) $run->warning_issue_count
                );

            $processing = [
                'run_id' =>
                    (int) $run->id,

                'status' =>
                    (string) $run->status,

                'profiled_row_count' =>
                    (int) $run->profiled_row_count,

                'normalized_row_count' =>
                    (int) $run->normalized_row_count,

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

                'normalization_completed' =>
                    $run->status
                    === DataTransformationBiProcessingRun::STATUS_COMPLETED,

                'completed_at' =>
                    $run->completed_at
                        ?->toISOString(),
            ];
        }

        return [
            'stage' =>
                $stage,

            'stage_label' =>
                $this->stageLabel(
                    $stage
                ),

            'batch' => [
                'batch_id' =>
                    (int) $batch->id,

                'status' =>
                    (string) $batch->status,

                'domain_count' =>
                    (int) $batch->domain_count,

                'source_row_count' =>
                    (int) $batch->source_row_count,

                'staged_row_count' =>
                    (int) $batch->staged_row_count,

                'rejected_row_count' =>
                    (int) $batch->rejected_row_count,

                'completed_at' =>
                    $batch->completed_at
                        ?->toISOString(),
            ],

            'processing' =>
                $processing,
        ];
    }

    private function stage(
        DataTransformationBiIntakeBatch $batch,
        ?DataTransformationBiProcessingRun $run
    ): string {
        if (
            $batch->status
            === DataTransformationBiIntakeBatch::STATUS_FAILED
        ) {
            return 'intake_failed';
        }

        if (
            $batch->status
            === DataTransformationBiIntakeBatch::STATUS_PURGED
        ) {
            return 'purged';
        }

        if (
            in_array(
                $batch->status,
                [
                    DataTransformationBiIntakeBatch::STATUS_PENDING,
                    DataTransformationBiIntakeBatch::STATUS_PROCESSING,
                ],
                true
            )
        ) {
            return 'intake_processing';
        }

        if ($run === null) {
            return 'staged';
        }

        if (
            $run->status
            === DataTransformationBiProcessingRun::STATUS_FAILED
        ) {
            return 'processing_failed';
        }

        if (
            $run->status
            === DataTransformationBiProcessingRun::STATUS_COMPLETED
        ) {
            return 'normalized';
        }

        if (
            (int) $run->profiled_row_count > 0
        ) {
            return 'profiled';
        }

        return 'processing';
    }

    private function stageLabel(
        string $stage
    ): string {
        return match ($stage) {
            'intake_processing' =>
                'Ingreso en proceso',

            'intake_failed' =>
                'Ingreso con incidencia',

            'staged' =>
                'Staging completado',

            'processing' =>
                'Analizando calidad',

            'profiled' =>
                'Calidad analizada',

            'processing_failed' =>
                'Procesamiento con incidencia',

            'normalized' =>
                'Normalización completada',

            'purged' =>
                'Fuente depurada',

            default =>
                'Sin procesamiento',
        };
    }
}
