<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestDefinitionTenantReviewService
{
    private const REQUIRED_CONFIRMATIONS = [
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
     * Envía una Definition funcional revisada por LAUDA
     * a revisión del Tenant Admin.
     *
     * Importante:
     *
     * - la Definition permanece STATUS_UNDER_REVIEW;
     * - definition_ready permanece false;
     * - ready_at permanece null;
     * - el Request pasa a awaiting_tenant_review;
     * - NO equivale a acuerdo del tenant;
     * - NO llama markReady();
     * - NO activa ni inicia ejecución;
     * - NO inicia etapa comercial.
     */
    public function submit(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        User $actor,
        ?string $notes = null
    ): TransformationImplementationRequest {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $definition,
                $actor,
                $notes
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

                $this->assertRequestContext(
                    $lockedRequest,
                    $lockedDefinition
                );

                /*
                 * El ID de Definition viene en la ruta HTTP, por lo que
                 * nunca se considera una fuente confiable para decidir
                 * qué versión debe presentarse al tenant.
                 *
                 * Solo la última Definition request-scoped puede salir
                 * nuevamente a revisión.
                 */
                $this->assertLatestDefinition(
                    $lockedRequest,
                    $lockedDefinition
                );

                $this->assertReadyForTenantReview(
                    $lockedDefinition
                );

                /*
                 * Esta es la única transición de lifecycle
                 * realizada por este servicio.
                 *
                 * El propio TransformationImplementationRequestService:
                 *
                 * - valida la matriz LAUDA;
                 * - registra evento de transición;
                 * - registra audit;
                 * - fija tenant_review_requested_at.
                 */
                $transitioned =
                    $this->requests
                        ->transitionByLauda(
                            $lockedRequest,
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
                            $actor,
                            $this->normalizeNotes(
                                $notes,
                                $lockedDefinition
                            )
                        );

                /*
                 * Audit específico que conserva cuál versión
                 * exacta de Definition fue enviada al tenant.
                 *
                 * No crea una segunda transición de Request.
                 */
                AuditService::log(
                    'transformation_implementation_definition_submitted_for_tenant_review',
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

                        'phase_capability_id' =>
                            $lockedDefinition
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            $lockedDefinition->capability_key,

                        'scope_mode' =>
                            TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                        'request_status' =>
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,

                        'definition_ready' =>
                            false,

                        'tenant_agreed' =>
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
            throw ValidationException::withMessages([
                'actor' => [
                    'Solo un Admin LAUDA puede enviar la Definition a revisión de la empresa.',
                ],
            ]);
        }
    }

    private function assertRequestContext(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud debe estar en preparación de definición antes de enviarla a revisión de la empresa.',
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
                    'La Definition no pertenece exactamente a esta solicitud de implementación.',
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
            )
                !== TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE

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
    }

    /**
     * Protege el reenvío request-scoped cuando existen V1, V2, V3...
     *
     * Una URL histórica puede contener el id de V1, pero el dominio
     * siempre resuelve por servidor la última Definition del Request.
     */
    private function assertLatestDefinition(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        $latest =
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
                    $request->transformation_implementation_plan_id
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
                ->first([
                    'id',
                    'version',
                ]);

        if (! $latest) {
            throw ValidationException::withMessages([
                'definition' => [
                    'No existe una Definition request-scoped disponible para enviar a revisión de la empresa.',
                ],
            ]);
        }

        if (
            (int) $latest->id
            !== (int) $definition->id
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo la versión más reciente de la Definition puede enviarse a revisión de la empresa.',
                ],
            ]);
        }
    }


    private function assertReadyForTenantReview(
        TransformationImplementationDefinition $definition
    ): void {
        /*
         * "Lista para revisión del tenant" no es lo mismo
         * que STATUS_READY / definition_ready.
         */
        if (
            $definition->status
            !== TransformationImplementationDefinition::STATUS_UNDER_REVIEW
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo una Definition revisada por LAUDA puede enviarse a la empresa.',
                ],
            ]);
        }

        $scope =
            $definition->implementation_scope
            ?? [];

        $deliverables =
            $definition->deliverables
            ?? [];

        $responsibilities =
            $definition->responsibility_model
            ?? [];

        $readiness =
            $definition->readiness
            ?? [];

        if (
            ! is_array(
                $scope
            )
            || empty(
                data_get(
                    $scope,
                    'phases',
                    []
                )
            )
        ) {
            throw ValidationException::withMessages([
                'implementation_scope' => [
                    'Debe existir alcance funcional revisado antes de enviar la Definition al tenant.',
                ],
            ]);
        }

        if (
            ! is_array(
                $deliverables
            )
            || $deliverables === []
        ) {
            throw ValidationException::withMessages([
                'deliverables' => [
                    'Debe existir al menos un entregable funcional revisado.',
                ],
            ]);
        }

        if (
            ! is_array(
                $responsibilities
            )
            || data_get(
                $responsibilities,
                'party_assignment_status'
            ) !== 'confirmed'
        ) {
            throw ValidationException::withMessages([
                'responsibility_model' => [
                    'Las responsabilidades deben estar confirmadas antes de enviar la Definition al tenant.',
                ],
            ]);
        }

        if (
            ! is_array(
                $readiness
            )
            || data_get(
                $readiness,
                'state'
            ) !== 'under_review'
        ) {
            throw ValidationException::withMessages([
                'readiness' => [
                    'La Definition debe haber completado la revisión humana de LAUDA.',
                ],
            ]);
        }

        $humanValidation =
            data_get(
                $readiness,
                'human_validation',
                []
            );

        foreach (
            self::REQUIRED_CONFIRMATIONS
            as $confirmation
        ) {
            if (
                (
                    $humanValidation[
                        $confirmation
                    ]
                    ?? null
                ) !== true
            ) {
                throw ValidationException::withMessages([
                    "readiness.{$confirmation}" => [
                        'Todas las confirmaciones humanas de LAUDA deben estar completas antes de enviar la Definition al tenant.',
                    ],
                ]);
            }
        }

        /*
         * Protege explícitamente el significado de F5G.
         *
         * Aún no existe acuerdo del tenant.
         */
        if (
            data_get(
                $readiness,
                'definition_ready'
            ) === true
            || $definition->ready_at !== null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Una Definition ya marcada como ready no pertenece al paso de envío inicial a revisión del tenant.',
                ],
            ]);
        }

        if (
            data_get(
                $readiness,
                'ready_for_execution'
            ) === true
            || data_get(
                $readiness,
                'execution_started'
            ) === true
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no puede haber iniciado ejecución antes de la revisión del tenant.',
                ],
            ]);
        }
    }

    private function normalizeNotes(
        ?string $notes,
        TransformationImplementationDefinition $definition
    ): string {
        $normalized =
            trim(
                (string) $notes
            );

        if (
            $normalized !== ''
        ) {
            return $normalized;
        }

        return "Definition V{$definition->version} enviada por LAUDA a revisión de la empresa.";
    }
}
