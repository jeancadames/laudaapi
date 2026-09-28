<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\TransformationImplementationRequest;

final class DataTransformationBiTenantPublishedEvaluationProjection
{
    /**
     * Return only the latest published professional evaluation
     * belonging to this exact Data BI implementation request.
     *
     * Draft and ready-for-review evaluations are intentionally invisible
     * to the tenant.
     *
     * @return array<string,mixed>|null
     */
    public function forRequest(
        TransformationImplementationRequest $request
    ): ?array {
        if (
            (string) $request->capability_key
                !== 'data_transformation_bi'
            || (int) $request->company_id <= 0
        ) {
            return null;
        }

        $evaluation =
            DataTransformationBiEvaluation::query()
                ->where(
                    'company_id',
                    (int) $request->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    (int) $request->getKey()
                )
                ->where(
                    'status',
                    DataTransformationBiEvaluation::STATUS_PUBLISHED
                )
                ->with([
                    'findings.sources',
                ])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->first();

        return $this->project(
            $evaluation
        );
    }

    /**
     * Deliberately narrow tenant projection.
     *
     * It must not expose:
     * - evaluation/finding/source IDs;
     * - draft/review lifecycle state;
     * - evidence versions or hashes;
     * - snapshots;
     * - actor IDs;
     * - Admin actions.
     *
     * @return array<string,mixed>|null
     */
    public function project(
        ?DataTransformationBiEvaluation $evaluation
    ): ?array {
        if (
            $evaluation === null
            || ! $evaluation->isPublished()
        ) {
            return null;
        }

        $evaluation->loadMissing([
            'findings.sources',
        ]);

        $findings =
            $evaluation
                ->findings
                ->map(
                    static function (
                        DataTransformationBiEvaluationFinding $finding
                    ): array {
                        $sources =
                            $finding
                                ->sources
                                ->pluck('display_name')
                                ->filter(
                                    static fn ($name): bool =>
                                        is_string($name)
                                        && trim($name) !== ''
                                )
                                ->map(
                                    static fn ($name): string =>
                                        trim(
                                            (string) $name
                                        )
                                )
                                ->unique()
                                ->values()
                                ->all();

                        return [
                            'finding_type' =>
                                (string) $finding->finding_type,

                            'title' =>
                                (string) $finding->title,

                            'details' =>
                                (string) $finding->details,

                            'recommendation' =>
                                $finding->recommendation !== null
                                    ? (string) $finding->recommendation
                                    : null,

                            'priority' =>
                                $finding->priority !== null
                                    ? (string) $finding->priority
                                    : null,

                            'sources' =>
                                $sources,
                        ];
                    }
                )
                ->values();

        return [
            'published_at' =>
                $evaluation
                    ->published_at
                    ?->toISOString(),

            'summary' => [
                'weakness_count' =>
                    $findings
                        ->where(
                            'finding_type',
                            DataTransformationBiEvaluationFinding
                                ::TYPE_WEAKNESS
                        )
                        ->count(),

                'opportunity_count' =>
                    $findings
                        ->where(
                            'finding_type',
                            DataTransformationBiEvaluationFinding
                                ::TYPE_OPPORTUNITY
                        )
                        ->count(),

                'observation_count' =>
                    $findings
                        ->where(
                            'finding_type',
                            DataTransformationBiEvaluationFinding
                                ::TYPE_OBSERVATION
                        )
                        ->count(),
            ],

            'findings' =>
                $findings->all(),
        ];
    }
}
