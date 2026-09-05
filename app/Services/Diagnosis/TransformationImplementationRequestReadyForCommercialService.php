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

final class TransformationImplementationRequestReadyForCommercialService
{
    private const AGREEMENT_EVENT =
        'definition_agreed_by_tenant';

    private const FUNCTIONAL_CLOSURE_EVENT =
        'definition_functionally_finalized_by_lauda';

    private const READY_FOR_COMMERCIAL_EVENT =
        'request_ready_for_commercial_by_lauda';

    public function __construct(
        private readonly TransformationImplementationRequestService $requests
    ) {
    }

    /**
     * Abre exclusivamente el gate posterior al cierre funcional.
     *
     * definition_agreed
     *     ->
     * ready_for_commercial
     *
     * "ready_for_commercial" significa únicamente:
     * la etapa funcional terminó y un proceso comercial separado
     * puede comenzar posteriormente.
     *
     * NO significa:
     * - aceptación comercial;
     * - propuesta creada;
     * - precio aceptado;
     * - contrato aceptado;
     * - facturación iniciada;
     * - pago;
     * - suscripción;
     * - activación;
     * - ejecución.
     */
    public function markReadyForCommercial(
        TransformationImplementationRequest $request,
        User $actor
    ): TransformationImplementationRequest {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $actor
            ): TransformationImplementationRequest {
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
                 * Source of truth #1:
                 * exact Definition agreed by the tenant.
                 *
                 * No latest Definition lookup.
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
                            'La solicitud debe tener una única evidencia exacta de Definition acordada.',
                        ],
                    ]);
                }

                /** @var TransformationImplementationRequestEvent $agreementEvent */
                $agreementEvent =
                    $agreementEvents->first();

                $agreementMetadata =
                    is_array(
                        $agreementEvent->metadata
                    )
                        ? $agreementEvent->metadata
                        : [];

                $this->assertAgreementEvidence(
                    $lockedRequest,
                    $agreementEvent,
                    $agreementMetadata
                );

                $definitionId =
                    (int) (
                        $agreementMetadata[
                            'definition_id'
                        ]
                        ?? 0
                    );

                $definitionVersion =
                    (int) (
                        $agreementMetadata[
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
                            'La evidencia del acuerdo no identifica una Definition válida.',
                        ],
                    ]);
                }

                /*
                 * Resolve exact agreed Definition by pinned ID.
                 *
                 * Deliberately no max(version), latest() or
                 * orderByDesc(version).
                 */
                $definition =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $definitionId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertExactReadyDefinition(
                    $lockedRequest,
                    $definition,
                    $definitionVersion
                );

                /*
                 * Source of truth #2:
                 * same exact agreed Definition must have passed the
                 * explicit LAUDA functional-closure gate.
                 */
                $functionalClosureEvents =
                    TransformationImplementationRequestEvent::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $lockedRequest->id
                        )
                        ->where(
                            'event_type',
                            self::FUNCTIONAL_CLOSURE_EVENT
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->lockForUpdate()
                        ->get();

                if ($functionalClosureEvents->count() !== 1) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'La Definition acordada debe tener un único cierre funcional explícito de LAUDA.',
                        ],
                    ]);
                }

                /** @var TransformationImplementationRequestEvent $functionalClosureEvent */
                $functionalClosureEvent =
                    $functionalClosureEvents->first();

                $closureMetadata =
                    is_array(
                        $functionalClosureEvent->metadata
                    )
                        ? $functionalClosureEvent->metadata
                        : [];

                $this->assertFunctionalClosureEvidence(
                    $lockedRequest,
                    $agreementEvent,
                    $functionalClosureEvent,
                    $closureMetadata,
                    $definition
                );

                /*
                 * Existing RequestService owns the lifecycle:
                 *
                 * - validates contract transition;
                 * - locks Request again;
                 * - sets ready_for_commercial_at;
                 * - records generic status_transition;
                 * - records generic request audit.
                 *
                 * This wrapper is what makes that generic transition
                 * safe for this request-scoped Definition workflow.
                 */
                $transitioned =
                    $this->requests
                        ->transitionByLauda(
                            $lockedRequest,
                            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,
                            $actor,
                            'Definition funcional V'
                                .(int) $definition->version
                                .' completada. Solicitud habilitada únicamente para iniciar posteriormente una etapa comercial separada.'
                        );

                $transitioned->refresh();

                if (
                    $transitioned->status
                    !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL

                    || $transitioned->ready_for_commercial_at === null
                ) {
                    throw ValidationException::withMessages([
                        'request' => [
                            'La solicitud no alcanzó correctamente el gate ready_for_commercial.',
                        ],
                    ]);
                }

                /*
                 * The Definition is already functionally final.
                 * This Request transition must not modify it.
                 */
                $definition->refresh();

                $this->assertExactReadyDefinition(
                    $transitioned,
                    $definition,
                    $definitionVersion
                );

                /*
                 * Version-specific evidence for the functional-to-
                 * commercial boundary.
                 */
                TransformationImplementationRequestEvent::query()
                    ->create([
                        'transformation_implementation_request_id' =>
                            $transitioned->id,

                        'event_type' =>
                            self::READY_FOR_COMMERCIAL_EVENT,

                        'from_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'to_status' =>
                            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,

                        'actor_type' =>
                            TransformationImplementationRequestService::ACTOR_LAUDA_ADMIN,

                        'actor_user_id' =>
                            $actor->id,

                        'notes' =>
                            'Gate funcional completado sobre Definition V'
                            .(int) $definition->version
                            .'. La etapa comercial permanece separada.',

                        'metadata' => [
                            'request_id' =>
                                (int) $transitioned->id,

                            'agreement_event_id' =>
                                (int) $agreementEvent->id,

                            'functional_closure_event_id' =>
                                (int) $functionalClosureEvent->id,

                            'definition_id' =>
                                (int) $definition->id,

                            'definition_version' =>
                                (int) $definition->version,

                            'company_id' =>
                                (int) $transitioned->company_id,

                            'phase_capability_id' =>
                                (int) $transitioned
                                    ->transformation_implementation_phase_capability_id,

                            'capability_key' =>
                                (string) $transitioned->capability_key,

                            'request_from_status' =>
                                TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                            'request_to_status' =>
                                TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,

                            'functional_definition_ready' =>
                                true,

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

                            /*
                             * Commercial boundary:
                             * this gate is readiness for a separate
                             * commercial stage, not commercial consent.
                             */
                            'commercial_acceptance' =>
                                false,

                            'commercial_proposal_created' =>
                                false,

                            'pricing_created' =>
                                false,

                            'contract_accepted' =>
                                false,

                            'billing_started' =>
                                false,

                            'invoice_created' =>
                                false,

                            'payment_created' =>
                                false,

                            'subscription_created' =>
                                false,

                            'activation_started' =>
                                false,

                            'service_active' =>
                                false,

                            'actor_user_id' =>
                                (int) $actor->id,
                        ],

                        'occurred_at' =>
                            now(),
                    ]);

                AuditService::log(
                    'transformation_implementation_request_ready_for_commercial_by_lauda',
                    $transitioned,
                    [
                        'request_id' =>
                            (int) $transitioned->id,

                        'agreement_event_id' =>
                            (int) $agreementEvent->id,

                        'functional_closure_event_id' =>
                            (int) $functionalClosureEvent->id,

                        'definition_id' =>
                            (int) $definition->id,

                        'definition_version' =>
                            (int) $definition->version,

                        'company_id' =>
                            (int) $transitioned->company_id,

                        'phase_capability_id' =>
                            (int) $transitioned
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $transitioned->capability_key,

                        'request_from_status' =>
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,

                        'request_to_status' =>
                            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,

                        'functional_definition_ready' =>
                            true,

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

                        'commercial_proposal_created' =>
                            false,

                        'pricing_created' =>
                            false,

                        'contract_accepted' =>
                            false,

                        'billing_started' =>
                            false,

                        'invoice_created' =>
                            false,

                        'payment_created' =>
                            false,

                        'subscription_created' =>
                            false,

                        'activation_started' =>
                            false,

                        'service_active' =>
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

    private function assertLaudaAdmin(
        User $actor
    ): void {
        if (
            ($actor->role ?? null)
            !== 'admin'
        ) {
            throw new AuthorizationException(
                'El gate ready_for_commercial requiere un Admin LAUDA.'
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
                    'La solicitud debe permanecer en definition_agreed antes del gate comercial.',
                ],
            ]);
        }

        if ($request->definition_agreed_at === null) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud no contiene evidencia temporal del acuerdo funcional.',
                ],
            ]);
        }

        if ($request->ready_for_commercial_at !== null) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud ya fue habilitada para la etapa comercial.',
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
                    'La evidencia de acuerdo no corresponde exactamente a esta solicitud.',
                ],
            ]);
        }
    }

    private function assertExactReadyDefinition(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        int $expectedVersion
    ): void {
        if (
            (int) $definition
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

            || (int) $definition->version
                !== $expectedVersion

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
                    'La Definition no coincide con la versión exacta acordada.',
                ],
            ]);
        }

        if (
            $definition->status
                !== TransformationImplementationDefinition::STATUS_READY

            || data_get(
                $definition->readiness,
                'state'
            ) !== 'ready'

            || data_get(
                $definition->readiness,
                'definition_ready'
            ) !== true

            || data_get(
                $definition->readiness,
                'technical_readiness'
            ) !== true

            || data_get(
                $definition->readiness,
                'ready_for_execution'
            ) !== false

            || data_get(
                $definition->readiness,
                'execution_started'
            ) !== false

            || $definition->ready_at === null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition acordada aún no ha completado el cierre funcional requerido.',
                ],
            ]);
        }
    }

    private function assertFunctionalClosureEvidence(
        TransformationImplementationRequest $request,
        TransformationImplementationRequestEvent $agreementEvent,
        TransformationImplementationRequestEvent $closureEvent,
        array $metadata,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            $closureEvent->event_type
                !== self::FUNCTIONAL_CLOSURE_EVENT

            || $closureEvent->from_status
                !== TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED

            || $closureEvent->to_status
                !== TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED

            || $closureEvent->actor_type
                !== TransformationImplementationRequestService::ACTOR_LAUDA_ADMIN

            || (int) $closureEvent->id
                <= (int) $agreementEvent->id

            || (int) (
                $metadata[
                    'request_id'
                ]
                ?? 0
            ) !== (int) $request->id

            || (int) (
                $metadata[
                    'agreement_event_id'
                ]
                ?? 0
            ) !== (int) $agreementEvent->id

            || (int) (
                $metadata[
                    'definition_id'
                ]
                ?? 0
            ) !== (int) $definition->id

            || (int) (
                $metadata[
                    'definition_version'
                ]
                ?? 0
            ) !== (int) $definition->version

            || (int) (
                $metadata[
                    'company_id'
                ]
                ?? 0
            ) !== (int) $request->company_id

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
                    'definition_ready'
                ]
                ?? null
            ) !== true

            || (
                $metadata[
                    'technical_readiness'
                ]
                ?? null
            ) !== true

            || (
                $metadata[
                    'ready_for_execution'
                ]
                ?? null
            ) !== false

            || (
                $metadata[
                    'execution_started'
                ]
                ?? null
            ) !== false

            || (
                $metadata[
                    'ready_for_commercial'
                ]
                ?? null
            ) !== false
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La evidencia de cierre funcional no corresponde a la Definition exacta acordada.',
                ],
            ]);
        }
    }
}
