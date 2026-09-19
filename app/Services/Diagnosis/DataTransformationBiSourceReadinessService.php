<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetFile;
use App\Models\TransformationImplementationRequest;
use Illuminate\Support\Facades\Schema;

final class DataTransformationBiSourceReadinessService
{
    /**
     * Machine-owned compatibility projection for Definition readiness.
     *
     * inputs_validated:
     * all active client-native sources have a current valid CSV/XLSX artifact.
     *
     * accesses_validated:
     * legacy compatibility mirror only. Data BI does not request, store or
     * verify remote database access or client credentials.
     *
     * @return array{
     *     inputs_validated:bool,
     *     accesses_validated:bool,
     *     source_count:int,
     *     complete_source_count:int
     * }
     */
    public function forRequest(
        TransformationImplementationRequest $request
    ): array {
        if (
            ! Schema::hasTable(
                'data_transformation_bi_intake_sessions'
            )
            || ! Schema::hasTable(
                'data_transformation_bi_source_assets'
            )
            || ! Schema::hasTable(
                'data_transformation_bi_source_asset_files'
            )
        ) {
            return $this->emptyPayload();
        }

        $session =
            DataTransformationBiIntakeSession::query()
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    (int) $request->getKey()
                )
                ->orderByDesc('id')
                ->first();

        if ($session === null) {
            return $this->emptyPayload();
        }

        $sources =
            DataTransformationBiSourceAsset::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->whereNull(
                    'archived_at'
                );

        $sourceCount =
            (clone $sources)
                ->count();

        if ($sourceCount === 0) {
            return $this->emptyPayload();
        }

        $completeSourceCount =
            (clone $sources)
                ->whereIn(
                    'status',
                    [
                        DataTransformationBiSourceAsset::STATUS_ACTIVE,
                        DataTransformationBiSourceAsset::STATUS_READY,
                    ]
                )
                ->whereIn(
                    'data_status',
                    [
                        DataTransformationBiSourceAsset::DATA_RECEIVED,
                        DataTransformationBiSourceAsset::DATA_ANALYZED,
                    ]
                )
                ->whereHas(
                    'dataFile',
                    static function ($query): void {
                        $query
                            ->where(
                                'status',
                                DataTransformationBiSourceAssetFile::STATUS_UPLOADED
                            )
                            ->whereIn(
                                'source_format',
                                [
                                    DataTransformationBiSourceAssetFile::FORMAT_CSV,
                                    DataTransformationBiSourceAssetFile::FORMAT_XLSX,
                                ]
                            );
                    }
                )
                ->count();

        $complete =
            $completeSourceCount === $sourceCount;

        return [
            'inputs_validated' => $complete,

            /*
             * Nombre legado conservado exclusivamente porque el
             * DefinitionReviewService genérico todavía opera con
             * seis checks. No significa acceso remoto.
             */
            'accesses_validated' => $complete,

            'source_count' => $sourceCount,

            'complete_source_count' => $completeSourceCount,
        ];
    }

    /**
     * @return array{
     *     inputs_validated:bool,
     *     accesses_validated:bool,
     *     source_count:int,
     *     complete_source_count:int
     * }
     */
    private function emptyPayload(): array
    {
        return [
            'inputs_validated' => false,

            'accesses_validated' => false,

            'source_count' => 0,

            'complete_source_count' => 0,
        ];
    }
}
