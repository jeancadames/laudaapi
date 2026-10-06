<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestDefinitionFunctionalClosureService
{
    private const AGREEMENT_EVENT =
        'definition_agreed_by_tenant';

    private const FUNCTIONAL_CLOSURE_EVENT =
        'definition_functionally_finalized_by_lauda';

    private const REQUIRED_CONFIRMATIONS = [
        'scope_confirmed',
        'deliverables_confirmed',
        'dependencies_confirmed',
        'inputs_validated',
        'accesses_validated',
        'responsibilities_confirmed',
    ];

    private const DATA_BI_CAPABILITY =
        'data_transformation_bi';

    private const DATA_BI_HUMAN_CONFIRMATIONS = [
        'scope_confirmed',
        'deliverables_confirmed',
        'dependencies_confirmed',
        'responsibilities_confirmed',
    ];

    public function __construct(
        private readonly TransformationImplementationDefinitionReviewService $definitionReviews,
        private readonly DataTransformationBiSourceReadinessService $sourceReadiness
    ) {
    }

    /**
     * Finaliza exclusivamente la Definition exacta que el tenant acordó.
     *
     * Fuente de verdad:
     *   RequestEvent.definition_agreed_by_tenant
     *
     * NO utiliza "latest Definition".
     *
     * Este paso:
     * - requiere Admin LAUDA;
     * - requiere Request = definition_agreed;
     * - resuelve la versión exacta acordada;
     * - reutiliza markReady() únicamente después de validar ese pin;
     * - mantiene Request = definition_agreed;
     * - NO cambia a ready_for_commercial;
     * - NO activa;
     * - NO inicia ejecución;
     * - NO crea suscripción ni artefactos comerciales.
     */
    public function finalize(
        TransformationImplementationRequest $request,
        User $actor
    ): TransformationImplementationDefinition {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $actor
            ): TransformationImplementationDefinition {
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertRequestState(
                    $lockedRequest
                );

                /*
                 * Hay una única evidencia específica de acuerdo.
                 *
                 * No usamos:
                 * - latest Definition;
                 * - max(version);
                 * - ID enviado por navegador.
                 */
                $agreementEvents =
                    TransformationImplementationRequestEvent::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $lockedRequest->id
                        )
                        ->where(
                            'event_type',
                            self::AGREEMENT_EVENT
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get();

                if ($agreementEvents->count() !== 1) {
                    throw ValidationException::withMessages([
                        'agreement' => [
                            'La solicitud debe tener una única evidencia exacta de Definition acordada por el tenant.',
                        ],
                    ]);
                }

                /** @var TransformationImplementationRequestEvent $agreementEvent */
                $agreementEvent =
                    $agreementEvents->first();

                $metadata =
                    is_array(
                        $agreementEvent->metadata
                    )
                        ? $agreementEvent->metadata
                        : [];

                $this->assertAgreementEvidence(
                    $lockedRequest,
                    $agreementEvent,
                    $metadata
                );

                $definitionId =
                    (int) (
                        $metadata[
                            'definition_id'
                        ]
                        ?? 0
                    );

                $definitionVersion =
                    (int) (
                        $metadata[
                            'definition_version'
                        ]
                        ?? 0
                    );

                if (
                    $definitionId <= 0
                    || $definitionVersion <= 0
                ) {
                    throw ValidationException::withMessages([
                        'agreement' => [
                            'La evidencia de acuerdo no identifica una Definition válida.',
                        ],
                    ]);
                }

                /*
                 * Exact Definition pinneada por el tenant.
                 *
                 * Deliberadamente NO orderByDesc(version).
                 */
                $lockedDefinition =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $definitionId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertExactAgreedDefinition(
                    $lockedRequest,
                    $agreementEvent,
                    $metadata,
                    $lockedDefinition,
                    $definitionVersion
                );

                /*
                 * Datos BI separa:
                 *
                 * - confirmaciones humanas funcionales;
                 * - readiness real de las fuentes.
                 *
                 * inputs_validated/accesses_validated son aliases legacy
                 * requeridos por el DefinitionReviewService genérico.
                 * Nunca se confía en valores enviados por navegador.
                 */
                $lockedDefinition =
                    $this->synchronizeDataBiSourceReadiness(
                        $lockedRequest,
                        $lockedDefinition,
                        $actor
                    );

                /*
                 * El servicio genérico ya contiene la semántica correcta
                 * para Definition functional-ready:
                 *
                 * under_review -> ready
                 * definition_ready=true
                 * technical_readiness=true
                 * ready_for_execution=false
                 * execution_started=false
                 * ready_at=now()
                 *
                 * El wrapper actual agrega el boundary request-scoped,
                 * autorización LAUDA y pinning de versión acordada.
                 */
                $readyDefinition =
                    $this->definitionReviews
                        ->markReady(
                            $lockedDefinition,
                            $actor
                        );

                $this->assertReadyResult(
                    $lockedRequest,
                    $lockedDefinition,
                    $readyDefinition
                );

                /*
                 * Revalidar Request después de markReady().
                 *
                 * markReady() no debe tocar el lifecycle del Request.
                 */
                $lockedRequest->refresh();

                if (
                    $lockedRequest->status
                    !== TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                    || $lockedRequest->ready_for_commercial_at !== null
                ) {
                    throw ValidationException::withMessages([
                        'request' => [
                            'El cierre funcional no puede cambiar automáticamente la solicitud a etapa comercial.',
                        ],
                    ]);
                }

                /*
                 * Historial request-scoped específico.
                 *
                 * Mismo status de Request:
                 * definition_agreed -> definition_agreed.
                 */
                TransformationImplementationRequestEvent::query()
                    ->create([
                        'transformation_implementation_request_id' =>
                            $lockedRequest->id,

                        'event_type' =>
                            self::FUNCTIONAL_CLOSURE_EVENT,

                        'from_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'to_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'actor_type' =>
                            TransformationImplementationRequestService::ACTOR_LAUDA_ADMIN,

                        'actor_user_id' =>
                            $actor->id,

                        'notes' =>
                            'Definition funcional V'
                            .(int) $readyDefinition->version
                            .' finalizada por Admin LAUDA.',

                        'metadata' => [
                            'request_id' =>
                                (int) $lockedRequest->id,

                            'agreement_event_id' =>
                                (int) $agreementEvent->id,

                            'definition_id' =>
                                (int) $readyDefinition->id,

                            'definition_version' =>
                                (int) $readyDefinition->version,

                            'company_id' =>
                                (int) $lockedRequest->company_id,

                            'phase_capability_id' =>
                                (int) $lockedRequest
                                    ->transformation_implementation_phase_capability_id,

                            'capability_key' =>
                                (string) $lockedRequest->capability_key,

                            'request_status' =>
                                TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                            'definition_status_from' =>
                                TransformationImplementationDefinition::STATUS_UNDER_REVIEW,

                            'definition_status_to' =>
                                TransformationImplementationDefinition::STATUS_READY,

                            'definition_ready' =>
                                true,

                            'technical_readiness' =>
                                true,

                            'ready_for_execution' =>
                                false,

                            'execution_started' =>
                                false,

                            'commercial_acceptance' =>
                                false,

                            'commercial_stage_started' =>
                                false,

                            'ready_for_commercial' =>
                                false,

                            'activation_started' =>
                                false,

                            'subscription_created' =>
                                false,

                            'actor_user_id' =>
                                (int) $actor->id,
                        ],

                        'occurred_at' =>
                            now(),
                    ]);

                /*
                 * Audit específico request-scoped.
                 *
                 * El markReady genérico mantiene su propio audit de
                 * Definition. Este segundo audit registra el contexto
                 * exacto del acuerdo del tenant.
                 */
                AuditService::log(
                    'transformation_implementation_definition_functionally_finalized_by_lauda',
                    $readyDefinition,
                    [
                        'request_id' =>
                            (int) $lockedRequest->id,

                        'agreement_event_id' =>
                            (int) $agreementEvent->id,

                        'definition_id' =>
                            (int) $readyDefinition->id,

                        'definition_version' =>
                            (int) $readyDefinition->version,

                        'company_id' =>
                            (int) $lockedRequest->company_id,

                        'phase_capability_id' =>
                            (int) $lockedRequest
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $lockedRequest->capability_key,

                        'request_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'definition_status' =>
                            TransformationImplementationDefinition::STATUS_READY,

                        'definition_ready' =>
                            true,

                        'technical_readiness' =>
                            true,

                        'ready_for_execution' =>
                            false,

                        'execution_started' =>
                            false,

                        'commercial_acceptance' =>
                            false,

                        'commercial_stage_started' =>
                            false,

                        'ready_for_commercial' =>
                            false,

                        'activation_started' =>
                            false,

                        'subscription_created' =>
                            false,

                        'actor_user_id' =>
                            (int) $actor->id,
                    ]
                );

                return $readyDefinition->fresh();
            },
            3
        );
    }

    private function assertLaudaAdmin(
        User $actor
    ): void {
        if (
            ($actor->role ?? null)
            !== 'admin'
        ) {
            throw new AuthorizationException(
                'El cierre funcional requiere un Admin LAUDA.'
            );
        }
    }

    private function assertRequestState(
        TransformationImplementationRequest $request
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud debe tener una Definition acordada por el tenant antes del cierre funcional.',
                ],
            ]);
        }

        if ($request->definition_agreed_at === null) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud no contiene la fecha de acuerdo funcional del tenant.',
                ],
            ]);
        }

        if ($request->ready_for_commercial_at !== null) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud ya avanzó fuera de la etapa de cierre funcional.',
                ],
            ]);
        }
    }

    private function assertAgreementEvidence(
        TransformationImplementationRequest $request,
        TransformationImplementationRequestEvent $event,
        array $metadata
    ): void {
        if (
            $event->event_type
                !== self::AGREEMENT_EVENT

            || $event->from_status
                !== TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW

            || $event->to_status
                !== TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED

            || $event->actor_type
                !== TransformationImplementationRequestService::ACTOR_TENANT_ADMIN

            || (int) (
                $metadata[
                    'request_id'
                ]
                ?? 0
            ) !== (int) $request->id

            || (int) (
                $metadata[
                    'company_id'
                ]
                ?? 0
            ) !== (int) $request->company_id

            || (int) (
                $metadata[
                    'phase_capability_id'
                ]
                ?? 0
            ) !== (int) $request
                ->transformation_implementation_phase_capability_id

            || trim(
                (string) (
                    $metadata[
                        'capability_key'
                    ]
                    ?? ''
                )
            ) !== trim(
                (string) $request->capability_key
            )

            || (
                $metadata[
                    'tenant_agreed'
                ]
                ?? null
            ) !== true
        ) {
            throw ValidationException::withMessages([
                'agreement' => [
                    'La evidencia de acuerdo del tenant no corresponde exactamente a esta solicitud.',
                ],
            ]);
        }
    }

    private function assertExactAgreedDefinition(
        TransformationImplementationRequest $request,
        TransformationImplementationRequestEvent $agreementEvent,
        array $metadata,
        TransformationImplementationDefinition $definition,
        int $definitionVersion
    ): void {
        if (
            (int) $definition->id
                !== (int) (
                    $metadata[
                        'definition_id'
                    ]
                    ?? 0
                )

            || (int) $definition->version
                !== $definitionVersion

            || (int) $definition
                ->transformation_implementation_request_id
                !== (int) $request->id

            || (int) $definition->company_id
                !== (int) $request->company_id

            || (int) $definition->diagnosis_assessment_id
                !== (int) $request->diagnosis_assessment_id

            || (int) $definition
                ->transformation_implementation_plan_id
                !== (int) $request
                    ->transformation_implementation_plan_id

            || (int) $definition
                ->transformation_implementation_phase_capability_id
                !== (int) $request
                    ->transformation_implementation_phase_capability_id

            || trim(
                (string) $definition->capability_key
            ) !== trim(
                (string) $request->capability_key
            )

            || data_get(
                $definition->source_snapshot,
                'source_type'
            ) !== 'implementation_request'

            || data_get(
                $definition->implementation_scope,
                'scope_mode'
            ) !== 'single_capability'

            || data_get(
                $definition->implementation_scope,
                'definition_scope_locked_to_request'
            ) !== true
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no coincide con la versión exacta acordada por el tenant.',
                ],
            ]);
        }

        if (
            $definition->status
                !== TransformationImplementationDefinition::STATUS_UNDER_REVIEW

            || data_get(
                $definition->readiness,
                'state'
            ) !== 'under_review'

            || data_get(
                $definition->readiness,
                'definition_ready'
            ) === true

            || $definition->ready_at !== null

            || data_get(
                $definition->responsibility_model,
                'party_assignment_status'
            ) !== 'confirmed'
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition acordada no está en condiciones de cierre funcional.',
                ],
            ]);
        }

        if (
            $definition->reviewed_by_user_id === null
            || $definition->reviewed_at === null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition acordada debe conservar evidencia de revisión humana de LAUDA.',
                ],
            ]);
        }

        foreach (
            $this->requiredConfirmations(
                $definition
            )
            as $confirmation
        ) {
            if (
                data_get(
                    $definition->readiness,
                    'human_validation.'
                    .$confirmation
                ) !== true
            ) {
                throw ValidationException::withMessages([
                    'definition' => [
                        'La Definition acordada no tiene completa la validación humana requerida.',
                    ],
                ]);
            }
        }

        /*
         * Defense in depth:
         * el actor que acordó debe ser el mismo actor pinneado
         * en la evidencia específica.
         */
        if (
            (int) (
                $metadata[
                    'actor_user_id'
                ]
                ?? 0
            ) !== (int) $agreementEvent->actor_user_id
        ) {
            throw ValidationException::withMessages([
                'agreement' => [
                    'La evidencia de actor del acuerdo no es consistente.',
                ],
            ]);
        }
    }

    /**
     * Human confirmations required by the functional agreement.
     *
     * Data BI deliberately excludes source-delivery readiness here:
     * that state is machine-owned and is resolved immediately before
     * markReady().
     *
     * @return array<int,string>
     */
    private function requiredConfirmations(
        TransformationImplementationDefinition $definition
    ): array {
        if (
            trim(
                (string) $definition->capability_key
            ) === self::DATA_BI_CAPABILITY
        ) {
            return self::DATA_BI_HUMAN_CONFIRMATIONS;
        }

        return self::REQUIRED_CONFIRMATIONS;
    }

    /**
     * Projects authoritative Data BI source readiness into the legacy
     * six-confirmation Definition readiness schema required by
     * TransformationImplementationDefinitionReviewService::markReady().
     *
     * No remote credentials or database access are validated here.
     */
    private function synchronizeDataBiSourceReadiness(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        User $actor
    ): TransformationImplementationDefinition {
        if (
            trim(
                (string) $definition->capability_key
            ) !== self::DATA_BI_CAPABILITY
        ) {
            return $definition;
        }

        $sourceReadiness =
            $this
                ->sourceReadiness
                ->forRequest(
                    $request
                );

        $sourceCount =
            (int) (
                $sourceReadiness[
                    'source_count'
                ]
                ?? 0
            );

        $completeSourceCount =
            (int) (
                $sourceReadiness[
                    'complete_source_count'
                ]
                ?? 0
            );

        if (
            ($sourceReadiness[
                'inputs_validated'
            ] ?? false) !== true
            || ($sourceReadiness[
                'accesses_validated'
            ] ?? false) !== true
            || $sourceCount <= 0
            || $completeSourceCount !== $sourceCount
        ) {
            throw ValidationException::withMessages([
                'source_readiness' => [
                    'Todas las fuentes activas de Datos BI deben tener un archivo CSV/XLSX válido antes del cierre funcional.',
                ],
            ]);
        }

        $readiness =
            is_array(
                $definition->readiness
            )
                ? $definition->readiness
                : [];

        $humanValidation =
            is_array(
                $readiness[
                    'human_validation'
                ] ?? null
            )
                ? $readiness[
                    'human_validation'
                ]
                : [];

        /*
         * Compatibility aliases only.
         *
         * These two values are machine-owned for Data BI even though
         * the historical generic schema stores them under
         * human_validation.
         */
        $humanValidation[
            'inputs_validated'
        ] = true;

        $humanValidation[
            'accesses_validated'
        ] = true;

        $readiness[
            'human_validation'
        ] = $humanValidation;

        $checks =
            is_array(
                $readiness[
                    'checks'
                ] ?? null
            )
                ? $readiness[
                    'checks'
                ]
                : [];

        $checks[
            'inputs_validated'
        ] = true;

        $checks[
            'accesses_validated'
        ] = true;

        $readiness[
            'checks'
        ] = $checks;

        $definition
            ->forceFill([
                'readiness' =>
                    $readiness,

                'updated_by_user_id' =>
                    $actor->id,
            ])
            ->save();

        return $definition->fresh()
            ?? $definition;
    }

    private function assertReadyResult(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $before,
        TransformationImplementationDefinition $after
    ): void {
        if (
            (int) $after->id
                !== (int) $before->id

            || (int) $after
                ->transformation_implementation_request_id
                !== (int) $request->id

            || $after->status
                !== TransformationImplementationDefinition::STATUS_READY

            || data_get(
                $after->readiness,
                'state'
            ) !== 'ready'

            || data_get(
                $after->readiness,
                'definition_ready'
            ) !== true

            || data_get(
                $after->readiness,
                'technical_readiness'
            ) !== true

            || data_get(
                $after->readiness,
                'ready_for_execution'
            ) !== false

            || data_get(
                $after->readiness,
                'execution_started'
            ) !== false

            || $after->ready_at === null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'El cierre funcional no produjo el estado ready esperado.',
                ],
            ]);
        }
    }
}
