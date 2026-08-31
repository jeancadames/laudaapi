<?php

namespace App\Services\Ecosystem;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
use App\Models\TransformationCapabilityNeedEvaluation;
use Illuminate\Validation\ValidationException;

final class BrandingIdentityWorkspaceService
{
    public function __construct(
        private readonly BrandingIdentityPlanContextService $planContext
    ) {
    }

    public function forActivation(
        TransformationCapabilityActivation $activation
    ): array {
        if (
            $activation->capability_key
            !== 'branding_identity'
            || $activation->status
                === TransformationCapabilityActivation::STATUS_CANCELLED
        ) {
            throw ValidationException::withMessages([
                'branding' => [
                    'La activación de Branding no está disponible para este workspace.',
                ],
            ]);
        }

        $snapshot = is_array($activation->source_snapshot)
            ? $activation->source_snapshot
            : [];

        $catalog = is_array($snapshot['catalog'] ?? null)
            ? $snapshot['catalog']
            : [];

        $roadmap = is_array($snapshot['roadmap'] ?? null)
            ? $snapshot['roadmap']
            : [];

        $freeContract =
            is_array($snapshot['free_activation_contract'] ?? null)
                ? $snapshot['free_activation_contract']
                : [];

        $status = (string) $activation->status;

        $assessmentId = $activation->diagnosis_assessment_id !== null
            ? (int) $activation->diagnosis_assessment_id
            : null;

        $sourceId = $activation->source_id !== null
            ? (int) $activation->source_id
            : null;

        $roadmapUrl = $assessmentId !== null
            && $activation->source_type
                === TransformationCapabilityActivation::SOURCE_DETAILED_ROADMAP
            ? route(
                'diagnosis.detailed_roadmap.show',
                $assessmentId,
                false
            )
            : null;

        return [
            'capability_key' =>
                'branding_identity',

            'title' =>
                (string) (
                    $catalog['title']
                    ?? 'Branding e Identidad Digital'
                ),

            'status' =>
                $status,

            'status_label' =>
                $this->statusLabel($status),

            'purpose' =>
                $catalog['purpose']
                ?? null,

            'scope' =>
                array_values(
                    array_filter(
                        $catalog['includes']
                        ?? [],
                        fn ($item) =>
                            is_string($item)
                            && trim($item) !== ''
                    )
                ),

            'needs' =>
                $this->needs(
                    $activation
                ),

            'final_result' =>
                $this->finalResult(
                    $activation
                ),

            'plan_context' =>
                $this->planContext->forActivation(
                    $activation
                ),

            'requires_lauda_review' =>
                (bool) (
                    $catalog['requires_lauda_review']
                    ?? true
                ),

            'recommendation' => [
                'recommended' =>
                    (bool) (
                        $roadmap['recommended']
                        ?? false
                    ),

                'basis' =>
                    $roadmap['recommendation_basis']
                    ?? null,
            ],

            'source' => [
                'assessment_id' =>
                    $assessmentId,

                'type' =>
                    $activation->source_type,

                'id' =>
                    $sourceId,

                'version' =>
                    $activation->source_version,

                'roadmap_url' =>
                    $roadmapUrl,
            ],

            'timestamps' => [
                'activated_at' =>
                    $activation->activated_at?->toISOString(),

                'started_at' =>
                    $activation->started_at?->toISOString(),

                'ready_for_review_at' =>
                    $activation->ready_for_review_at?->toISOString(),

                'validated_at' =>
                    $activation->validated_at?->toISOString(),

                'completed_at' =>
                    $activation->completed_at?->toISOString(),
            ],

            'progress' => [
                'steps' =>
                    $this->progressSteps(
                        $activation
                    ),

                'current_label' =>
                    $this->statusLabel($status),

                'next_step_label' =>
                    $this->nextStepLabel($status),
            ],

            'next_action' =>
                $status
                    === TransformationCapabilityActivation::STATUS_ACTIVATED
                        ? [
                            'key' =>
                                'start',

                            'label' =>
                                'Continuar evaluación',

                            'method' =>
                                'post',

                            'url' =>
                                route(
                                    'app.branding.start',
                                    [],
                                    false
                                ),

                            'description' =>
                                'Continúa con la evaluación de Branding e Identidad Digital. Cualquier trabajo posterior de diseño, desarrollo o implementación se definirá y cotizará por separado.',
                        ]
                        : null,

            'free_contract' => [
                'free' =>
                    (bool) (
                        $freeContract['free']
                        ?? true
                    ),

                'commercial_acceptance' =>
                    false,

                'requires_modality' =>
                    false,

                'requires_payment' =>
                    false,

                'creates_order' =>
                    false,

                'creates_invoice' =>
                    false,

                'creates_payment' =>
                    false,

                'creates_subscription' =>
                    false,

                'creates_subscription_item' =>
                    false,

                'creates_go_live' =>
                    false,
            ],
        ];
    }

    private function finalResult(
        TransformationCapabilityActivation $activation
    ): ?array {
        if (
            ! in_array(
                $activation->status,
                [
                    TransformationCapabilityActivation::STATUS_VALIDATED,
                    TransformationCapabilityActivation::STATUS_COMPLETED,
                ],
                true
            )
        ) {
            return null;
        }

        $summary =
            $activation
                ->evaluationSummary()
                ->first();

        if (
            ! $summary
            || ! $summary->hasReviewedSummary()
        ) {
            return null;
        }

        /*
         * El tenant recibe exclusivamente el contenido profesional
         * revisado. Nunca se expone generated_payload como resultado final.
         */
        $reviewed =
            is_array(
                $summary->reviewed_payload
            )
                ? $summary->reviewed_payload
                : [];

        $allNeeds =
            $activation
                ->needs()
                ->with('evaluation')
                ->get();

        $areas =
            $allNeeds
                ->map(
                    function (
                        TransformationCapabilityNeed $need
                    ): ?array {
                        $evaluation =
                            $need->evaluation;

                        if (
                            ! $evaluation
                            || $evaluation->status
                                !== TransformationCapabilityNeedEvaluation::STATUS_EVALUATED
                        ) {
                            return null;
                        }

                        $result =
                            (string) $evaluation->result;

                        $priority =
                            $evaluation->priority !== null
                                ? (string) $evaluation->priority
                                : null;

                        return [
                            'id' =>
                                (int) $need->id,

                            'sequence' =>
                                (int) $need->sequence,

                            'key' =>
                                (string) $need->need_key,

                            'title' =>
                                (string) $need->title,

                            'result' =>
                                $result,

                            'result_label' =>
                                $this->evaluationResultLabel(
                                    $result
                                ),

                            'findings' =>
                                $evaluation->findings,

                            'recommendation' =>
                                $evaluation->recommendation,

                            'priority' =>
                                $priority,

                            'priority_label' =>
                                $this->evaluationPriorityLabel(
                                    $priority
                                ),

                            'evaluated_at' =>
                                $evaluation
                                    ->evaluated_at
                                    ?->toISOString(),
                        ];
                    }
                )
                ->filter()
                ->values();

        return [
            'status' =>
                (string) $activation->status,

            'status_label' =>
                $activation->status
                    === TransformationCapabilityActivation::STATUS_COMPLETED
                        ? 'Evaluación completada'
                        : 'Evaluación validada',

            'validated_at' =>
                $activation
                    ->validated_at
                    ?->toISOString(),

            'completed_at' =>
                $activation
                    ->completed_at
                    ?->toISOString(),

            'reviewed_at' =>
                $summary
                    ->reviewed_at
                    ?->toISOString(),

            'counts' => [
                'total' =>
                    $allNeeds->count(),

                'evaluated' =>
                    $areas->count(),

                'requires_attention' =>
                    $areas
                        ->where(
                            'result',
                            'requires_attention'
                        )
                        ->count(),

                'adequate' =>
                    $areas
                        ->where(
                            'result',
                            'adequate'
                        )
                        ->count(),

                'not_applicable' =>
                    $areas
                        ->where(
                            'result',
                            'not_applicable'
                        )
                        ->count(),
            ],

            'executive_summary' =>
                $reviewed[
                    'executive_summary'
                ]
                ?? null,

            'overall_recommendation' =>
                $reviewed[
                    'overall_recommendation'
                ]
                ?? null,

            'priority_order' =>
                is_array(
                    $reviewed[
                        'priority_order'
                    ]
                    ?? null
                )
                    ? array_values(
                        $reviewed[
                            'priority_order'
                        ]
                    )
                    : [],

            'dependencies' =>
                is_array(
                    $reviewed[
                        'dependencies'
                    ]
                    ?? null
                )
                    ? array_values(
                        $reviewed[
                            'dependencies'
                        ]
                    )
                    : [],

            'areas' =>
                $areas->all(),

            'commercial_boundary' => [
                'evaluation_included' =>
                    true,

                'follow_up_requires_separate_quote' =>
                    true,

                'automatic_commercial_execution' =>
                    false,
            ],
        ];
    }

    private function evaluationResultLabel(
        string $result
    ): string {
        return match ($result) {
            'requires_attention' =>
                'Requiere atención',

            'adequate' =>
                'Adecuado / no requiere intervención',

            'not_applicable' =>
                'No aplica',

            default =>
                $result,
        };
    }

    private function evaluationPriorityLabel(
        ?string $priority
    ): ?string {
        return match ($priority) {
            'high' =>
                'Alta',

            'medium' =>
                'Media',

            'low' =>
                'Baja',

            default =>
                null,
        };
    }

    private function needs(
        TransformationCapabilityActivation $activation
    ): array {
        return $activation
            ->needs()
            ->get()
            ->map(
                fn (
                    TransformationCapabilityNeed $need
                ): array => [
                    'id' =>
                        (int) $need->id,

                    'sequence' =>
                        (int) $need->sequence,

                    'key' =>
                        (string) $need->need_key,

                    'title' =>
                        (string) $need->title,

                    'description' =>
                        $need->description,

                    'source_type' =>
                        (string) $need->source_type,

                    'status' =>
                        (string) $need->status,

                    'status_label' =>
                        $this->needStatusLabel(
                            (string) $need->status
                        ),

                    'identified_at' =>
                        $need->identified_at?->toISOString(),
                ]
            )
            ->values()
            ->all();
    }

    private function needStatusLabel(
        string $status
    ): string {
        return match ($status) {
            TransformationCapabilityNeed::STATUS_IDENTIFIED =>
                'Pendiente de evaluación',

            default =>
                $status,
        };
    }

    private function progressSteps(
        TransformationCapabilityActivation $activation
    ): array {
        $status = (string) $activation->status;

        $steps = [
            [
                'key' =>
                    TransformationCapabilityActivation::STATUS_ACTIVATED,
                'label' =>
                    'Activado',
                'at' =>
                    $activation->activated_at?->toISOString(),
            ],
            [
                'key' =>
                    TransformationCapabilityActivation::STATUS_IN_PROGRESS,
                'label' =>
                    'En progreso',
                'at' =>
                    $activation->started_at?->toISOString(),
            ],
            [
                'key' =>
                    TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW,
                'label' =>
                    'Listo para revisión',
                'at' =>
                    $activation->ready_for_review_at?->toISOString(),
            ],
            [
                'key' =>
                    TransformationCapabilityActivation::STATUS_VALIDATED,
                'label' =>
                    'Validado',
                'at' =>
                    $activation->validated_at?->toISOString(),
            ],
            [
                'key' =>
                    TransformationCapabilityActivation::STATUS_COMPLETED,
                'label' =>
                    'Completado',
                'at' =>
                    $activation->completed_at?->toISOString(),
            ],
        ];

        $order = array_flip(
            array_column(
                $steps,
                'key'
            )
        );

        $currentIndex =
            $order[$status]
            ?? 0;

        return array_map(
            function (
                array $step,
                int $index
            ) use (
                $status,
                $currentIndex
            ): array {
                $state = 'pending';

                if (
                    $status
                    === TransformationCapabilityActivation::STATUS_COMPLETED
                    || $index < $currentIndex
                ) {
                    $state = 'completed';
                } elseif ($index === $currentIndex) {
                    $state = 'current';
                }

                return array_merge(
                    $step,
                    ['state' => $state]
                );
            },
            $steps,
            array_keys($steps)
        );
    }

    private function statusLabel(
        string $status
    ): string {
        return match ($status) {
            TransformationCapabilityActivation::STATUS_ACTIVATED =>
                'Activado',

            TransformationCapabilityActivation::STATUS_IN_PROGRESS =>
                'En progreso',

            TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW =>
                'Listo para revisión',

            TransformationCapabilityActivation::STATUS_VALIDATED =>
                'Validado',

            TransformationCapabilityActivation::STATUS_COMPLETED =>
                'Completado',

            default =>
                $status,
        };
    }

    private function nextStepLabel(
        string $status
    ): ?string {
        return match ($status) {
            TransformationCapabilityActivation::STATUS_ACTIVATED =>
                'Iniciar la Evaluación de Branding e Identidad Digital.',

            TransformationCapabilityActivation::STATUS_IN_PROGRESS =>
                'Preparar el trabajo para revisión de LAUDA.',

            TransformationCapabilityActivation::STATUS_READY_FOR_REVIEW =>
                'Revisión de LAUDA pendiente.',

            TransformationCapabilityActivation::STATUS_VALIDATED =>
                'La evaluación fue validada por LAUDA y está pendiente de cierre administrativo.',

            TransformationCapabilityActivation::STATUS_COMPLETED =>
                null,

            default =>
                null,
        };
    }
}
