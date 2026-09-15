<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiDomainProfile;
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

        $domains =
            [];

        if ($run !== null) {
            $domains =
                DataTransformationBiDomainProfile::query()
                    ->where(
                        'data_transformation_bi_processing_run_id',
                        (int) $run->id
                    )
                    ->where(
                        'data_transformation_bi_intake_batch_id',
                        (int) $batch->id
                    )
                    ->where(
                        'company_id',
                        $companyId
                    )
                    ->get([
                        'domain_key',
                        'row_count',
                        'field_count',
                        'identity_count',
                        'duplicate_identity_count',
                        'issue_count',
                        'blocking_issue_count',
                        'warning_issue_count',
                        'normalized_row_count',
                    ])
                    ->sortBy(
                        fn (
                            DataTransformationBiDomainProfile $profile
                        ): int =>
                            $this->domainOrder(
                                (string) $profile->domain_key
                            )
                    )
                    ->values()
                    ->map(
                        function (
                            DataTransformationBiDomainProfile $profile
                        ) use ($run): array {
                            $issueCount =
                                max(
                                    0,
                                    (int) $profile->issue_count
                                );

                            $blockingCount =
                                max(
                                    0,
                                    (int) $profile
                                        ->blocking_issue_count
                                );

                            $warningCount =
                                max(
                                    0,
                                    (int) $profile
                                        ->warning_issue_count
                                );

                            $informationalCount =
                                max(
                                    0,
                                    $issueCount
                                    - $blockingCount
                                    - $warningCount
                                );

                            $rowCount =
                                max(
                                    0,
                                    (int) $profile->row_count
                                );

                            $normalizedRowCount =
                                max(
                                    0,
                                    (int) $profile
                                        ->normalized_row_count
                                );

                            $normalized =
                                $run->status
                                    === DataTransformationBiProcessingRun::STATUS_COMPLETED
                                && $normalizedRowCount
                                    === $rowCount;

                            $qualityStatus =
                                $blockingCount > 0
                                    ? 'blocking'
                                    : (
                                        $warningCount > 0
                                            ? 'warning'
                                            : 'clean'
                                    );

                            return [
                                'key' =>
                                    (string) $profile->domain_key,

                                'label' =>
                                    $this->domainLabel(
                                        (string) $profile->domain_key
                                    ),

                                'preparation_status' =>
                                    $normalized
                                        ? 'normalized'
                                        : 'profiled',

                                'preparation_label' =>
                                    $normalized
                                        ? 'Normalizado'
                                        : 'Perfilado',

                                'quality_status' =>
                                    $qualityStatus,

                                'quality_label' =>
                                    match ($qualityStatus) {
                                        'blocking' =>
                                            'Requiere corrección',

                                        'warning' =>
                                            'Con advertencias',

                                        default =>
                                            'Sin bloqueos',
                                    },

                                'row_count' =>
                                    $rowCount,

                                'field_count' =>
                                    max(
                                        0,
                                        (int) $profile->field_count
                                    ),

                                'identity_count' =>
                                    max(
                                        0,
                                        (int) $profile->identity_count
                                    ),

                                'duplicate_identity_count' =>
                                    max(
                                        0,
                                        (int) $profile
                                            ->duplicate_identity_count
                                    ),

                                'issue_count' =>
                                    $issueCount,

                                'blocking_issue_count' =>
                                    $blockingCount,

                                'warning_issue_count' =>
                                    $warningCount,

                                'informational_issue_count' =>
                                    $informationalCount,

                                'normalized_row_count' =>
                                    $normalizedRowCount,
                            ];
                        }
                    )
                    ->all();
        }

        $domainSummary = [
            'total' =>
                count($domains),

            'normalized' =>
                count(
                    array_filter(
                        $domains,
                        fn (array $domain): bool =>
                            $domain['preparation_status']
                                === 'normalized'
                    )
                ),

            'profiled' =>
                count(
                    array_filter(
                        $domains,
                        fn (array $domain): bool =>
                            $domain['preparation_status']
                                === 'profiled'
                    )
                ),

            'with_blocking_issues' =>
                count(
                    array_filter(
                        $domains,
                        fn (array $domain): bool =>
                            $domain['blocking_issue_count'] > 0
                    )
                ),

            'with_warnings' =>
                count(
                    array_filter(
                        $domains,
                        fn (array $domain): bool =>
                            $domain['warning_issue_count'] > 0
                    )
                ),

            'clean' =>
                count(
                    array_filter(
                        $domains,
                        fn (array $domain): bool =>
                            $domain['quality_status']
                                === 'clean'
                    )
                ),
        ];

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

            'domain_summary' =>
                $domainSummary,

            'domains' =>
                $domains,
        ];
    }

    private function domainLabel(
        string $domainKey
    ): string {
        return match ($domainKey) {
            'customers' =>
                'Clientes',

            'products' =>
                'Productos',

            'inventory' =>
                'Inventario',

            'sales' =>
                'Ventas',

            'accounts_receivable' =>
                'Cuentas por cobrar',

            'suppliers' =>
                'Suplidores',

            'accounts_payable' =>
                'Cuentas por pagar',

            default =>
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $domainKey
                    )
                ),
        };
    }

    private function domainOrder(
        string $domainKey
    ): int {
        return match ($domainKey) {
            'customers' => 10,
            'products' => 20,
            'inventory' => 30,
            'sales' => 40,
            'accounts_receivable' => 50,
            'suppliers' => 60,
            'accounts_payable' => 70,
            default => 999,
        };
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
