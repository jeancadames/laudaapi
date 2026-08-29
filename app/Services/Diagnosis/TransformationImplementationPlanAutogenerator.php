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
             * Una capacidad se asigna solo a su primera fase aplicable.
             * Así evitamos duplicar CRM/Social/etc. en varias fases.
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

            if ($phaseCapabilities->isEmpty()) {
                continue;
            }

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

    private function recommendedCapabilities(
        array $roadmap,
        Collection $initiatives
    ): Collection {
        $result = collect();

        $transformation = $roadmap[
            'transformation_capabilities'
        ] ?? [];

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

                default => false,
            };

            if (! $recommended) {
                continue;
            }

            $result->push(
                $this->normalizeCapability(
                    (string) $key,
                    $definition,
                    'roadmap_professional_recommendation'
                )
            );
        }

        foreach (
            TransformationServiceCapabilityCatalog::all()
            as $key => $definition
        ) {
            $serviceKey = (string) (
                $definition['service_key']
                ?? ''
            );

            /*
             * LaudaOne ya no es una solución válida del ecosistema.
             * No se utiliza como destino de un Plan nuevo.
             */
            if (
                $serviceKey !== ''
                && str_starts_with(
                    $serviceKey,
                    'laudaone_'
                )
            ) {
                $item = $this->normalizeCapability(
                    (string) $key,
                    $definition,
                    'legacy_service_reference_blocked'
                );

                $item['excluded'] = true;
                $item['exclusion_reason'] =
                    'legacy_laudaone_service_key';

                $result->push($item);

                continue;
            }

            $linked = collect(
                $definition[
                    'linked_initiative_keys'
                ] ?? []
            );

            $matched = $linked
                ->map(
                    fn (string $initiativeKey) =>
                        $initiatives->get(
                            $initiativeKey
                        )
                )
                ->filter(
                    fn ($initiative) =>
                        is_array($initiative)
                );

            /*
             * Regla automática inicial:
             * una solución entra al Plan si alguna iniciativa
             * relacionada tiene prioridad critical/high.
             *
             * Las prioridades medium/sustain permanecen en
             * el Roadmap, pero no activan automáticamente
             * una solución comercial.
             */
            $recommended = $matched
                ->contains(
                    fn (array $initiative): bool =>
                        in_array(
                            $initiative[
                                'priority'
                            ] ?? null,
                            [
                                'critical',
                                'high',
                            ],
                            true
                        )
                );

            if (! $recommended) {
                continue;
            }

            $result->push(
                $this->normalizeCapability(
                    (string) $key,
                    $definition,
                    'critical_or_high_linked_initiative'
                )
            );
        }

        return $result
            ->unique('capability_key')
            ->values();
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
