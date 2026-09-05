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

final class TransformationImplementationRequestDefinitionRevisionService
{
    private const HUMAN_CONFIRMATIONS = [
        'scope_confirmed',
        'deliverables_confirmed',
        'dependencies_confirmed',
        'inputs_validated',
        'accesses_validated',
        'responsibilities_confirmed',
    ];

    public function __construct(
        private readonly TransformationImplementationRequestService $requests
    ) {
    }

    /**
     * Crea explícitamente la siguiente versión request-scoped.
     *
     * Contrato:
     *
     * - requiere Request changes_requested;
     * - requiere la última Definition presentada;
     * - V1/Vn anterior queda inmutable;
     * - crea V(n+1) draft;
     * - copia el contenido funcional como baseline;
     * - reinicia confirmaciones humanas/readiness;
     * - mueve Request a definition_preparation;
     * - NO autogenera encima de la versión anterior;
     * - NO marca ready;
     * - NO inicia comercial ni ejecución.
     */
    public function createRevision(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $previousDefinition,
        User $actor
    ): TransformationImplementationDefinition {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $previousDefinition,
                $actor
            ): TransformationImplementationDefinition {
                $lockedRequest =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertRequestReadyForRevision(
                    $lockedRequest
                );

                /*
                 * La autoridad de versionado es el Request.
                 *
                 * Siempre bloqueamos y resolvemos la última
                 * Definition request-scoped desde servidor.
                 */
                $latestDefinition =
                    TransformationImplementationDefinition::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $lockedRequest->id
                        )
                        ->orderByDesc(
                            'version'
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    (int) $latestDefinition->id
                    !== (int) $previousDefinition->id
                ) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'Solo puede crearse una revisión desde la última Definition presentada.',
                        ],
                    ]);
                }

                $this->assertPreviousDefinitionContext(
                    $lockedRequest,
                    $latestDefinition
                );

                $changeEvent =
                    $this->tenantChangesEvent(
                        $lockedRequest
                    );

                $tenantReason =
                    trim(
                        (string) (
                            $changeEvent->notes
                            ?? ''
                        )
                    );

                if ($tenantReason === '') {
                    throw ValidationException::withMessages([
                        'request' => [
                            'La revisión requiere la razón registrada por la empresa.',
                        ],
                    ]);
                }

                $nextVersion =
                    ((int) $latestDefinition->version)
                    + 1;

                /*
                 * Copiamos contenido funcional como baseline,
                 * no el estado de aprobación.
                 */
                $scope =
                    is_array(
                        $latestDefinition
                            ->implementation_scope
                    )
                        ? $latestDefinition
                            ->implementation_scope
                        : [];

                $scope[
                    'scope_mode'
                ] =
                    TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE;

                $scope[
                    'capability_key'
                ] =
                    (string) $lockedRequest
                        ->capability_key;

                $scope[
                    'definition_scope_locked_to_request'
                ] =
                    true;

                $responsibilityModel =
                    is_array(
                        $latestDefinition
                            ->responsibility_model
                    )
                        ? $latestDefinition
                            ->responsibility_model
                        : [];

                /*
                 * Las asignaciones propuestas pueden conservarse
                 * como baseline, pero dejan de estar confirmadas.
                 */
                $responsibilityModel[
                    'confirmation_required'
                ] =
                    true;

                $responsibilityModel[
                    'party_assignment_status'
                ] =
                    'to_be_defined';

                $readiness =
                    $this->revisionReadiness();

                $revision =
                    TransformationImplementationDefinition::query()
                        ->create([
                            'transformation_implementation_request_id' =>
                                $lockedRequest->id,

                            'transformation_implementation_phase_capability_id' =>
                                $lockedRequest
                                    ->transformation_implementation_phase_capability_id,

                            'transformation_implementation_plan_id' =>
                                $lockedRequest
                                    ->transformation_implementation_plan_id,

                            'diagnosis_assessment_id' =>
                                $lockedRequest
                                    ->diagnosis_assessment_id,

                            'company_id' =>
                                $lockedRequest
                                    ->company_id,

                            'capability_key' =>
                                $lockedRequest
                                    ->capability_key,

                            'version' =>
                                $nextVersion,

                            'status' =>
                                TransformationImplementationDefinition::STATUS_DRAFT,

                            'source_snapshot' => [
                                'source_type' =>
                                    'implementation_request',

                                'request_id' =>
                                    $lockedRequest->id,

                                'company_id' =>
                                    $lockedRequest
                                        ->company_id,

                                'diagnosis_assessment_id' =>
                                    $lockedRequest
                                        ->diagnosis_assessment_id,

                                'plan_id' =>
                                    $lockedRequest
                                        ->transformation_implementation_plan_id,

                                'phase_capability_id' =>
                                    $lockedRequest
                                        ->transformation_implementation_phase_capability_id,

                                'capability_key' =>
                                    $lockedRequest
                                        ->capability_key,

                                'revision' => [
                                    'revision_of_definition_id' =>
                                        $latestDefinition->id,

                                    'revision_of_definition_version' =>
                                        $latestDefinition->version,

                                    'tenant_change_event_id' =>
                                        $changeEvent->id,

                                    'tenant_changes_requested_at' =>
                                        $lockedRequest
                                            ->changes_requested_at
                                            ?->toISOString(),

                                    'tenant_change_reason' =>
                                        $tenantReason,
                                ],
                            ],

                            'implementation_scope' =>
                                $scope,

                            'deliverables' =>
                                $latestDefinition
                                    ->deliverables,

                            'dependencies' =>
                                $latestDefinition
                                    ->dependencies,

                            'responsibility_model' =>
                                $responsibilityModel,

                            'readiness' =>
                                $readiness,

                            /*
                             * Internal-only provenance.
                             * No se expone al tenant.
                             */
                            'internal_notes' =>
                                'Revisión V'
                                .$nextVersion
                                .' creada desde V'
                                .$latestDefinition->version
                                .' tras solicitud de cambios del tenant. Evento #'
                                .$changeEvent->id
                                .'.',

                            'created_by_user_id' =>
                                $actor->id,

                            'updated_by_user_id' =>
                                $actor->id,

                            'reviewed_by_user_id' =>
                                null,

                            'reviewed_at' =>
                                null,

                            'ready_at' =>
                                null,
                        ]);

                /*
                 * La transición y la creación de V2 están dentro
                 * de la misma transacción. Si cualquiera falla,
                 * ninguna queda persistida.
                 */
                $this->requests
                    ->transitionByLauda(
                        $lockedRequest,
                        TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                        $actor,
                        'Nueva revisión de Definition creada a partir de la solicitud de cambios del tenant. V'
                        .$latestDefinition->version
                        .' → V'
                        .$revision->version
                        .'.'
                    );

                AuditService::log(
                    'transformation_implementation_definition_revision_created',
                    $revision,
                    [
                        'request_id' =>
                            $lockedRequest->id,

                        'company_id' =>
                            $lockedRequest
                                ->company_id,

                        'capability_key' =>
                            $lockedRequest
                                ->capability_key,

                        'previous_definition_id' =>
                            $latestDefinition->id,

                        'previous_definition_version' =>
                            $latestDefinition
                                ->version,

                        'revision_definition_id' =>
                            $revision->id,

                        'revision_definition_version' =>
                            $revision->version,

                        'tenant_change_event_id' =>
                            $changeEvent->id,

                        'tenant_change_reason' =>
                            $tenantReason,

                        'previous_definition_modified' =>
                            false,

                        'revision_status' =>
                            TransformationImplementationDefinition::STATUS_DRAFT,

                        'definition_ready' =>
                            false,

                        'ready_for_execution' =>
                            false,

                        'execution_started' =>
                            false,

                        'commercial_stage_started' =>
                            false,

                        'actor_user_id' =>
                            $actor->id,
                    ]
                );

                return $revision->fresh();
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
                'Solo un administrador LAUDA puede crear una nueva versión de Definition.'
            );
        }
    }

    private function assertRequestReadyForRevision(
        TransformationImplementationRequest $request
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'Solo una solicitud con cambios solicitados puede iniciar una nueva revisión de Definition.',
                ],
            ]);
        }

        if (
            $request->changes_requested_at
            === null
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud no contiene la marca temporal de cambios solicitados.',
                ],
            ]);
        }
    }

    private function assertPreviousDefinitionContext(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
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
                (string) $definition
                    ->capability_key
            )
                !== trim(
                    (string) $request
                        ->capability_key
                )
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition anterior no pertenece exactamente a esta solicitud.',
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
                    'La Definition anterior no cumple el contrato request-scoped single-capability.',
                ],
            ]);
        }

        if (
            $definition->status
            !== TransformationImplementationDefinition::STATUS_UNDER_REVIEW
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La versión presentada debe permanecer under_review antes de crear su revisión.',
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
                    'Una Definition ya cerrada como ready no puede usarse como versión pendiente de cambios.',
                ],
            ]);
        }
    }

    private function tenantChangesEvent(
        TransformationImplementationRequest $request
    ): TransformationImplementationRequestEvent {
        $event =
            TransformationImplementationRequestEvent::query()
                ->where(
                    'transformation_implementation_request_id',
                    $request->id
                )
                ->where(
                    'event_type',
                    'status_transition'
                )
                ->where(
                    'from_status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        if (! $event) {
            throw ValidationException::withMessages([
                'request' => [
                    'No existe el evento de solicitud de cambios del tenant.',
                ],
            ]);
        }

        return $event;
    }

    private function revisionReadiness(): array
    {
        $humanValidation = [];

        foreach (
            self::HUMAN_CONFIRMATIONS
            as $key
        ) {
            $humanValidation[$key] =
                false;
        }

        return [
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

            'human_review_required' =>
                true,

            'human_review_completed' =>
                false,

            'human_validation' =>
                $humanValidation,

            'blockers' =>
                [],
        ];
    }
}
