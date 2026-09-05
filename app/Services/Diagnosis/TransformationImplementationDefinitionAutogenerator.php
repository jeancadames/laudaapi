<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationDefinitionAutogenerator
{
    /**
     * Construye una vista funcional sin escribir DB.
     */
    public function preview(
        TransformationImplementationDefinition $definition
    ): array {
        $source =
            $definition->source_snapshot
            ?? [];

        $sourcePhases =
            collect(
                $source['phases']
                ?? []
            )
                ->filter(
                    fn ($phase): bool =>
                        is_array($phase)
                )
                ->values();

        $scopePhases = [];

        $deliverables = [];
        $dependencies = [];
        $assignments = [];
        $unresolvedResponsibilities = [];

        foreach (
            $sourcePhases
            as $phase
        ) {
            $phaseId =
                (int) (
                    $phase['id']
                    ?? 0
                );

            $phaseSequence =
                (int) (
                    $phase['sequence']
                    ?? 0
                );

            $phaseName =
                trim(
                    (string) (
                        $phase['name']
                        ?? ''
                    )
                );

            $phaseSource =
                is_array(
                    $phase[
                        'source_snapshot'
                    ] ?? null
                )
                    ? $phase[
                        'source_snapshot'
                    ]
                    : [];

            $initiatives =
                collect(
                    $phaseSource[
                        'initiatives'
                    ] ?? []
                )
                    ->filter(
                        fn ($initiative): bool =>
                            is_array(
                                $initiative
                            )
                    )
                    ->map(
                        function (
                            array $initiative
                        ) use (
                            $phaseId,
                            $phaseSequence,
                            $phaseName,
                            &$assignments,
                            &$unresolvedResponsibilities
                        ): array {
                            $initiativeId =
                                trim(
                                    (string) (
                                        $initiative['id']
                                        ?? ''
                                    )
                                );

                            $title =
                                trim(
                                    (string) (
                                        $initiative['title']
                                        ?? $initiativeId
                                    )
                                );

                            $ownerRole =
                                trim(
                                    (string) (
                                        $initiative[
                                            'owner_role'
                                        ]
                                        ?? ''
                                    )
                                );

                            $normalized = [
                                'id' =>
                                    $initiativeId,

                                'title' =>
                                    $title,

                                'objective' =>
                                    $initiative[
                                        'objective'
                                    ] ?? null,

                                'priority' =>
                                    $initiative[
                                        'priority'
                                    ] ?? null,

                                'effort' =>
                                    $initiative[
                                        'effort'
                                    ] ?? null,

                                'actions' =>
                                    array_values(
                                        array_filter(
                                            $initiative[
                                                'actions'
                                            ] ?? [],
                                            'is_string'
                                        )
                                    ),

                                'dependencies' =>
                                    array_values(
                                        array_filter(
                                            $initiative[
                                                'dependencies'
                                            ] ?? [],
                                            'is_string'
                                        )
                                    ),

                                'success_metrics' =>
                                    array_values(
                                        array_filter(
                                            $initiative[
                                                'success_metrics'
                                            ] ?? [],
                                            'is_string'
                                        )
                                    ),

                                /*
                                 * Es una sugerencia heredada del Roadmap.
                                 * NO equivale a responsabilidad acordada.
                                 */
                                'suggested_owner_role' =>
                                    $ownerRole !== ''
                                        ? $ownerRole
                                        : null,
                            ];

                            $responsibility = [
                                'phase_id' =>
                                    $phaseId,

                                'phase_sequence' =>
                                    $phaseSequence,

                                'phase_name' =>
                                    $phaseName,

                                'initiative_id' =>
                                    $initiativeId,

                                'initiative_title' =>
                                    $title,

                                'suggested_owner_role' =>
                                    $ownerRole !== ''
                                        ? $ownerRole
                                        : null,

                                'confirmation_status' =>
                                    'pending',
                            ];

                            if ($ownerRole !== '') {
                                $assignments[] =
                                    $responsibility;
                            } else {
                                $unresolvedResponsibilities[] =
                                    $responsibility;
                            }

                            return $normalized;
                        }
                    )
                    ->values()
                    ->all();

            $capabilities =
                collect(
                    $phase[
                        'capabilities'
                    ] ?? []
                )
                    ->filter(
                        fn ($capability): bool =>
                            is_array(
                                $capability
                            )
                    )
                    ->map(
                        function (
                            array $capability
                        ): array {
                            $key =
                                trim(
                                    (string) (
                                        $capability[
                                            'capability_key'
                                        ] ?? ''
                                    )
                                );

                            $catalog =
                                $key !== ''
                                    ? TransformationProfessionalCapabilityCatalog::get(
                                        $key
                                    )
                                    : null;

                            return [
                                'capability_key' =>
                                    $key,

                                'capability_label' =>
                                    $capability[
                                        'capability_label'
                                    ]
                                    ?? $catalog[
                                        'title'
                                    ]
                                    ?? $key,

                                'kind' =>
                                    $catalog[
                                        'kind'
                                    ]
                                    ?? data_get(
                                        $capability,
                                        'source_snapshot.kind'
                                    ),

                                'purpose' =>
                                    $catalog[
                                        'purpose'
                                    ]
                                    ?? data_get(
                                        $capability,
                                        'source_snapshot.purpose'
                                    ),

                                /*
                                 * Incluye el alcance funcional del
                                 * catálogo, NO entregables comerciales.
                                 */
                                'scope_items' =>
                                    array_values(
                                        array_filter(
                                            $catalog[
                                                'includes'
                                            ]
                                            ?? data_get(
                                                $capability,
                                                'source_snapshot.includes',
                                                []
                                            ),
                                            'is_string'
                                        )
                                    ),

                                'linked_initiative_keys' =>
                                    array_values(
                                        array_filter(
                                            $catalog[
                                                'linked_initiative_keys'
                                            ]
                                            ?? data_get(
                                                $capability,
                                                'source_snapshot.linked_initiative_keys',
                                                []
                                            ),
                                            'is_string'
                                        )
                                    ),

                                'source_snapshot' =>
                                    $capability[
                                        'source_snapshot'
                                    ] ?? [],
                            ];
                        }
                    )
                    ->filter(
                        fn (array $capability): bool =>
                            $capability[
                                'capability_key'
                            ] !== ''
                    )
                    ->values()
                    ->all();

            $phaseDependencies =
                array_values(
                    array_unique(
                        array_filter(
                            $phaseSource[
                                'dependencies'
                            ] ?? [],
                            'is_string'
                        )
                    )
                );

            foreach (
                $phaseDependencies
                as $dependency
            ) {
                $dependencies[] = [
                    'phase_id' =>
                        $phaseId,

                    'phase_sequence' =>
                        $phaseSequence,

                    'phase_name' =>
                        $phaseName,

                    'dependency' =>
                        $dependency,

                    'source' =>
                        'plan_phase',
                ];
            }

            foreach (
                $initiatives
                as $initiative
            ) {
                foreach (
                    $initiative[
                        'dependencies'
                    ] ?? []
                    as $dependency
                ) {
                    $dependencies[] = [
                        'phase_id' =>
                            $phaseId,

                        'phase_sequence' =>
                            $phaseSequence,

                        'phase_name' =>
                            $phaseName,

                        'initiative_id' =>
                            $initiative['id'],

                        'dependency' =>
                            $dependency,

                        'source' =>
                            'roadmap_initiative',
                    ];
                }
            }

            $phaseDeliverables =
                array_values(
                    array_unique(
                        array_filter(
                            $phaseSource[
                                'deliverables'
                            ] ?? [],
                            'is_string'
                        )
                    )
                );

            foreach (
                $phaseDeliverables
                as $deliverable
            ) {
                $deliverables[] = [
                    'phase_id' =>
                        $phaseId,

                    'phase_sequence' =>
                        $phaseSequence,

                    'phase_name' =>
                        $phaseName,

                    'deliverable' =>
                        $deliverable,

                    'source' =>
                        'implementation_plan',
                ];
            }

            $scopePhases[] = [
                'id' =>
                    $phaseId,

                'sequence' =>
                    $phaseSequence,

                'name' =>
                    $phaseName,

                'objective' =>
                    $phase['objective']
                    ?? $phaseSource[
                        'objective'
                    ]
                    ?? null,

                'horizon' =>
                    $phase['horizon']
                    ?? $phaseSource[
                        'horizon'
                    ]
                    ?? null,

                'initiatives' =>
                    $initiatives,

                'capabilities' =>
                    $capabilities,

                'dependencies' =>
                    $phaseDependencies,

                'deliverables' =>
                    $phaseDeliverables,
            ];
        }

        $dependencies =
            collect(
                $dependencies
            )
                ->unique(
                    fn (array $item): string =>
                        implode(
                            '|',
                            [
                                $item[
                                    'phase_id'
                                ] ?? 0,
                                $item[
                                    'initiative_id'
                                ] ?? '',
                                $item[
                                    'dependency'
                                ] ?? '',
                                $item[
                                    'source'
                                ] ?? '',
                            ]
                        )
                )
                ->values()
                ->all();

        $deliverables =
            collect(
                $deliverables
            )
                ->unique(
                    fn (array $item): string =>
                        implode(
                            '|',
                            [
                                $item[
                                    'phase_id'
                                ] ?? 0,
                                $item[
                                    'deliverable'
                                ] ?? '',
                            ]
                        )
                )
                ->values()
                ->all();

        $scopeDefined =
            $scopePhases !== [];

        $deliverablesPrepared =
            $deliverables !== [];

        $responsibilitySuggestionsAvailable =
            $assignments !== [];

        /*
         * En esta etapa no tenemos evidencia suficiente
         * para afirmar disponibilidad de insumos/accesos.
         */
        $blockers = [
            [
                'key' =>
                    'human_scope_review',

                'message' =>
                    'Confirmar que el alcance preparado corresponde a lo que realmente se implementará.',

                'resolution' =>
                    'Revisión humana de la Definición de Implementación.',
            ],

            [
                'key' =>
                    'inputs_validation',

                'message' =>
                    'Validar insumos, fuentes, documentos y accesos requeridos antes de ejecutar.',

                'resolution' =>
                    'Registrar disponibilidad y responsables de cada insumo requerido.',
            ],

            [
                'key' =>
                    'responsibility_confirmation',

                'message' =>
                    'Confirmar responsables reales; los responsables heredados del Roadmap son solo sugeridos.',

                'resolution' =>
                    'Asignar y confirmar responsables durante la revisión de implementación.',
            ],
        ];

        if (! $scopeDefined) {
            $blockers[] = [
                'key' =>
                    'scope_missing',

                'message' =>
                    'No existe alcance suficiente para preparar la implementación.',

                'resolution' =>
                    'Completar las fases e iniciativas del Plan.',
            ];
        }

        if (! $deliverablesPrepared) {
            $blockers[] = [
                'key' =>
                    'deliverables_missing',

                'message' =>
                    'No existen entregables identificados en el Plan.',

                'resolution' =>
                    'Definir los entregables técnicos o funcionales de la implementación.',
            ];
        }

        if (
            $unresolvedResponsibilities
            !== []
        ) {
            $blockers[] = [
                'key' =>
                    'owner_suggestions_missing',

                'message' =>
                    'Existen iniciativas sin responsable sugerido.',

                'resolution' =>
                    'Definir responsable sugerido y posteriormente confirmar el responsable real.',
            ];
        }

        return [
            'implementation_scope' => [
                'source' =>
                    'presented_implementation_plan',

                'phases' =>
                    $scopePhases,
            ],

            'deliverables' =>
                $deliverables,

            'dependencies' =>
                $dependencies,

            'responsibility_model' => [
                'source' =>
                    'roadmap_owner_role_suggestions',

                'assignments' =>
                    $assignments,

                'unresolved' =>
                    $unresolvedResponsibilities,

                'confirmation_required' =>
                    true,

                /*
                 * No se infiere automáticamente
                 * LAUDA / cliente / compartido.
                 */
                'party_assignment_status' =>
                    'to_be_defined',
            ],

            'readiness' => [
                'state' =>
                    'prepared_for_review',

                'ready_for_execution' =>
                    false,

                'human_review_required' =>
                    true,

                'checks' => [
                    'scope_prepared' =>
                        $scopeDefined,

                    'deliverables_prepared' =>
                        $deliverablesPrepared,

                    'dependencies_extracted' =>
                        true,

                    'responsibility_suggestions_available' =>
                        $responsibilitySuggestionsAvailable,

                    /*
                     * null = todavía no evaluado.
                     * No equivale a false.
                     */
                    'inputs_validated' =>
                        null,

                    'accesses_validated' =>
                        null,

                    'responsibilities_confirmed' =>
                        null,
                ],

                'blockers' =>
                    $blockers,
            ],
        ];
    }

    /**
     * Persiste únicamente preparación funcional.
     */
    public function generate(
        TransformationImplementationDefinition $definition,
        ?int $userId = null
    ): TransformationImplementationDefinition {
        return DB::transaction(
            function () use (
                $definition,
                $userId
            ): TransformationImplementationDefinition {
                $locked =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $definition->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (! $locked->isEditable()) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'Solo una Definición editable puede autogenerarse.',
                        ],
                    ]);
                }

                $generated =
                    $this->preview(
                        $locked
                    );

                $locked->forceFill([
                    'implementation_scope' =>
                        $generated[
                            'implementation_scope'
                        ],

                    'deliverables' =>
                        $generated[
                            'deliverables'
                        ],

                    'dependencies' =>
                        $generated[
                            'dependencies'
                        ],

                    'responsibility_model' =>
                        $generated[
                            'responsibility_model'
                        ],

                    'readiness' =>
                        $generated[
                            'readiness'
                        ],

                    'updated_by_user_id' =>
                        $userId,
                ])->save();

                AuditService::log(
                    'transformation_implementation_definition_autogenerated',
                    $locked,
                    [
                        'definition_id' =>
                            $locked->id,

                        'definition_version' =>
                            $locked->version,

                        'plan_id' =>
                            $locked
                                ->transformation_implementation_plan_id,

                        'phase_count' =>
                            count(
                                data_get(
                                    $generated,
                                    'implementation_scope.phases',
                                    []
                                )
                            ),

                        'deliverable_count' =>
                            count(
                                $generated[
                                    'deliverables'
                                ]
                            ),

                        'dependency_count' =>
                            count(
                                $generated[
                                    'dependencies'
                                ]
                            ),

                        'ready_for_execution' =>
                            false,

                        'commercial_context_attached' =>
                            false,

                        'pricing_attached' =>
                            false,

                        'billing_created' =>
                            false,

                        'subscription_created' =>
                            false,

                        'actor_user_id' =>
                            $userId,
                    ]
                );

                return $locked->fresh();
            },
            3
        );
    }
}
