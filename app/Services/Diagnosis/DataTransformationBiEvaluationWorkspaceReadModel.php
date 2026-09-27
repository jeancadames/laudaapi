<?php

namespace App\Services\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiIntakeSession;
use App\Models\DataTransformationBiSourceAsset;
use App\Models\TransformationImplementationRequest;

final class DataTransformationBiEvaluationWorkspaceReadModel
{
    public function forSession(
        TransformationImplementationRequest $implementationRequest,
        DataTransformationBiIntakeSession $session
    ): array {
        $evaluation =
            DataTransformationBiEvaluation::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    $session->getKey()
                )
                ->where(
                    'company_id',
                    $implementationRequest->company_id
                )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->getKey()
                )
                ->with([
                    'findings.sources',
                ])
                ->first();

        $sources =
            DataTransformationBiSourceAsset::query()
                ->where(
                    'data_transformation_bi_intake_session_id',
                    $session->getKey()
                )
                ->where(
                    'company_id',
                    $implementationRequest->company_id
                )
                ->whereNull('archived_at')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        return [
            'session' => [
                'id' =>
                    (int) $session->getKey(),

                'status' =>
                    (string) $session->status,

                'submitted_at' =>
                    $session->submitted_at?->toISOString(),
            ],

            'sources' =>
                $sources
                    ->map(
                        fn (
                            DataTransformationBiSourceAsset $source
                        ): array =>
                            $this->sourcePayload(
                                $source
                            )
                    )
                    ->values()
                    ->all(),

            'evaluation' =>
                $evaluation
                    ? $this->evaluationPayload(
                        $evaluation
                    )
                    : null,

            'actions' =>
                $this->actions(
                    $session,
                    $evaluation
                ),
        ];
    }

    private function sourcePayload(
        DataTransformationBiSourceAsset $source
    ): array {
        $summary =
            is_array(
                $source->profiling_snapshot
            )
                ? DataTransformationBiSourceDiagnosticReadModel
                    ::fromSnapshot(
                        $source->profiling_snapshot
                    )
                : null;

        if (
            is_array($summary)
            && ($summary['available'] ?? false) !== true
        ) {
            $summary = null;
        }

        return [
            'id' =>
                (int) $source->getKey(),

            'display_name' =>
                (string) $source->display_name,

            'source_object_name' =>
                (string) $source->source_object_name,

            'origin_system' =>
                $source->origin_system !== null
                    ? (string) $source->origin_system
                    : null,

            'profiling_status' =>
                (string) $source->profiling_status,

            'profiled_at' =>
                $source->profiled_at?->toISOString(),

            /*
             * Aggregate diagnostic metadata only.
             * Never expose the technical profiling snapshot itself.
             */
            'diagnostic_summary' =>
                $summary,
        ];
    }

    private function evaluationPayload(
        DataTransformationBiEvaluation $evaluation
    ): array {
        $findings =
            $evaluation
                ->findings
                ->map(
                    function (
                        DataTransformationBiEvaluationFinding $finding
                    ) use (
                        $evaluation
                    ): array {
                        return [
                            'id' =>
                                (int) $finding->getKey(),

                            'finding_type' =>
                                (string) $finding->finding_type,

                            'title' =>
                                (string) $finding->title,

                            'details' =>
                                (string) $finding->details,

                            'recommendation' =>
                                $finding->recommendation,

                            'priority' =>
                                $finding->priority,

                            'evidence_version' =>
                                (int) $finding
                                    ->evidence_version,

                            'evidence_current' =>
                                (int) $finding
                                    ->evidence_version
                                === (int) $evaluation
                                    ->evidence_version,

                            'sort_order' =>
                                (int) $finding->sort_order,

                            'source_ids' =>
                                $finding
                                    ->sources
                                    ->pluck('id')
                                    ->map(
                                        fn ($id): int =>
                                            (int) $id
                                    )
                                    ->values()
                                    ->all(),
                        ];
                    }
                )
                ->values()
                ->all();

        $staleCount =
            collect(
                $findings
            )
                ->where(
                    'evidence_current',
                    false
                )
                ->count();

        return [
            'id' =>
                (int) $evaluation->getKey(),

            'status' =>
                (string) $evaluation->status,

            'evidence_version' =>
                (int) $evaluation->evidence_version,

            'evidence_captured_at' =>
                $evaluation
                    ->evidence_captured_at
                    ?->toISOString(),

            'ready_for_review_at' =>
                $evaluation
                    ->ready_for_review_at
                    ?->toISOString(),

            'published_at' =>
                $evaluation
                    ->published_at
                    ?->toISOString(),

            'finding_count' =>
                count(
                    $findings
                ),

            'stale_finding_count' =>
                $staleCount,

            'findings' =>
                $findings,
        ];
    }

    private function actions(
        DataTransformationBiIntakeSession $session,
        ?DataTransformationBiEvaluation $evaluation
    ): array {
        $submitted =
            $session->status
            === DataTransformationBiIntakeSession
                ::STATUS_SUBMITTED_FOR_EVALUATION;

        return [
            'can_prepare' =>
                $submitted
                && (
                    $evaluation === null
                    || $evaluation->isDraft()
                ),

            'can_manage_findings' =>
                $submitted
                && $evaluation?->isDraft() === true,

            'can_mark_ready_for_review' =>
                $submitted
                && $evaluation?->isDraft() === true,

            'can_publish' =>
                $submitted
                && $evaluation?->isReadyForReview() === true,
        ];
    }
}
