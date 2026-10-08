<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiImplementationDataset;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\DataTransformationBiSourceAssetMapping;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DataTransformationBiImplementationDatasetOrchestrator
{
    public function __construct(
        private readonly TransformationImplementationAuthorizationGate
            $authorizationGate,

        private readonly DataTransformationBiImplementationDatasetMaterializer
            $materializer
    ) {
    }

    /**
     * Materialize every CURRENT validated semantic mapping in one
     * implementation session.
     *
     * Important:
     * - this does NOT mean implementation execution has started;
     * - this does NOT mutate ready_for_execution;
     * - this does NOT mutate execution_started;
     * - historical mapping versions are never selected as fallback.
     *
     * @return array{
     *     session_id:int,
     *     selected_mapping_count:int,
     *     materialized_dataset_count:int,
     *     reused_dataset_count:int,
     *     datasets:array<int,array<string,mixed>>
     * }
     */
    public function materializeSession(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session,
        User $actor
    ): array {
        $this->authorizationGate
            ->assertActiveForRequest(
                $request
            );

        $this->assertSessionScope(
            $request,
            $session
        );

        $currentMappings =
            $this->currentMappings(
                $request,
                $session
            );

        if ($currentMappings->isEmpty()) {
            throw ValidationException::withMessages([
                'mappings' => [
                    'La sesión no contiene mapeos técnicos actuales.',
                ],
            ]);
        }

        $notValidated =
            $currentMappings
                ->filter(
                    static fn (
                        DataTransformationBiSourceAssetMapping $mapping
                    ): bool =>
                        (string) $mapping->status
                        !== DataTransformationBiSourceAssetMapping
                            ::STATUS_VALIDATED
                )
                ->values();

        if ($notValidated->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mappings' => [
                    'Todos los mapeos técnicos actuales deben estar VALIDATED antes de materializar la sesión.',
                ],
            ]);
        }

        $datasets = [];
        $reused = 0;

        foreach ($currentMappings as $mapping) {
            $asset =
                DataTransformationBiSourceAsset::query()
                    ->whereKey(
                        (int) $mapping
                            ->data_transformation_bi_source_asset_id
                    )
                    ->where(
                        'company_id',
                        (int) $request->company_id
                    )
                    ->where(
                        'data_transformation_bi_intake_session_id',
                        (int) $session->getKey()
                    )
                    ->first();

            if ($asset === null) {
                throw new RuntimeException(
                    'Un mapeo actual apunta a una fuente fuera del alcance de la sesión.'
                );
            }

            $existing =
                DataTransformationBiImplementationDataset::query()
                    ->where(
                        'data_transformation_bi_source_asset_mapping_id',
                        (int) $mapping->getKey()
                    )
                    ->where(
                        'status',
                        DataTransformationBiImplementationDataset
                            ::STATUS_READY
                    )
                    ->first();

            $dataset =
                $this->materializer
                    ->materialize(
                        $request,
                        $session,
                        $asset,
                        $mapping,
                        $actor
                    );

            if (
                $existing !== null
                && (int) $existing->getKey()
                    === (int) $dataset->getKey()
            ) {
                $reused++;
            }

            $datasets[] = [
                'dataset_id' =>
                    (int) $dataset->getKey(),

                'mapping_id' =>
                    (int) $mapping->getKey(),

                'source_asset_id' =>
                    (int) $asset->getKey(),

                'canonical_entity_key' =>
                    (string) $dataset
                        ->canonical_entity_key,

                'mapping_version' =>
                    (int) $dataset->mapping_version,

                'canonical_registry_version' =>
                    (int) $dataset
                        ->canonical_registry_version,

                'status' =>
                    (string) $dataset->status,

                'row_count' =>
                    (int) $dataset->row_count,

                'dataset_sha256' =>
                    (string) $dataset
                        ->dataset_sha256,

                'materialized_at' =>
                    $dataset->materialized_at
                        ?->toISOString(),
            ];
        }

        return [
            'session_id' =>
                (int) $session->getKey(),

            'selected_mapping_count' =>
                $currentMappings->count(),

            'materialized_dataset_count' =>
                count($datasets),

            'reused_dataset_count' =>
                $reused,

            'datasets' =>
                $datasets,
        ];
    }

    /**
     * Return ONLY the newest semantic mapping for each logical target:
     *
     * source_asset_id
     * + canonical_entity_key
     * + source_sheet_index
     *
     * Never fall back to an older VALIDATED mapping when a newer mapping
     * exists in draft/ready/blocked state.
     *
     * @return Collection<int,DataTransformationBiSourceAssetMapping>
     */
    private function currentMappings(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): Collection {
        $mappings =
            DataTransformationBiSourceAssetMapping::query()
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->where(
                    'data_transformation_bi_intake_session_id',
                    (int) $session->getKey()
                )
                ->orderBy(
                    'data_transformation_bi_source_asset_id'
                )
                ->orderBy(
                    'canonical_entity_key'
                )
                ->orderBy(
                    'source_sheet_index'
                )
                ->orderByDesc(
                    'mapping_version'
                )
                ->orderByDesc('id')
                ->get();

        return $mappings
            ->unique(
                static fn (
                    DataTransformationBiSourceAssetMapping $mapping
                ): string =>
                    implode(
                        ':',
                        [
                            (int) $mapping
                                ->data_transformation_bi_source_asset_id,

                            (string) $mapping
                                ->canonical_entity_key,

                            (int) $mapping
                                ->source_sheet_index,
                        ]
                    )
            )
            ->values();
    }

    private function assertSessionScope(
        TransformationImplementationRequest $request,
        DataTransformationBiIntakeSession $session
    ): void {
        if (
            (int) $session->company_id
                !== (int) $request->company_id

            || (int) $session
                ->transformation_implementation_request_id
                !== (int) $request->getKey()
        ) {
            throw new AuthorizationException(
                'La sesión no pertenece a esta implementación.'
            );
        }
    }
}
