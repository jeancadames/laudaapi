<?php

namespace App\Services\Ecosystem;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationCapabilityNeed;
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
                'Cerrar la capacidad cuando los entregables estén concluidos.',

            TransformationCapabilityActivation::STATUS_COMPLETED =>
                null,

            default =>
                null,
        };
    }
}
