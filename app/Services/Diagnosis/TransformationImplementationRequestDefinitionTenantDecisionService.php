<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Subscribers\CompanyContextResolver;
use App\Services\Subscribers\SubscriberResolver;
use App\Services\Subscribers\TenantAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestDefinitionTenantDecisionService
{
    public function __construct(
        private readonly TransformationImplementationRequestService $requests,
        private readonly SubscriberResolver $subscriberResolver,
        private readonly CompanyContextResolver $companyResolver,
        private readonly TenantAccessService $tenantAccessService
    ) {
    }

    /**
     * El Tenant Admin solicita cambios sobre la versión exacta
     * de Definition que LAUDA presentó a SU Company.
     *
     * Único cambio de lifecycle:
     *
     * awaiting_tenant_review -> changes_requested
     *
     * La Definition presentada:
     *
     * - permanece under_review;
     * - no se modifica;
     * - no se marca ready;
     * - no genera V2 en este paso.
     */
    public function requestChanges(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        User $actor,
        string $reason
    ): TransformationImplementationRequest {
        $normalizedReason =
            trim(
                $reason
            );

        $this->assertReason(
            $normalizedReason
        );

        return DB::transaction(
            function () use (
                $request,
                $definition,
                $actor,
                $normalizedReason
            ): TransformationImplementationRequest {
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $lockedDefinition =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $definition->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Defense in depth.
                 *
                 * No dependemos únicamente del controller HTTP.
                 * Una invocación directa del servicio también debe
                 * demostrar que el actor es Tenant Admin de la
                 * misma Company propietaria del Request.
                 */
                $this->assertTenantActor(
                    $lockedRequest,
                    $actor
                );

                $this->assertContext(
                    $lockedRequest,
                    $lockedDefinition
                );

                /*
                 * RequestService conserva:
                 *
                 * - matriz contractual de transición;
                 * - changes_requested_at;
                 * - evento status_transition;
                 * - notes con la razón exacta;
                 * - audit genérico de transición.
                 */
                $transitioned =
                    $this->requests
                        ->transitionByTenant(
                            $lockedRequest,
                            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
                            $actor,
                            $normalizedReason
                        );

                /*
                 * Audit específico que fija cuál versión
                 * exacta de Definition fue objetada.
                 *
                 * No modifica Definition.
                 */
                AuditService::log(
                    'transformation_implementation_definition_changes_requested_by_tenant',
                    $lockedDefinition,
                    [
                        'request_id' =>
                            $lockedRequest->id,

                        'definition_id' =>
                            $lockedDefinition->id,

                        'definition_version' =>
                            $lockedDefinition->version,

                        'definition_status' =>
                            $lockedDefinition->status,

                        'company_id' =>
                            $lockedRequest->company_id,

                        'phase_capability_id' =>
                            $lockedDefinition
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            $lockedDefinition->capability_key,

                        'request_from_status' =>
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,

                        'request_to_status' =>
                            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,

                        'tenant_reason' =>
                            $normalizedReason,

                        'definition_modified' =>
                            false,

                        'new_definition_version_created' =>
                            false,

                        'definition_ready' =>
                            false,

                        'commercial_stage_started' =>
                            false,

                        'execution_started' =>
                            false,

                        'actor_user_id' =>
                            $actor->id,
                    ]
                );

                return $transitioned->fresh([
                    'events',
                ]);
            },
            3
        );
    }

    /**
     * El Tenant Admin acuerda explícitamente la versión exacta de
     * Definition que LAUDA le presentó.
     *
     * Único cambio de lifecycle:
     *
     * awaiting_tenant_review -> definition_agreed
     *
     * Este acuerdo:
     *
     * - NO modifica la Definition;
     * - NO ejecuta markReady();
     * - NO inicia etapa comercial;
     * - NO activa el servicio;
     * - NO inicia ejecución;
     * - NO crea suscripción.
     */
    public function agree(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        User $actor
    ): TransformationImplementationRequest {
        return DB::transaction(
            function () use (
                $request,
                $definition,
                $actor
            ): TransformationImplementationRequest {
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $lockedDefinition =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $definition->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Defense in depth:
                 * una llamada directa al dominio tampoco puede saltarse
                 * pertenencia de tenant / Company.
                 */
                $this->assertTenantActor(
                    $lockedRequest,
                    $actor
                );

                /*
                 * El acuerdo tiene un contrato propio porque además de
                 * validar la Definition presentada exige revisión humana
                 * completa antes de que el tenant pueda acordarla.
                 */
                $this->assertAgreementContext(
                    $lockedRequest,
                    $lockedDefinition
                );

                $agreementNote =
                    'Definition V'
                    .(int) $lockedDefinition->version
                    .' acordada explícitamente por el Tenant Admin.';

                /*
                 * RequestService conserva:
                 *
                 * - matriz contractual de transición;
                 * - definition_agreed_at;
                 * - status_changed_by_user_id;
                 * - status_transition;
                 * - audit genérico.
                 */
                $transitioned =
                    $this->requests
                        ->transitionByTenant(
                            $lockedRequest,
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
                            $actor,
                            $agreementNote
                        );

                /*
                 * Evidencia específica de versión.
                 *
                 * El status_transition genérico demuestra que hubo una
                 * transición, pero no basta para identificar cuál
                 * Definition histórica fue aceptada.
                 */
                TransformationImplementationRequestEvent::query()
                    ->create([
                        'transformation_implementation_request_id' =>
                            $lockedRequest->id,

                        'event_type' =>
                            'definition_agreed_by_tenant',

                        'from_status' =>
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,

                        'to_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'actor_type' =>
                            'tenant_admin',

                        'actor_user_id' =>
                            $actor->id,

                        'notes' =>
                            $agreementNote,

                        'metadata' => [
                            'request_id' =>
                                (int) $lockedRequest->id,

                            'definition_id' =>
                                (int) $lockedDefinition->id,

                            'definition_version' =>
                                (int) $lockedDefinition->version,

                            'definition_status' =>
                                (string) $lockedDefinition->status,

                            'company_id' =>
                                (int) $lockedRequest->company_id,

                            'phase_capability_id' =>
                                (int) $lockedDefinition
                                    ->transformation_implementation_phase_capability_id,

                            'capability_key' =>
                                (string) $lockedDefinition->capability_key,

                            'request_from_status' =>
                                TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,

                            'request_to_status' =>
                                TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                            'tenant_agreed' =>
                                true,

                            'definition_modified' =>
                                false,

                            'new_definition_version_created' =>
                                false,

                            'definition_ready' =>
                                false,

                            'mark_ready_used' =>
                                false,

                            'commercial_acceptance' =>
                                false,

                            'commercial_stage_started' =>
                                false,

                            'ready_for_commercial' =>
                                false,

                            'activation_started' =>
                                false,

                            'execution_started' =>
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
                 * Audit específico de acuerdo.
                 *
                 * Acordar la Definition funcional NO equivale a aceptar
                 * condiciones comerciales.
                 */
                AuditService::log(
                    'transformation_implementation_definition_agreed_by_tenant',
                    $lockedDefinition,
                    [
                        'request_id' =>
                            (int) $lockedRequest->id,

                        'definition_id' =>
                            (int) $lockedDefinition->id,

                        'definition_version' =>
                            (int) $lockedDefinition->version,

                        'definition_status' =>
                            (string) $lockedDefinition->status,

                        'company_id' =>
                            (int) $lockedRequest->company_id,

                        'phase_capability_id' =>
                            (int) $lockedDefinition
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $lockedDefinition->capability_key,

                        'request_from_status' =>
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,

                        'request_to_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'tenant_agreed' =>
                            true,

                        'definition_modified' =>
                            false,

                        'new_definition_version_created' =>
                            false,

                        'definition_ready' =>
                            false,

                        'mark_ready_used' =>
                            false,

                        'commercial_acceptance' =>
                            false,

                        'commercial_stage_started' =>
                            false,

                        'ready_for_commercial' =>
                            false,

                        'activation_started' =>
                            false,

                        'execution_started' =>
                            false,

                        'subscription_created' =>
                            false,

                        'actor_user_id' =>
                            (int) $actor->id,
                    ]
                );

                return $transitioned->fresh([
                    'events',
                ]);
            },
            3
        );
    }


    private function assertTenantActor(
        TransformationImplementationRequest $request,
        User $actor
    ): void {
        if (
            ($actor->role ?? null)
            !== 'subscriber'
        ) {
            throw new AuthorizationException(
                'La acción requiere un Tenant Admin.'
            );
        }

        $subscriberId =
            (int) (
                $this->subscriberResolver
                    ->resolve(
                        $actor
                    )
                ?? 0
            );

        if ($subscriberId <= 0) {
            throw new AuthorizationException(
                'No se pudo resolver el tenant del usuario.'
            );
        }

        $tenantAccess =
            $this->tenantAccessService
                ->resolve(
                    $actor,
                    $subscriberId
                );

        if (
            ($tenantAccess['mode'] ?? null)
                !== TenantAccessService::SUBSCRIBER_ADMIN

            || ! (bool) (
                $tenantAccess['tenant_admin']
                ?? false
            )
        ) {
            throw new AuthorizationException(
                'La acción requiere permisos de administrador de la empresa.'
            );
        }

        $company =
            $this->companyResolver
                ->resolve(
                    $actor,
                    $subscriberId
                );

        if (
            ! $company

            || (int) $company->id
                !== (int) $request->company_id
        ) {
            throw new AuthorizationException(
                'La solicitud no pertenece a la empresa del usuario.'
            );
        }
    }

    /**
     * Contrato exacto para acuerdo de la Definition presentada.
     */
    private function assertAgreementContext(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'Solo una Definition actualmente presentada a la empresa puede ser acordada.',
                ],
            ]);
        }

        if (
            $request->tenant_review_requested_at
            === null
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La Definition aún no ha sido enviada formalmente a revisión de la empresa.',
                ],
            ]);
        }

        /*
         * Contexto exacto Request -> Definition.
         */
        if (
            (int) $definition
                ->transformation_implementation_request_id
                !== (int) $request->id

            || (int) $definition
                ->transformation_implementation_phase_capability_id
                !== (int) $request
                    ->transformation_implementation_phase_capability_id

            || (int) $definition
                ->transformation_implementation_plan_id
                !== (int) $request
                    ->transformation_implementation_plan_id

            || (int) $definition
                ->diagnosis_assessment_id
                !== (int) $request
                    ->diagnosis_assessment_id

            || (int) $definition->company_id
                !== (int) $request->company_id

            || trim(
                (string) $definition->capability_key
            ) !== trim(
                (string) $request->capability_key
            )
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no pertenece exactamente a esta solicitud.',
                ],
            ]);
        }

        /*
         * Siempre resolver latest por servidor.
         *
         * Una V1 histórica nunca puede acordarse cuando ya existe V2.
         */
        $latestDefinitionId =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $request->id
                )
                ->where(
                    'company_id',
                    $request->company_id
                )
                ->where(
                    'diagnosis_assessment_id',
                    $request->diagnosis_assessment_id
                )
                ->where(
                    'transformation_implementation_plan_id',
                    $request
                        ->transformation_implementation_plan_id
                )
                ->where(
                    'transformation_implementation_phase_capability_id',
                    $request
                        ->transformation_implementation_phase_capability_id
                )
                ->where(
                    'capability_key',
                    $request->capability_key
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->lockForUpdate()
                ->value(
                    'id'
                );

        if (
            (int) $latestDefinitionId
            !== (int) $definition->id
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo puede acordarse la versión más reciente presentada.',
                ],
            ]);
        }

        /*
         * Definition request-scoped single-capability.
         */
        if (
            data_get(
                $definition->source_snapshot,
                'source_type'
            ) !== 'implementation_request'

            || data_get(
                $definition->implementation_scope,
                'scope_mode'
            ) !== TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE

            || data_get(
                $definition->implementation_scope,
                'definition_scope_locked_to_request'
            ) !== true

            || data_get(
                $definition->implementation_scope,
                'capability_key'
            ) !== $request->capability_key
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no cumple el contrato request-scoped single-capability.',
                ],
            ]);
        }

        /*
         * El tenant acuerda exactamente la Definition que LAUDA revisó.
         *
         * El acuerdo todavía NO la convierte en ready.
         */
        if (
            $definition->status
            !== TransformationImplementationDefinition::STATUS_UNDER_REVIEW
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo una Definition revisada por LAUDA puede ser acordada.',
                ],
            ]);
        }

        if (
            data_get(
                $definition->readiness,
                'state'
            ) !== 'under_review'

            || data_get(
                $definition->readiness,
                'definition_ready'
            ) === true

            || $definition->ready_at !== null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition presentada no cumple el estado previo requerido para el acuerdo.',
                ],
            ]);
        }

        /*
         * Las seis confirmaciones humanas de LAUDA deben existir.
         */
        foreach ([
            'scope_confirmed',
            'deliverables_confirmed',
            'dependencies_confirmed',
            'inputs_validated',
            'accesses_validated',
            'responsibilities_confirmed',
        ] as $confirmation) {
            if (
                data_get(
                    $definition->readiness,
                    'human_validation.'
                    .$confirmation
                ) !== true
            ) {
                throw ValidationException::withMessages([
                    'definition' => [
                        'La revisión humana de LAUDA debe estar completa antes del acuerdo.',
                    ],
                ]);
            }
        }

        /*
         * Responsabilidades ya confirmadas durante human review.
         */
        if (
            data_get(
                $definition->responsibility_model,
                'party_assignment_status'
            ) !== 'confirmed'

            || data_get(
                $definition->responsibility_model,
                'confirmation_required'
            ) === true

            || ! empty(
                data_get(
                    $definition->responsibility_model,
                    'unresolved',
                    []
                )
            )
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Las responsabilidades deben estar confirmadas antes del acuerdo.',
                ],
            ]);
        }
    }


    private function assertReason(
        string $reason
    ): void {
        $length =
            mb_strlen(
                $reason
            );

        if (
            $length < 10
            || $length > 4000
        ) {
            throw ValidationException::withMessages([
                'reason' => [
                    'Explica los cambios solicitados en al menos 10 caracteres y hasta un máximo de 4000.',
                ],
            ]);
        }
    }

    private function assertContext(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'Solo una Definition actualmente en revisión de la empresa puede recibir una solicitud de cambios.',
                ],
            ]);
        }

        if (
            $request->tenant_review_requested_at
            === null
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La Definition aún no ha sido enviada formalmente a revisión de la empresa.',
                ],
            ]);
        }

        if (
            (int) $definition
                ->transformation_implementation_request_id
                !== (int) $request->id

            || (int) $definition
                ->transformation_implementation_phase_capability_id
                !== (int) $request
                    ->transformation_implementation_phase_capability_id

            || (int) $definition
                ->transformation_implementation_plan_id
                !== (int) $request
                    ->transformation_implementation_plan_id

            || (int) $definition
                ->diagnosis_assessment_id
                !== (int) $request
                    ->diagnosis_assessment_id

            || (int) $definition->company_id
                !== (int) $request->company_id

            || trim(
                (string) $definition->capability_key
            )
                !== trim(
                    (string) $request->capability_key
                )
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no pertenece exactamente a esta solicitud.',
                ],
            ]);
        }

        $latestDefinitionId =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $request->id
                )
                ->orderByDesc(
                    'version'
                )
                ->orderByDesc(
                    'id'
                )
                ->value(
                    'id'
                );

        if (
            (int) $latestDefinitionId
            !== (int) $definition->id
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo puede solicitarse cambios sobre la versión más reciente presentada.',
                ],
            ]);
        }

        if (
            data_get(
                $definition->source_snapshot,
                'source_type'
            ) !== 'implementation_request'

            || data_get(
                $definition->implementation_scope,
                'scope_mode'
            ) !== TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE

            || data_get(
                $definition->implementation_scope,
                'definition_scope_locked_to_request'
            ) !== true
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no cumple el contrato request-scoped single-capability.',
                ],
            ]);
        }

        if (
            $definition->status
            !== TransformationImplementationDefinition::STATUS_UNDER_REVIEW
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La versión presentada debe permanecer en revisión antes de solicitar cambios.',
                ],
            ]);
        }

        if (
            data_get(
                $definition->readiness,
                'definition_ready'
            ) === true

            || $definition->ready_at !== null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Una Definition ya cerrada como ready no admite esta acción.',
                ],
            ]);
        }
    }
}
