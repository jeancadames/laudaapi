<?php

namespace App\Services\Ecosystem;

use App\Models\TransformationCapabilityActivation;
use App\Models\TransformationImplementationPlan;

final class BrandingIdentityPlanContextService
{
    private const PUBLIC_PLAN_STATUSES = [
        TransformationImplementationPlan::STATUS_PRESENTED,
        TransformationImplementationPlan::STATUS_ACCEPTED,
        TransformationImplementationPlan::STATUS_ACTIVE,
        TransformationImplementationPlan::STATUS_COMPLETED,
    ];

    public function forActivation(
        TransformationCapabilityActivation $activation
    ): array {
        if (! $activation->diagnosis_assessment_id) {
            return $this->unavailable(
                'Esta activación fue seleccionada manualmente y no tiene un Plan consultivo de origen.'
            );
        }

        $plan =
            TransformationImplementationPlan::query()
                ->where(
                    'diagnosis_assessment_id',
                    $activation->diagnosis_assessment_id
                )
                ->whereIn(
                    'status',
                    self::PUBLIC_PLAN_STATUSES
                )
                ->whereNotNull(
                    'presented_at'
                )
                ->with([
                    'phases.capabilities',
                ])
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

        if (! $plan) {
            return $this->unavailable(
                'Todavía no existe un Plan consultivo público para este diagnóstico.'
            );
        }

        $phase = null;
        $capability = null;

        foreach ($plan->phases as $candidatePhase) {
            $candidateCapability =
                $candidatePhase
                    ->capabilities
                    ->first(
                        fn ($item): bool =>
                            (string) $item->capability_key
                            === 'branding_identity'
                    );

            if ($candidateCapability) {
                $phase = $candidatePhase;
                $capability = $candidateCapability;

                break;
            }
        }

        $planPayload = [
            'id' =>
                (int) $plan->id,

            'version' =>
                (int) $plan->version,

            'status' =>
                (string) $plan->status,

            'status_label' =>
                $this->planStatusLabel(
                    (string) $plan->status
                ),

            'presented_at' =>
                $plan->presented_at?->toISOString(),

            'url' =>
                route(
                    'diagnosis.implementation_plan.show',
                    $activation->diagnosis_assessment_id,
                    false
                ),
        ];

        if (! $phase || ! $capability) {
            return [
                'available' =>
                    false,

                'reason' =>
                    'El Plan consultivo público vigente no ubica Branding e Identidad Digital dentro de una fase.',

                'plan' =>
                    $planPayload,

                'phase' =>
                    null,

                'related_initiatives' =>
                    [],

                'priorities' =>
                    [],

                'dependencies' =>
                    [],

                'deliverables' =>
                    [],
            ];
        }

        $phaseSnapshot =
            is_array($phase->source_snapshot)
                ? $phase->source_snapshot
                : [];

        $capabilitySnapshot =
            is_array($capability->source_snapshot)
                ? $capability->source_snapshot
                : [];

        $linkedInitiativeKeys =
            collect(
                $capabilitySnapshot[
                    'linked_initiative_keys'
                ]
                ?? []
            )
                ->map(
                    fn ($value): string =>
                        trim((string) $value)
                )
                ->filter()
                ->unique()
                ->values();

        $relatedInitiatives =
            collect(
                $phaseSnapshot['initiatives']
                ?? []
            )
                ->filter(
                    fn ($initiative): bool =>
                        is_array($initiative)
                        && filled(
                            $initiative['id']
                            ?? null
                        )
                        && $linkedInitiativeKeys
                            ->contains(
                                (string) $initiative['id']
                            )
                )
                ->map(
                    function (
                        array $initiative
                    ): array {
                        $priority = trim(
                            (string) (
                                $initiative['priority']
                                ?? ''
                            )
                        );

                        return [
                            'id' =>
                                (string) $initiative['id'],

                            'title' =>
                                $initiative['title']
                                ?? null,

                            'objective' =>
                                $initiative['objective']
                                ?? null,

                            'owner_role' =>
                                $initiative['owner_role']
                                ?? null,

                            'priority' =>
                                $priority !== ''
                                    ? $priority
                                    : null,

                            'priority_label' =>
                                $priority !== ''
                                    ? $this->priorityLabel(
                                        $priority
                                    )
                                    : null,

                            'dependencies' =>
                                $this->stringList(
                                    $initiative[
                                        'dependencies'
                                    ]
                                    ?? []
                                ),
                        ];
                    }
                )
                ->values();

        $priorities =
            $relatedInitiatives
                ->filter(
                    fn (array $initiative): bool =>
                        filled(
                            $initiative['priority']
                            ?? null
                        )
                )
                ->map(
                    fn (array $initiative): array => [
                        'key' =>
                            $initiative['priority'],

                        'label' =>
                            $initiative[
                                'priority_label'
                            ],
                    ]
                )
                ->unique('key')
                ->values()
                ->all();

        $dependencies =
            $relatedInitiatives
                ->flatMap(
                    fn (array $initiative): array =>
                        $initiative[
                            'dependencies'
                        ]
                        ?? []
                )
                ->filter()
                ->unique()
                ->values()
                ->all();

        return [
            'available' =>
                true,

            'reason' =>
                null,

            'plan' =>
                $planPayload,

            'phase' => [
                'id' =>
                    (int) $phase->id,

                'sequence' =>
                    (int) $phase->sequence,

                'name' =>
                    (string) $phase->name,

                'objective' =>
                    $phase->objective,

                'horizon' =>
                    $phaseSnapshot[
                        'horizon'
                    ]
                    ?? null,
            ],

            'related_initiatives' =>
                $relatedInitiatives->all(),

            'priorities' =>
                $priorities,

            'dependencies' =>
                $dependencies,

            'deliverables' =>
                $this->stringList(
                    $capabilitySnapshot[
                        'includes'
                    ]
                    ?? []
                ),
        ];
    }

    private function unavailable(
        string $reason
    ): array {
        return [
            'available' =>
                false,

            'reason' =>
                $reason,

            'plan' =>
                null,

            'phase' =>
                null,

            'related_initiatives' =>
                [],

            'priorities' =>
                [],

            'dependencies' =>
                [],

            'deliverables' =>
                [],
        ];
    }

    private function stringList(
        mixed $value
    ): array {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(
                fn ($item): bool =>
                    is_string($item)
                    && trim($item) !== ''
            )
            ->map(
                fn (string $item): string =>
                    trim($item)
            )
            ->unique()
            ->values()
            ->all();
    }

    private function priorityLabel(
        string $priority
    ): string {
        return match (
            strtolower(
                trim($priority)
            )
        ) {
            'high' =>
                'Alta',

            'medium' =>
                'Media',

            'low' =>
                'Baja',

            default =>
                $priority,
        };
    }

    private function planStatusLabel(
        string $status
    ): string {
        return match ($status) {
            TransformationImplementationPlan::STATUS_PRESENTED =>
                'Presentado',

            TransformationImplementationPlan::STATUS_ACCEPTED =>
                'Aceptado',

            TransformationImplementationPlan::STATUS_ACTIVE =>
                'Activo',

            TransformationImplementationPlan::STATUS_COMPLETED =>
                'Completado',

            default =>
                $status,
        };
    }
}
