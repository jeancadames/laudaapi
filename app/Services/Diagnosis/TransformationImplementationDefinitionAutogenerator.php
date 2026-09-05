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

        /*
         * El flujo histórico conserva su preview Plan-wide.
         *
         * Una Definition nacida de ImplementationRequest tiene
         * identidad propia:
         *
         *   Request -> PhaseCapability -> capability_key
         *
         * y por contrato NO puede absorber las demás capabilities
         * del Plan.
         */
        if (
            $this->isRequestScopedDefinition(
                $definition,
                $source
            )
        ) {
            return $this->previewRequestScoped(
                $definition,
                $source
            );
        }

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
     * Indica si la Definition pertenece al nuevo lifecycle
     * ImplementationRequest -> una capability.
     */
    private function isRequestScopedDefinition(
        TransformationImplementationDefinition $definition,
        array $source
    ): bool {
        return
            $definition
                ->transformation_implementation_request_id
                !== null
            && $definition
                ->transformation_implementation_phase_capability_id
                !== null
            && trim(
                (string) $definition->capability_key
            ) !== ''
            && (
                $source[
                    'source_type'
                ] ?? null
            ) === 'implementation_request'
            && (
                $source[
                    'scope_mode'
                ] ?? null
            )
                === TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE;
    }

    /**
     * Prepara exclusivamente la capability solicitada.
     *
     * Regla crítica:
     *
     * source_snapshot.phases NO se consulta aquí.
     *
     * Aunque el Plan posea otras capabilities, ninguna puede
     * entrar en el alcance, entregables, dependencias o
     * responsabilidades de esta Definition.
     */
    private function previewRequestScoped(
        TransformationImplementationDefinition $definition,
        array $source
    ): array {
        $requestId =
            (int) $definition
                ->transformation_implementation_request_id;

        $phaseCapabilityId =
            (int) $definition
                ->transformation_implementation_phase_capability_id;

        $capabilityKey =
            trim(
                (string) $definition
                    ->capability_key
            );

        $initialScope =
            is_array(
                $definition
                    ->implementation_scope
            )
                ? $definition
                    ->implementation_scope
                : [];

        $sourceCapability =
            is_array(
                $source[
                    'capability'
                ] ?? null
            )
                ? $source[
                    'capability'
                ]
                : [];

        $sourcePhase =
            is_array(
                $source[
                    'phase'
                ] ?? null
            )
                ? $source[
                    'phase'
                ]
                : [];

        /*
         * ----------------------------------------------------------
         * Integridad request-scoped
         * ----------------------------------------------------------
         */

        if (
            $requestId <= 0
            || $phaseCapabilityId <= 0
            || $capabilityKey === ''
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definición request-scoped no tiene identidad completa.',
                ],
            ]);
        }

        if (
            (
                $initialScope[
                    'scope_mode'
                ] ?? null
            )
                !== TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE
            || (int) (
                $initialScope[
                    'request_id'
                ] ?? 0
            ) !== $requestId
            || (int) (
                $initialScope[
                    'phase_capability_id'
                ] ?? 0
            ) !== $phaseCapabilityId
            || trim(
                (string) (
                    $initialScope[
                        'capability_key'
                    ] ?? ''
                )
            ) !== $capabilityKey
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'El alcance inicial no coincide con la identidad de la solicitud.',
                ],
            ]);
        }

        if (
            (int) (
                $sourceCapability[
                    'id'
                ] ?? 0
            ) !== $phaseCapabilityId
            || trim(
                (string) (
                    $sourceCapability[
                        'capability_key'
                    ] ?? ''
                )
            ) !== $capabilityKey
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La capability del snapshot no coincide con la solicitud.',
                ],
            ]);
        }

        /*
         * ----------------------------------------------------------
         * Catálogo profesional
         * ----------------------------------------------------------
         */

        $catalog =
            TransformationProfessionalCapabilityCatalog::get(
                $capabilityKey
            );

        if (
            ! is_array(
                $catalog
            )
            || (
                $catalog[
                    'kind'
                ] ?? null
            ) !== 'professional_service'
            || (
                $catalog[
                    'activation_policy'
                ] ?? null
            ) !== 'implementation_only'
        ) {
            throw ValidationException::withMessages([
                'capability' => [
                    'La capability request-scoped no es una capability profesional implementation_only válida.',
                ],
            ]);
        }

        /*
         * ----------------------------------------------------------
         * Scope items
         *
         * Prioridad:
         * 1. catálogo profesional vigente;
         * 2. scope congelado al crear la Definition;
         * 3. snapshot exacto de la phase capability.
         * ----------------------------------------------------------
         */

        $scopeItems =
            $this->requestScopedStringList(
                $catalog[
                    'includes'
                ] ?? [],
                $initialScope[
                    'includes'
                ] ?? [],
                data_get(
                    $sourceCapability,
                    'source_snapshot.includes',
                    []
                )
            );

        /*
         * Entregables explícitos de capability, cuando existan.
         *
         * Si el catálogo no define un bloque "deliverables",
         * los "includes" constituyen la preparación funcional
         * inicial. Esto NO los convierte en hitos comerciales.
         */
        $deliverableStrings =
            $this->requestScopedStringList(
                $catalog[
                    'deliverables'
                ] ?? [],
                data_get(
                    $sourceCapability,
                    'source_snapshot.deliverables',
                    []
                )
            );

        if (
            $deliverableStrings === []
        ) {
            $deliverableStrings =
                $scopeItems;
        }

        /*
         * Solo dependencias específicas de la capability.
         *
         * Deliberadamente NO usamos dependencias generales
         * de source_snapshot.phases ni del resto del Plan.
         */
        $dependencyStrings =
            $this->requestScopedStringList(
                $catalog[
                    'dependencies'
                ] ?? [],
                $initialScope[
                    'dependencies'
                ] ?? [],
                data_get(
                    $sourceCapability,
                    'source_snapshot.dependencies',
                    []
                )
            );

        $phaseId =
            (int) (
                $initialScope[
                    'phase_id'
                ]
                ?? $sourcePhase[
                    'id'
                ]
                ?? 0
            );

        $phaseSequence =
            (int) (
                $initialScope[
                    'phase_sequence'
                ]
                ?? $sourcePhase[
                    'sequence'
                ]
                ?? 0
            );

        $phaseName =
            trim(
                (string) (
                    $initialScope[
                        'phase_name'
                    ]
                    ?? $sourcePhase[
                        'name'
                    ]
                    ?? ''
                )
            );

        $capabilityLabel =
            trim(
                (string) (
                    $sourceCapability[
                        'capability_label'
                    ]
                    ?? $initialScope[
                        'capability_label'
                    ]
                    ?? $catalog[
                        'title'
                    ]
                    ?? $capabilityKey
                )
            );

        $purpose =
            $catalog[
                'purpose'
            ]
            ?? $initialScope[
                'purpose'
            ]
            ?? $sourceCapability[
                'capability_summary'
            ]
            ?? null;

        /*
         * ----------------------------------------------------------
         * Única capability visible en el scope.
         * ----------------------------------------------------------
         */

        $scopeCapability = [
            'id' =>
                $phaseCapabilityId,

            'capability_key' =>
                $capabilityKey,

            'capability_label' =>
                $capabilityLabel,

            'kind' =>
                'professional_service',

            'activation_policy' =>
                'implementation_only',

            'purpose' =>
                $purpose,

            'scope_items' =>
                $scopeItems,

            'source' =>
                'implementation_request',
        ];

        $deliverables =
            collect(
                $deliverableStrings
            )
                ->values()
                ->map(
                    fn (
                        string $deliverable
                    ): array => [
                        'phase_id' =>
                            $phaseId,

                        'phase_sequence' =>
                            $phaseSequence,

                        'phase_name' =>
                            $phaseName,

                        'phase_capability_id' =>
                            $phaseCapabilityId,

                        'capability_key' =>
                            $capabilityKey,

                        'deliverable' =>
                            $deliverable,

                        'source' =>
                            'professional_capability',
                    ]
                )
                ->all();

        $dependencies =
            collect(
                $dependencyStrings
            )
                ->values()
                ->map(
                    fn (
                        string $dependency
                    ): array => [
                        'phase_id' =>
                            $phaseId,

                        'phase_sequence' =>
                            $phaseSequence,

                        'phase_name' =>
                            $phaseName,

                        'phase_capability_id' =>
                            $phaseCapabilityId,

                        'capability_key' =>
                            $capabilityKey,

                        'dependency' =>
                            $dependency,

                        'source' =>
                            'professional_capability',
                    ]
                )
                ->all();

        /*
         * No inferimos automáticamente LAUDA / cliente / shared.
         *
         * Cada elemento funcional queda pendiente de
         * confirmación humana en la revisión.
         */
        $suggestedOwnerRole =
            $catalog[
                'suggested_owner_role'
            ]
            ?? data_get(
                $sourceCapability,
                'source_snapshot.suggested_owner_role'
            );

        $assignments =
            collect(
                $deliverableStrings
            )
                ->values()
                ->map(
                    function (
                        string $deliverable,
                        int $index
                    ) use (
                        $phaseId,
                        $phaseSequence,
                        $phaseName,
                        $phaseCapabilityId,
                        $capabilityKey,
                        $suggestedOwnerRole
                    ): array {
                        return [
                            'phase_id' =>
                                $phaseId,

                            'phase_sequence' =>
                                $phaseSequence,

                            'phase_name' =>
                                $phaseName,

                            'phase_capability_id' =>
                                $phaseCapabilityId,

                            'capability_key' =>
                                $capabilityKey,

                            /*
                             * El ReviewService necesita una
                             * identidad funcional estable para
                             * cada asignación.
                             */
                            'initiative_id' =>
                                $capabilityKey
                                .':deliverable:'
                                .($index + 1),

                            'initiative_title' =>
                                $deliverable,

                            'suggested_owner_role' =>
                                is_string(
                                    $suggestedOwnerRole
                                )
                                    && trim(
                                        $suggestedOwnerRole
                                    ) !== ''
                                        ? trim(
                                            $suggestedOwnerRole
                                        )
                                        : null,

                            'responsible_party' =>
                                null,

                            'confirmation_status' =>
                                'pending',
                        ];
                    }
                )
                ->all();

        $unresolved =
            collect(
                $assignments
            )
                ->filter(
                    fn (
                        array $assignment
                    ): bool =>
                        (
                            $assignment[
                                'suggested_owner_role'
                            ] ?? null
                        ) === null
                )
                ->values()
                ->all();

        $scopeDefined =
            $scopeItems !== [];

        $deliverablesPrepared =
            $deliverables !== [];

        $responsibilitySuggestionsAvailable =
            collect(
                $assignments
            )
                ->contains(
                    fn (
                        array $assignment
                    ): bool =>
                        (
                            $assignment[
                                'suggested_owner_role'
                            ] ?? null
                        ) !== null
                );

        $blockers = [
            [
                'key' =>
                    'human_scope_review',

                'message' =>
                    'El alcance funcional debe ser confirmado por LAUDA.',

                'resolution' =>
                    'Revisar y confirmar el alcance de la capability solicitada.',
            ],

            [
                'key' =>
                    'inputs_accesses_validation',

                'message' =>
                    'Los insumos y accesos todavía no han sido validados.',

                'resolution' =>
                    'Validar los insumos y accesos necesarios para esta capability.',
            ],

            [
                'key' =>
                    'responsibility_confirmation',

                'message' =>
                    'Las responsabilidades todavía no han sido confirmadas.',

                'resolution' =>
                    'Asignar y confirmar LAUDA, cliente o responsabilidad compartida.',
            ],
        ];

        if (
            ! $scopeDefined
        ) {
            $blockers[] = [
                'key' =>
                    'scope_missing',

                'message' =>
                    'La capability solicitada no tiene alcance funcional suficiente.',

                'resolution' =>
                    'Completar el alcance funcional de la capability solicitada.',
            ];
        }

        if (
            ! $deliverablesPrepared
        ) {
            $blockers[] = [
                'key' =>
                    'deliverables_missing',

                'message' =>
                    'La capability solicitada no tiene entregables funcionales preparados.',

                'resolution' =>
                    'Definir los entregables técnicos o funcionales de esta capability.',
            ];
        }

        return [
            'implementation_scope' =>
                array_merge(
                    $initialScope,
                    [
                        'source' =>
                            'implementation_request',

                        'scope_mode' =>
                            TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                        'request_id' =>
                            $requestId,

                        'phase_capability_id' =>
                            $phaseCapabilityId,

                        'capability_key' =>
                            $capabilityKey,

                        'definition_scope_locked_to_request' =>
                            true,

                        /*
                         * Se conserva la estructura phases para
                         * compatibilidad con la revisión humana,
                         * pero solo contiene la fase/capability
                         * exactas de esta solicitud.
                         */
                        'phases' => [
                            [
                                'id' =>
                                    $phaseId,

                                'sequence' =>
                                    $phaseSequence,

                                'name' =>
                                    $phaseName,

                                'objective' =>
                                    $sourcePhase[
                                        'objective'
                                    ]
                                    ?? null,

                                'capabilities' => [
                                    $scopeCapability,
                                ],

                                'dependencies' =>
                                    $dependencyStrings,

                                'deliverables' =>
                                    $deliverableStrings,
                            ],
                        ],
                    ]
                ),

            'deliverables' =>
                $deliverables,

            'dependencies' =>
                $dependencies,

            'responsibility_model' => [
                'source' =>
                    'implementation_request_capability',

                'scope_mode' =>
                    TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                'capability_key' =>
                    $capabilityKey,

                'assignments' =>
                    $assignments,

                'unresolved' =>
                    $unresolved,

                'confirmation_required' =>
                    true,

                'party_assignment_status' =>
                    'to_be_defined',
            ],

            'readiness' => [
                'state' =>
                    'prepared_for_review',

                'definition_ready' =>
                    false,

                'technical_readiness' =>
                    false,

                'ready_for_execution' =>
                    false,

                'execution_started' =>
                    false,

                'commercial_stage_started' =>
                    false,

                'human_review_required' =>
                    true,

                'human_review_completed' =>
                    false,

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
                     * null = revisión todavía no realizada.
                     */
                    'inputs_validated' =>
                        null,

                    'accesses_validated' =>
                        null,

                    'responsibilities_confirmed' =>
                        null,
                ],

                'human_validation' => [
                    'scope_confirmed' =>
                        null,

                    'deliverables_confirmed' =>
                        null,

                    'dependencies_confirmed' =>
                        null,

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
     * Normaliza listas funcionales sin inferir información.
     */
    private function requestScopedStringList(
        mixed ...$sources
    ): array {
        return collect(
            $sources
        )
            ->flatMap(
                fn (
                    mixed $items
                ): array =>
                    is_array(
                        $items
                    )
                        ? $items
                        : []
            )
            ->filter(
                fn (
                    mixed $item
                ): bool =>
                    is_string(
                        $item
                    )
            )
            ->map(
                fn (
                    string $item
                ): string =>
                    trim(
                        $item
                    )
            )
            ->filter(
                fn (
                    string $item
                ): bool =>
                    $item !== ''
            )
            ->unique()
            ->values()
            ->all();
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
