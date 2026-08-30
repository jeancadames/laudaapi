<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationPlanAutogenerator
{
    public function __construct(
        private readonly TransformationImplementationPhaseService $phases
    ) {
    }

    /**
     * Construye una vista previa determinística sin escribir en DB.
     *
     * @return array{
     *   source_type:string,
     *   phases:array<int,array<string,mixed>>,
     *   excluded_capabilities:array<int,array<string,mixed>>
     * }
     */
    public function preview(
        TransformationImplementationPlan $plan
    ): array {
        $source = $this->sourceSnapshot($plan);

        $roadmap = $this->roadmapFromSource($source);

        if ($roadmap === []) {
            throw ValidationException::withMessages([
                'source_snapshot' => [
                    'El Plan no contiene un Roadmap utilizable para generar su estructura.',
                ],
            ]);
        }

        $roadmapPhases = collect(
            $roadmap['phases'] ?? []
        );

        $initiatives = collect(
            $roadmap['initiatives'] ?? []
        )
            ->filter(
                fn ($item) =>
                    is_array($item)
                    && filled($item['id'] ?? null)
            )
            ->keyBy(
                fn (array $item): string =>
                    (string) $item['id']
            );

        if ($roadmapPhases->isEmpty()) {
            throw ValidationException::withMessages([
                'roadmap' => [
                    'El Roadmap no contiene fases para generar el Plan.',
                ],
            ]);
        }

        $capabilities = $this->recommendedCapabilities(
            $roadmap,
            $initiatives
        );

        $excluded = $capabilities
            ->filter(
                fn (array $item): bool =>
                    (bool) ($item['excluded'] ?? false)
            )
            ->values();

        $included = $capabilities
            ->reject(
                fn (array $item): bool =>
                    (bool) ($item['excluded'] ?? false)
            )
            ->values();

        $generated = [];

        foreach ($roadmapPhases as $phaseDefinition) {
            if (! is_array($phaseDefinition)) {
                continue;
            }

            $initiativeIds = collect(
                $phaseDefinition['initiative_ids']
                ?? []
            )
                ->map(
                    fn ($value): string =>
                        (string) $value
                )
                ->filter()
                ->values();

            $phaseInitiatives = $initiativeIds
                ->map(
                    fn (string $id) =>
                        $initiatives->get($id)
                )
                ->filter(
                    fn ($item) =>
                        is_array($item)
                )
                ->values();

            $phaseCapabilities = $included
                ->filter(
                    function (
                        array $capability
                    ) use ($initiativeIds): bool {
                        $linked = collect(
                            $capability[
                                'linked_initiative_keys'
                            ] ?? []
                        );

                        return $linked
                            ->intersect(
                                $initiativeIds
                            )
                            ->isNotEmpty();
                    }
                )
                ->values();

            /*
             * Un servicio profesional se asigna solo a su primera fase
             * aplicable. La existencia de una fase NO depende de que tenga
             * un servicio profesional adicional.
             */
            $alreadyAssigned = collect($generated)
                ->flatMap(
                    fn (array $item) =>
                        collect(
                            $item['capabilities']
                            ?? []
                        )->pluck('capability_key')
                )
                ->all();

            $phaseCapabilities = $phaseCapabilities
                ->reject(
                    fn (array $item): bool =>
                        in_array(
                            $item['capability_key'],
                            $alreadyAssigned,
                            true
                        )
                )
                ->values();

            $objective = $phaseInitiatives
                ->pluck('objective')
                ->filter()
                ->unique()
                ->take(3)
                ->implode(' ');

            $generated[] = [
                'sequence' =>
                    (int) (
                        $phaseDefinition['number']
                        ?? count($generated) + 1
                    ),

                'name' =>
                    (string) (
                        $phaseDefinition['title']
                        ?? (
                            'Fase '
                            .(count($generated) + 1)
                        )
                    ),

                'objective' =>
                    $objective !== ''
                        ? $objective
                        : null,

                'horizon' =>
                    $phaseDefinition['horizon']
                    ?? null,

                'initiative_ids' =>
                    $initiativeIds->all(),

                'initiatives' =>
                    $phaseInitiatives->all(),

                'dependencies' =>
                    $phaseInitiatives
                        ->flatMap(
                            fn (array $item) =>
                                $item['dependencies']
                                ?? []
                        )
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),

                'deliverables' =>
                    $phaseCapabilities
                        ->flatMap(
                            fn (array $item) =>
                                $item['includes']
                                ?? []
                        )
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),

                'capabilities' =>
                    $phaseCapabilities
                        ->values()
                        ->all(),
            ];
        }

        if ($generated === []) {
            throw ValidationException::withMessages([
                'capabilities' => [
                    'El Roadmap no produjo capacidades ejecutables bajo las reglas actuales de autogeneración.',
                ],
            ]);
        }

        return [
            'source_type' =>
                (string) (
                    $source['source_type']
                    ?? 'unknown'
                ),

            'phases' =>
                array_values($generated),

            'excluded_capabilities' =>
                $excluded->all(),
        ];
    }

    /**
     * Persiste estructura únicamente sobre un draft vacío.
     */
    public function generate(
        TransformationImplementationPlan $plan,
        ?int $userId = null
    ): TransformationImplementationPlan {
        return DB::transaction(function () use (
            $plan,
            $userId
        ) {
            $locked =
                TransformationImplementationPlan::query()
                    ->whereKey($plan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $locked->status
                !== TransformationImplementationPlan::STATUS_DRAFT
            ) {
                throw ValidationException::withMessages([
                    'plan' => [
                        'Solo un Plan en borrador puede autogenerarse.',
                    ],
                ]);
            }

            if ($locked->phases()->exists()) {
                return $locked->fresh([
                    'phases.capabilities',
                ]);
            }

            $preview = $this->preview($locked);

            foreach (
                $preview['phases']
                as $phaseData
            ) {
                $capabilities = collect(
                    $phaseData['capabilities']
                    ?? []
                )
                    ->values()
                    ->map(
                        function (
                            array $item,
                            int $index
                        ): array {
                            return [
                                'sequence' =>
                                    $index + 1,

                                'capability_key' =>
                                    $item[
                                        'capability_key'
                                    ],

                                'capability_label' =>
                                    $item['label'],

                                'capability_summary' =>
                                    $item['purpose']
                                    ?? null,

                                'source_snapshot' => [
                                    'autogenerated' =>
                                        true,

                                    'capability_key' =>
                                        $item[
                                            'capability_key'
                                        ],

                                    'capability_label' =>
                                        $item['label'],

                                    'kind' =>
                                        $item['kind']
                                        ?? null,

                                    'subscription_candidate' =>
                                        (bool) (
                                            $item[
                                                'subscription_candidate'
                                            ]
                                            ?? false
                                        ),

                                    'service_key' =>
                                        $item[
                                            'service_key'
                                        ]
                                        ?? null,

                                    'purpose' =>
                                        $item['purpose']
                                        ?? null,

                                    'includes' =>
                                        $item['includes']
                                        ?? [],

                                    'linked_initiative_keys' =>
                                        $item[
                                            'linked_initiative_keys'
                                        ]
                                        ?? [],

                                    'recommendation_basis' =>
                                        $item[
                                            'recommendation_basis'
                                        ]
                                        ?? null,
                                ],
                            ];
                        }
                    )
                    ->all();

                $phase = $this->phases
                    ->createPhaseFromRoadmap(
                        $locked,
                        [
                            'sequence' =>
                                $phaseData['sequence'],

                            'name' =>
                                $phaseData['name'],

                            'objective' =>
                                $phaseData['objective'],

                            'capabilities' =>
                                $capabilities,

                            'allow_empty_capabilities' =>
                                true,
                        ],
                        $userId
                    );

                $phase->forceFill([
                    'source_snapshot' => [
                        'autogenerated' =>
                            true,

                        'generator' =>
                            't360_plan_autogenerator_v1',

                        'source_type' =>
                            $preview['source_type'],

                        'horizon' =>
                            $phaseData['horizon']
                            ?? null,

                        'initiative_ids' =>
                            $phaseData[
                                'initiative_ids'
                            ]
                            ?? [],

                        'initiatives' =>
                            $phaseData[
                                'initiatives'
                            ]
                            ?? [],

                        'dependencies' =>
                            $phaseData[
                                'dependencies'
                            ]
                            ?? [],

                        'deliverables' =>
                            $phaseData[
                                'deliverables'
                            ]
                            ?? [],
                    ],
                ])->save();
            }

            $source = $this->sourceSnapshot(
                $locked
            );

            $source['autogeneration'] = [
                'generator' =>
                    't360_plan_autogenerator_v1',

                'generated_at' =>
                    now()->toISOString(),

                'phase_count' =>
                    count($preview['phases']),

                'excluded_capabilities' =>
                    $preview[
                        'excluded_capabilities'
                    ],
            ];

            $locked->forceFill([
                'source_snapshot' =>
                    $source,

                'updated_by_user_id' =>
                    $userId,
            ])->save();

            return $locked->fresh([
                'phases.capabilities',
            ]);
        }, 3);
    }

    /**
     * Reconstruye únicamente un draft que todavía no haya iniciado
     * facturación ni ejecución.
     *
     * Los estimates dependen de la estructura y se invalidan por cascade.
     * La modalidad seleccionada también se limpia.
     */
    public function regenerate(
        TransformationImplementationPlan $plan,
        ?int $userId = null
    ): TransformationImplementationPlan {
        return DB::transaction(function () use (
            $plan,
            $userId
        ) {
            $locked =
                TransformationImplementationPlan::query()
                    ->whereKey($plan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

            if (
                $locked->status
                !== TransformationImplementationPlan::STATUS_DRAFT
                || $locked->presented_at !== null
                || $locked->accepted_at !== null
            ) {
                throw ValidationException::withMessages([
                    'plan' => [
                        'Solo un Plan borrador nunca presentado puede regenerarse.',
                    ],
                ]);
            }

            $phaseIds =
                $locked->phases()
                    ->pluck('id');

            if ($phaseIds->isNotEmpty()) {
                if (
                    DB::table(
                        'transformation_implementation_milestones'
                    )
                        ->whereIn(
                            'transformation_implementation_phase_id',
                            $phaseIds
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'milestones' => [
                            'No se puede regenerar un Plan que ya contiene hitos de facturación.',
                        ],
                    ]);
                }

                if (
                    DB::table(
                        'transformation_implementation_phase_executions'
                    )
                        ->whereIn(
                            'transformation_implementation_phase_id',
                            $phaseIds
                        )
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'execution' => [
                            'No se puede regenerar un Plan cuya ejecución ya fue inicializada.',
                        ],
                    ]);
                }

                $capabilityIds =
                    DB::table(
                        'transformation_implementation_phase_capabilities'
                    )
                        ->whereIn(
                            'transformation_implementation_phase_id',
                            $phaseIds
                        )
                        ->pluck('id');

                if ($capabilityIds->isNotEmpty()) {
                    if (
                        DB::table(
                            'transformation_implementation_capability_executions'
                        )
                            ->whereIn(
                                'transformation_implementation_phase_capability_id',
                                $capabilityIds
                            )
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'execution' => [
                                'No se puede regenerar un Plan con capacidades en ejecución.',
                            ],
                        ]);
                    }

                    if (
                        DB::table(
                            'transformation_implementation_capability_go_lives'
                        )
                            ->whereIn(
                                'transformation_implementation_phase_capability_id',
                                $capabilityIds
                            )
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'go_live' => [
                                'No se puede regenerar un Plan que ya contiene Go-Live.',
                            ],
                        ]);
                    }
                }

                /*
                 * FK cascade elimina capabilities y estimates.
                 * Milestones / execution ya fueron bloqueados arriba.
                 */
                $locked->phases()->delete();
            }

            $locked->forceFill([
                'selected_modality' =>
                    null,

                'selected_modality_label' =>
                    null,

                'updated_by_user_id' =>
                    $userId,
            ])->save();

            return $this->generate(
                $locked,
                $userId
            );
        }, 3);
    }


    private function recommendedCapabilities(
        array $roadmap,
        Collection $initiatives
    ): Collection {
        /*
         * Transformación 360 implementa iniciativas, procesos y servicios
         * profesionales. Las soluciones SaaS permanecen únicamente como
         * recomendaciones del Diagnóstico/Roadmap y NO forman parte del Plan.
         */
        $result = collect();

        $transformation =
            $roadmap['transformation_capabilities']
            ?? [];

        $professional =
            TransformationProfessionalCapabilityCatalog::all();

        foreach (
            $professional
            as $key => $definition
        ) {
            $roadmapDefinition =
                is_array(
                    $transformation[$key]
                    ?? null
                )
                    ? $transformation[$key]
                    : [];

            $recommended = match ($key) {
                'procedures_guide' =>
                    (bool) (
                        $roadmapDefinition[
                            'recommended'
                        ]
                        ?? true
                    ),

                'branding_identity' =>
                    (bool) (
                        $roadmapDefinition[
                            'recommended'
                        ]
                        ?? false
                    ),

                default =>
                    (bool) (
                        $roadmapDefinition[
                            'recommended'
                        ]
                        ?? false
                    ),
            };

            if (! $recommended) {
                continue;
            }

            $result->push([
                'capability_key' =>
                    $definition[
                        'capability_key'
                    ]
                    ?? $key,

                'label' =>
                    $definition['title']
                    ?? $key,

                'kind' =>
                    'professional_service',

                'service_key' =>
                    null,

                'subscription_candidate' =>
                    false,

                'purpose' =>
                    $definition['purpose']
                    ?? null,

                'includes' =>
                    $definition['includes']
                    ?? [],

                'linked_initiative_keys' =>
                    $definition[
                        'linked_initiative_keys'
                    ]
                    ?? [],

                'recommendation_basis' =>
                    'professional_transformation_capability',

                'excluded' =>
                    false,
            ]);
        }

        return $result;
    }

    private function normalizeCapability(
        string $key,
        array $definition,
        string $basis
    ): array {
        $subscriptionCandidate = (bool) (
            $definition[
                'subscription_candidate'
            ] ?? false
        );

        return [
            'capability_key' =>
                $definition[
                    'capability_key'
                ]
                ?? $key,

            'label' =>
                $definition['title']
                ?? $definition['label']
                ?? $key,

            'kind' =>
                $definition['kind']
                ?? (
                    $subscriptionCandidate
                        ? 'subscription_service'
                        : 'professional_service'
                ),

            'subscription_candidate' =>
                $subscriptionCandidate,

            'service_key' =>
                $definition['service_key']
                ?? null,

            'purpose' =>
                $definition['purpose']
                ?? null,

            'includes' =>
                $definition['includes']
                ?? [],

            'linked_initiative_keys' =>
                $definition[
                    'linked_initiative_keys'
                ] ?? [],

            'requires_lauda_review' =>
                (bool) (
                    $definition[
                        'requires_lauda_review'
                    ]
                    ?? false
                ),

            'recommendation_basis' =>
                $basis,

            'excluded' =>
                false,
        ];
    }

    private function sourceSnapshot(
        TransformationImplementationPlan $plan
    ): array {
        $source = $plan->source_snapshot;

        if (is_array($source)) {
            return $source;
        }

        if (
            is_string($source)
            && trim($source) !== ''
        ) {
            $decoded = json_decode(
                $source,
                true
            );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return [];
    }

    private function roadmapFromSource(
        array $source
    ): array {
        $type = $source['source_type']
            ?? null;

        if (
            $type === 'published_roadmap'
            && is_array(
                $source[
                    'published_roadmap'
                ] ?? null
            )
        ) {
            return $source[
                'published_roadmap'
            ];
        }

        if (
            $type === 'internal_assessment'
            && is_array(
                $source[
                    'internal_roadmap'
                ] ?? null
            )
        ) {
            return $source[
                'internal_roadmap'
            ];
        }

        return [];
    }
}
