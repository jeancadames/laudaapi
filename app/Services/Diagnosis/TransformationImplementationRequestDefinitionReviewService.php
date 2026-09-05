<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestDefinitionReviewService
{
    public function __construct(
        private readonly TransformationImplementationDefinitionReviewService $reviews
    ) {
    }

    /**
     * Guarda revisión humana LAUDA de una Definition
     * perteneciente a una ImplementationRequest.
     *
     * Esta operación:
     * - exige Admin LAUDA;
     * - exige Request en definition_preparation;
     * - exige identidad exacta Request/Definition/capability;
     * - exige contenido previamente preparado;
     * - delega la revisión funcional al servicio Definition;
     * - deja la Definition en under_review;
     * - deja el Request en definition_preparation;
     * - NO marca Definition ready;
     * - NO envía al tenant;
     * - NO inicia comercial, activación ni ejecución.
     */
    public function saveReview(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        array $data,
        User $actor
    ): TransformationImplementationDefinition {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $definition,
                $data,
                $actor
            ): TransformationImplementationDefinition {
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

                $this->assertRequestStatus(
                    $lockedRequest
                );

                $this->assertLatestDefinition(
                    $lockedRequest,
                    $lockedDefinition
                );

                $this->assertDefinitionContext(
                    $lockedRequest,
                    $lockedDefinition
                );

                $this->assertPreparedContent(
                    $lockedDefinition
                );

                /*
                 * El servicio Definition existente conserva
                 * una sola fuente de verdad para:
                 *
                 * - validación de responsables;
                 * - human_validation;
                 * - blockers;
                 * - STATUS_UNDER_REVIEW;
                 * - reviewed_by / reviewed_at;
                 * - garantía de definition_ready=false.
                 */
                $reviewData =
                    $this->normalizeReviewData(
                        $lockedRequest,
                        $lockedDefinition,
                        $data
                    );

                $reviewed =
                    $this->reviews
                        ->saveReview(
                            $lockedDefinition,
                            $reviewData,
                            $actor
                        );

                /*
                 * La revisión humana pertenece también al
                 * historial de la ImplementationRequest.
                 *
                 * No es una transición del lifecycle:
                 * from_status === to_status.
                 */
                TransformationImplementationRequestEvent::query()
                    ->create([
                        'transformation_implementation_request_id' =>
                            $lockedRequest->id,

                        'event_type' =>
                            'definition_review_saved',

                        'from_status' =>
                            $lockedRequest->status,

                        'to_status' =>
                            $lockedRequest->status,

                        'actor_type' =>
                            'lauda_admin',

                        'actor_user_id' =>
                            $actor->id,

                        'notes' =>
                            'LAUDA guardó revisión humana de la Definition funcional solicitada.',

                        'metadata' => [
                            'definition_id' =>
                                $reviewed->id,

                            'definition_version' =>
                                $reviewed->version,

                            'definition_status' =>
                                $reviewed->status,

                            'phase_capability_id' =>
                                $reviewed
                                    ->transformation_implementation_phase_capability_id,

                            'capability_key' =>
                                $reviewed->capability_key,

                            'scope_mode' =>
                                TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                            'definition_ready' =>
                                false,

                            'request_status_changed' =>
                                false,

                            'tenant_review_started' =>
                                false,

                            'commercial_stage_started' =>
                                false,

                            'execution_started' =>
                                false,
                        ],

                        'occurred_at' =>
                            now(),
                    ]);

                return $reviewed->fresh();
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
                    'Solo un Admin LAUDA puede revisar esta Definition.',
                ],
            ]);
        }
    }

    private function assertRequestStatus(
        TransformationImplementationRequest $request
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La revisión LAUDA solo está disponible durante la preparación de definición.',
                ],
            ]);
        }
    }

    /**
     * V1 deja de ser editable desde el flujo request-scoped
     * en cuanto existe una V2+.
     *
     * El modelo genérico permite draft/under_review, por lo que
     * esta garantía pertenece a la capa request-scoped.
     */
    private function assertLatestDefinition(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        $latestId =
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
            (int) $latestId
            !== (int) $definition->id
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'Solo la versión más reciente de la Definition puede editarse o revisarse.',
                ],
            ]);
        }
    }

    /**
     * Conserva el schema funcional existente.
     *
     * implementation_scope/deliverables/dependencies se aceptan
     * como las estructuras JSON ya producidas por la Definition.
     * No inventamos un segundo schema.
     *
     * Las claves que fijan el request-scope son siempre
     * reconstruidas server-side y nunca quedan bajo control
     * del navegador.
     */
    private function normalizeReviewData(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        array $data
    ): array {
        if (
            array_key_exists(
                'implementation_scope',
                $data
            )
        ) {
            if (
                ! is_array(
                    $data['implementation_scope']
                )
            ) {
                throw ValidationException::withMessages([
                    'implementation_scope' => [
                        'El alcance funcional debe conservar una estructura válida.',
                    ],
                ]);
            }

            $data['implementation_scope'][
                'scope_mode'
            ] =
                TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE;

            $data['implementation_scope'][
                'capability_key'
            ] =
                (string) $request->capability_key;

            $data['implementation_scope'][
                'definition_scope_locked_to_request'
            ] =
                true;
        }

        foreach ([
            'deliverables',
            'dependencies',
            'responsibility_model',
        ] as $field) {
            if (
                array_key_exists(
                    $field,
                    $data
                )
                && ! is_array(
                    $data[$field]
                )
            ) {
                throw ValidationException::withMessages([
                    $field => [
                        'El contenido funcional debe conservar una estructura válida.',
                    ],
                ]);
            }
        }

        return $data;
    }


    private function assertDefinitionContext(
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

            || trim(
                (string) $definition->capability_key
            )
                !== trim(
                    (string) $request->capability_key
                )

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
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no pertenece exactamente a esta solicitud de implementación.',
                ],
            ]);
        }

        if (
            (
                data_get(
                    $definition->source_snapshot,
                    'source_type'
                )
            ) !== 'implementation_request'
            || (
                data_get(
                    $definition->implementation_scope,
                    'scope_mode'
                )
            )
                !== TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE
            || (
                data_get(
                    $definition->implementation_scope,
                    'definition_scope_locked_to_request'
                )
            ) !== true
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no cumple el contrato request-scoped.',
                ],
            ]);
        }
    }

    private function assertPreparedContent(
        TransformationImplementationDefinition $definition
    ): void {
        if (
            ! $definition->isEditable()
            || ! is_array(
                $definition->implementation_scope
            )
            || ! is_array(
                $definition->deliverables
            )
            || ! is_array(
                $definition->dependencies
            )
            || ! is_array(
                $definition->responsibility_model
            )
            || ! is_array(
                $definition->readiness
            )
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition debe tener contenido funcional preparado antes de la revisión humana.',
                ],
            ]);
        }

        $state =
            data_get(
                $definition->readiness,
                'state'
            );

        /*
         * Se permite guardar múltiples revisiones humanas.
         */
        if (
            ! in_array(
                $state,
                [
                    'prepared_for_review',
                    'under_review',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition no está en un estado válido para revisión humana.',
                ],
            ]);
        }
    }
}
