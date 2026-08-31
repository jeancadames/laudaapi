<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityEvaluationSummary;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationCapabilityEvaluationSummaryService
{
    public function saveGeneratedDraft(
        TransformationCapabilityActivation $activation,
        User $actor,
        array $generated
    ): TransformationCapabilityEvaluationSummary {
        $payload =
            $this->payload(
                $generated
            );

        $context =
            is_array(
                $generated[
                    'generation_context'
                ]
                ?? null
            )
                ? $generated[
                    'generation_context'
                ]
                : [];

        return DB::transaction(
            function () use (
                $activation,
                $actor,
                $payload,
                $context
            ): TransformationCapabilityEvaluationSummary {
                $summary =
                    TransformationCapabilityEvaluationSummary::query()
                        ->where(
                            'transformation_capability_activation_id',
                            $activation->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (! $summary) {
                    $summary =
                        TransformationCapabilityEvaluationSummary::create([
                            'transformation_capability_activation_id' =>
                                $activation->id,

                            'status' =>
                                TransformationCapabilityEvaluationSummary::STATUS_PENDING,

                            'generation_version' =>
                                0,
                        ]);
                }

                $nextVersion =
                    ((int) $summary->generation_version)
                    + 1;

                $summary->forceFill([
                    'status' =>
                        TransformationCapabilityEvaluationSummary::STATUS_DRAFT_GENERATED,

                    'generated_payload' =>
                        $payload,

                    'generation_context' =>
                        $context,

                    'generation_version' =>
                        $nextVersion,

                    'generated_at' =>
                        now(),

                    /*
                     * Una regeneración invalida cualquier futura
                     * revisión de síntesis.
                     */
                    'reviewed_payload' =>
                        null,

                    'reviewed_by_user_id' =>
                        null,

                    'reviewed_at' =>
                        null,
                ])->save();

                AuditService::log(
                    'transformation_capability_evaluation_summary_generated',
                    $summary,
                    [
                        'company_id' =>
                            $activation->company_id,

                        'assessment_id' =>
                            $activation
                                ->diagnosis_assessment_id,

                        'activation_id' =>
                            $activation->id,

                        'capability_key' =>
                            $activation
                                ->capability_key,

                        'actor_user_id' =>
                            $actor->id,

                        'generation_version' =>
                            $nextVersion,

                        'generation_mode' =>
                            $context[
                                'generation_mode'
                            ]
                            ?? null,

                        'human_evaluations_only' =>
                            (bool) (
                                $context[
                                    'human_evaluations_only'
                                ]
                                ?? false
                            ),

                        'human_decision_changed' =>
                            false,

                        'activation_status_changed' =>
                            false,

                        'commercial_acceptance' =>
                            false,
                    ]
                );

                return $summary->fresh();
            },
            3
        );
    }

    public function assertGeneratedForActivation(
        TransformationCapabilityActivation $activation
    ): void {
        $summary =
            TransformationCapabilityEvaluationSummary::query()
                ->where(
                    'transformation_capability_activation_id',
                    $activation->id
                )
                ->first();

        if (
            ! $summary
            || ! $summary->hasGeneratedDraft()
        ) {
            throw ValidationException::withMessages([
                'summary' => [
                    'Genera la síntesis de la Evaluación de Branding antes de enviarla a revisión.',
                ],
            ]);
        }
    }

    public function review(
        TransformationCapabilityActivation $activation,
        User $actor,
        array $reviewed
    ): TransformationCapabilityEvaluationSummary {
        return DB::transaction(
            function () use (
                $activation,
                $actor,
                $reviewed
            ): TransformationCapabilityEvaluationSummary {
                $lockedActivation =
                    TransformationCapabilityActivation::query()
                        ->whereKey(
                            $activation->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedActivation->status
                    !== TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW
                ) {
                    throw ValidationException::withMessages([
                        'summary' => [
                            'La síntesis solo puede revisarse cuando la evaluación está lista para revisión.',
                        ],
                    ]);
                }

                $summary =
                    TransformationCapabilityEvaluationSummary::query()
                        ->where(
                            'transformation_capability_activation_id',
                            $lockedActivation->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    ! $summary
                    || ! $summary->hasGeneratedDraft()
                ) {
                    throw ValidationException::withMessages([
                        'summary' => [
                            'No existe una síntesis generada para revisar.',
                        ],
                    ]);
                }

                $generated =
                    is_array(
                        $summary->generated_payload
                    )
                        ? $summary->generated_payload
                        : [];

                $payload =
                    array_merge(
                        $generated,
                        [
                            'executive_summary' =>
                                trim(
                                    (string) (
                                        $reviewed[
                                            'executive_summary'
                                        ]
                                        ?? ''
                                    )
                                ),

                            'overall_recommendation' =>
                                isset(
                                    $reviewed[
                                        'overall_recommendation'
                                    ]
                                )
                                    ? trim(
                                        (string) $reviewed[
                                            'overall_recommendation'
                                        ]
                                    )
                                    : null,
                        ]
                    );

                $summary->forceFill([
                    'status' =>
                        TransformationCapabilityEvaluationSummary::STATUS_REVIEWED,

                    'reviewed_payload' =>
                        $payload,

                    'reviewed_by_user_id' =>
                        $actor->id,

                    'reviewed_at' =>
                        now(),
                ])->save();

                AuditService::log(
                    'transformation_capability_evaluation_summary_reviewed',
                    $summary,
                    [
                        'company_id' =>
                            $lockedActivation->company_id,

                        'assessment_id' =>
                            $lockedActivation
                                ->diagnosis_assessment_id,

                        'activation_id' =>
                            $lockedActivation->id,

                        'capability_key' =>
                            $lockedActivation
                                ->capability_key,

                        'actor_user_id' =>
                            $actor->id,

                        'generation_version' =>
                            (int) $summary
                                ->generation_version,

                        'summary_status' =>
                            TransformationCapabilityEvaluationSummary::STATUS_REVIEWED,

                        'human_review' =>
                            true,

                        'activation_status_changed' =>
                            false,

                        'commercial_acceptance' =>
                            false,
                    ]
                );

                return $summary->fresh([
                    'reviewedBy:id,name,email',
                ]);
            },
            3
        );
    }

    public function assertReviewedForActivation(
        TransformationCapabilityActivation $activation
    ): void {
        $summary =
            TransformationCapabilityEvaluationSummary::query()
                ->where(
                    'transformation_capability_activation_id',
                    $activation->id
                )
                ->first();

        if (
            ! $summary
            || ! $summary->hasReviewedSummary()
        ) {
            throw ValidationException::withMessages([
                'summary' => [
                    'La síntesis debe ser revisada y confirmada por LAUDA antes de validar la evaluación.',
                ],
            ]);
        }
    }

    public function generatedForActivation(
        TransformationCapabilityActivation $activation
    ): ?TransformationCapabilityEvaluationSummary {
        return TransformationCapabilityEvaluationSummary::query()
            ->where(
                'transformation_capability_activation_id',
                $activation->id
            )
            ->with(
                'reviewedBy:id,name,email'
            )
            ->first();
    }

    private function payload(
        array $generated
    ): array {
        return [
            'executive_summary' =>
                $generated[
                    'executive_summary'
                ]
                ?? null,

            'counts' =>
                is_array(
                    $generated[
                        'counts'
                    ]
                    ?? null
                )
                    ? $generated[
                        'counts'
                    ]
                    : [],

            'priority_order' =>
                is_array(
                    $generated[
                        'priority_order'
                    ]
                    ?? null
                )
                    ? array_values(
                        $generated[
                            'priority_order'
                        ]
                    )
                    : [],

            'dependencies' =>
                is_array(
                    $generated[
                        'dependencies'
                    ]
                    ?? null
                )
                    ? array_values(
                        $generated[
                            'dependencies'
                        ]
                    )
                    : [],

            'overall_recommendation' =>
                $generated[
                    'overall_recommendation'
                ]
                ?? null,

            'confirmed_areas' =>
                is_array(
                    $generated[
                        'confirmed_areas'
                    ]
                    ?? null
                )
                    ? array_values(
                        $generated[
                            'confirmed_areas'
                        ]
                    )
                    : [],
        ];
    }
}
