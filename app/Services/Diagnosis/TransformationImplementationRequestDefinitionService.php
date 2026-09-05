<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestDefinitionService
{
    /**
     * Crea la primera Definition funcional vinculada a una
     * solicitud explícita del tenant.
     *
     * Contrato:
     * - LAUDA Admin solamente.
     * - Request en definition_preparation.
     * - mismo Company / Assessment / Plan.
     * - una única phase capability.
     * - capability implementation_only.
     * - idempotente.
     *
     * NO cambia el estado del Request.
     * NO activa la capability.
     * NO inicia ejecución.
     * NO crea propuesta, cobro ni suscripción.
     */
    public function createOrGetDraftFromRequest(
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
                $locked =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $request->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertRequestStatus(
                    $locked
                );

                $company =
                    Company::query()
                        ->findOrFail(
                            $locked->company_id
                        );

                $assessment =
                    DiagnosisAssessment::query()
                        ->findOrFail(
                            $locked->diagnosis_assessment_id
                        );

                $plan =
                    TransformationImplementationPlan::query()
                        ->findOrFail(
                            $locked->transformation_implementation_plan_id
                        );

                $phaseCapability =
                    TransformationImplementationPhaseCapability::query()
                        ->findOrFail(
                            $locked->transformation_implementation_phase_capability_id
                        );

                $phase =
                    TransformationImplementationPhase::query()
                        ->findOrFail(
                            $phaseCapability
                                ->transformation_implementation_phase_id
                        );

                $this->assertContext(
                    $locked,
                    $company,
                    $assessment,
                    $plan,
                    $phase,
                    $phaseCapability
                );

                $catalog =
                    $this->capabilityDefinition(
                        (string) $locked->capability_key
                    );

                /*
                 * Idempotencia.
                 *
                 * La creación inicial de una Definition para un
                 * mismo Request nunca produce otra versión.
                 *
                 * Las revisiones/versiones posteriores por
                 * changes_requested pertenecen a F6.
                 */
                $existing =
                    TransformationImplementationDefinition::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $locked->id
                        )
                        ->orderByDesc(
                            'version'
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existing) {
                    return $existing->fresh();
                }

                $version =
                    (
                        (int) TransformationImplementationDefinition::query()
                            ->where(
                                'transformation_implementation_request_id',
                                $locked->id
                            )
                            ->max(
                                'version'
                            )
                    ) + 1;

                $definition =
                    TransformationImplementationDefinition::query()
                        ->create([
                            'transformation_implementation_plan_id' =>
                                $plan->id,

                            'diagnosis_assessment_id' =>
                                $assessment->id,

                            'company_id' =>
                                $company->id,

                            'transformation_implementation_request_id' =>
                                $locked->id,

                            'transformation_implementation_phase_capability_id' =>
                                $phaseCapability->id,

                            'capability_key' =>
                                (string) $locked->capability_key,

                            'version' =>
                                $version,

                            'status' =>
                                TransformationImplementationDefinition::STATUS_DRAFT,

                            'source_snapshot' =>
                                $this->sourceSnapshot(
                                    $locked,
                                    $company,
                                    $assessment,
                                    $plan,
                                    $phase,
                                    $phaseCapability,
                                    $catalog
                                ),

                            'implementation_scope' =>
                                $this->initialScope(
                                    $locked,
                                    $phase,
                                    $phaseCapability,
                                    $catalog
                                ),

                            /*
                             * Estos bloques se completarán por
                             * autogeneración scoped + revisión
                             * humana en los siguientes pasos.
                             */
                            'deliverables' =>
                                null,

                            'dependencies' =>
                                null,

                            'responsibility_model' =>
                                null,

                            'readiness' => [
                                'definition_ready' =>
                                    false,

                                'human_review_required' =>
                                    true,

                                'human_review_completed' =>
                                    false,

                                'ready_for_execution' =>
                                    false,

                                'execution_started' =>
                                    false,

                                'commercial_stage_started' =>
                                    false,
                            ],

                            'internal_notes' =>
                                'Definition request-scoped creada como borrador funcional. Pendiente de autogeneración scoped y revisión humana.',

                            'created_by_user_id' =>
                                $actor->id,

                            'updated_by_user_id' =>
                                $actor->id,
                        ]);

                TransformationImplementationRequestEvent::query()
                    ->create([
                        'transformation_implementation_request_id' =>
                            $locked->id,

                        'event_type' =>
                            'definition_created',

                        'from_status' =>
                            $locked->status,

                        'to_status' =>
                            $locked->status,

                        'actor_type' =>
                            'lauda_admin',

                        'actor_user_id' =>
                            $actor->id,

                        'notes' =>
                            'LAUDA creó la Definition funcional inicial para la capability solicitada.',

                        'metadata' => [
                            'definition_id' =>
                                $definition->id,

                            'definition_version' =>
                                $definition->version,

                            'scope_mode' =>
                                TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                            'capability_key' =>
                                $locked->capability_key,

                            'plan_wide_definition' =>
                                false,

                            'commercial_stage_started' =>
                                false,

                            'execution_started' =>
                                false,
                        ],

                        'occurred_at' =>
                            now(),
                    ]);

                AuditService::log(
                    'transformation_implementation_definition_created_from_request',
                    $definition,
                    [
                        'request_id' =>
                            $locked->id,

                        'company_id' =>
                            $company->id,

                        'assessment_id' =>
                            $assessment->id,

                        'plan_id' =>
                            $plan->id,

                        'phase_capability_id' =>
                            $phaseCapability->id,

                        'capability_key' =>
                            $locked->capability_key,

                        'definition_version' =>
                            $definition->version,

                        'scope_mode' =>
                            TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                        'plan_wide_definition' =>
                            false,

                        'auto_definition' =>
                            false,

                        'activation_started' =>
                            false,

                        'commercial_stage_started' =>
                            false,

                        'execution_started' =>
                            false,

                        'subscription_created' =>
                            false,

                        'actor_user_id' =>
                            $actor->id,
                    ]
                );

                return $definition->fresh();
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
                    'Solo un Admin LAUDA puede crear la Definition de una solicitud.',
                ],
            ]);
        }
    }

    private function assertRequestStatus(
        TransformationImplementationRequest $request
    ): void {
        if (
            $request->status
            !== TransformationImplementationDefinitionRequestScopeContract::REQUIRED_REQUEST_STATUS
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La Definition solo puede crearse cuando la solicitud está en preparación de definición.',
                ],
            ]);
        }
    }

    private function assertContext(
        TransformationImplementationRequest $request,
        Company $company,
        DiagnosisAssessment $assessment,
        TransformationImplementationPlan $plan,
        TransformationImplementationPhase $phase,
        TransformationImplementationPhaseCapability $phaseCapability
    ): void {
        if (
            (int) $request->company_id
                !== (int) $company->id
        ) {
            $this->contextError(
                'company',
                'La empresa no coincide con la solicitud.'
            );
        }

        if (
            (int) $request->diagnosis_assessment_id
                !== (int) $assessment->id
            || (int) $plan->diagnosis_assessment_id
                !== (int) $assessment->id
        ) {
            $this->contextError(
                'assessment',
                'El Diagnóstico, Plan y solicitud no pertenecen al mismo contexto.'
            );
        }

        $assessmentBelongsToCompany =
            (int) (
                $assessment->organization_id
                ?? 0
            )
            === (int) $company->id;

        if (! $assessmentBelongsToCompany) {
            $assessmentBelongsToCompany =
                DiagnosisAccessRequest::query()
                    ->where(
                        'diagnosis_assessment_id',
                        $assessment->id
                    )
                    ->where(
                        'meta->company_id',
                        $company->id
                    )
                    ->exists();
        }

        if (! $assessmentBelongsToCompany) {
            $this->contextError(
                'assessment',
                'El Diagnóstico no pertenece a la empresa de la solicitud.'
            );
        }

        if (
            (int) $request->transformation_implementation_plan_id
                !== (int) $plan->id
            || $plan->status
                !== TransformationImplementationPlan::STATUS_PRESENTED
            || $plan->presented_at === null
        ) {
            $this->contextError(
                'plan',
                'La Definition requiere exactamente el Plan presentado vinculado a la solicitud.'
            );
        }

        if (
            (int) $phase->transformation_implementation_plan_id
                !== (int) $plan->id
            || (int) $phaseCapability
                ->transformation_implementation_phase_id
                !== (int) $phase->id
            || (int) $request
                ->transformation_implementation_phase_capability_id
                !== (int) $phaseCapability->id
        ) {
            $this->contextError(
                'capability',
                'La phase capability solicitada no pertenece al Plan indicado.'
            );
        }

        if (
            trim(
                (string) $request->capability_key
            )
            !== trim(
                (string) $phaseCapability->capability_key
            )
        ) {
            $this->contextError(
                'capability',
                'El capability_key de la solicitud no coincide con la capability del Plan.'
            );
        }
    }

    private function capabilityDefinition(
        string $capabilityKey
    ): array {
        $capabilityKey =
            trim(
                $capabilityKey
            );

        $definition =
            TransformationProfessionalCapabilityCatalog::get(
                $capabilityKey
            );

        if (
            ! is_array(
                $definition
            )
            || (
                $definition['kind']
                ?? null
            ) !== 'professional_service'
            || (
                $definition['service_key']
                ?? null
            ) !== null
            || (
                $definition['subscription_candidate']
                ?? true
            ) !== false
            || (
                $definition['activation_policy']
                ?? null
            ) !== 'implementation_only'
        ) {
            throw ValidationException::withMessages([
                'capability' => [
                    'La solicitud no corresponde a una capability profesional implementation_only válida.',
                ],
            ]);
        }

        return $definition;
    }

    private function sourceSnapshot(
        TransformationImplementationRequest $request,
        Company $company,
        DiagnosisAssessment $assessment,
        TransformationImplementationPlan $plan,
        TransformationImplementationPhase $phase,
        TransformationImplementationPhaseCapability $phaseCapability,
        array $catalog
    ): array {
        return [
            'source_type' =>
                TransformationImplementationDefinitionRequestScopeContract::SOURCE_TYPE,

            'scope_mode' =>
                TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

            'request' => [
                'id' =>
                    $request->id,

                'attempt' =>
                    $request->attempt,

                'status' =>
                    $request->status,

                'source_type' =>
                    $request->source_type,

                'requested_at' =>
                    $request->requested_at?->toISOString(),
            ],

            'company' => [
                'id' =>
                    $company->id,

                'name' =>
                    $company->name,
            ],

            'assessment' => [
                'id' =>
                    $assessment->id,

                'organization_name' =>
                    $assessment->organization_name,

                'status' =>
                    $assessment->status,
            ],

            'plan' => [
                'id' =>
                    $plan->id,

                'version' =>
                    $plan->version,

                'status' =>
                    $plan->status,

                'presented_at' =>
                    $plan->presented_at?->toISOString(),
            ],

            'phase' => [
                'id' =>
                    $phase->id,

                'sequence' =>
                    $phase->sequence,

                'name' =>
                    $phase->name,

                'objective' =>
                    $phase->objective,

                'source_snapshot' =>
                    $phase->source_snapshot,
            ],

            /*
             * Única capability permitida dentro de esta
             * Definition request-scoped.
             */
            'capability' => [
                'id' =>
                    $phaseCapability->id,

                'capability_key' =>
                    $phaseCapability->capability_key,

                'capability_label' =>
                    $phaseCapability->capability_label,

                'capability_summary' =>
                    $phaseCapability->capability_summary,

                'source_snapshot' =>
                    $phaseCapability->source_snapshot,

                'catalog' => [
                    'title' =>
                        $catalog['title']
                        ?? $phaseCapability->capability_label,

                    'kind' =>
                        $catalog['kind']
                        ?? null,

                    'category' =>
                        $catalog['category']
                        ?? null,

                    'purpose' =>
                        $catalog['purpose']
                        ?? null,

                    'includes' =>
                        array_values(
                            $catalog['includes']
                            ?? []
                        ),

                    'excludes' =>
                        array_values(
                            $catalog['excludes']
                            ?? []
                        ),

                    'activation_policy' =>
                        $catalog['activation_policy']
                        ?? null,

                    'requires_lauda_review' =>
                        (bool) (
                            $catalog[
                                'requires_lauda_review'
                            ]
                            ?? false
                        ),
                ],
            ],

            'boundary' => [
                'plan_wide_definition' =>
                    false,

                'single_capability' =>
                    true,

                'definition_ready' =>
                    false,

                'activation_started' =>
                    false,

                'commercial_stage_started' =>
                    false,

                'execution_started' =>
                    false,

                'subscription_created' =>
                    false,
            ],
        ];
    }

    private function initialScope(
        TransformationImplementationRequest $request,
        TransformationImplementationPhase $phase,
        TransformationImplementationPhaseCapability $phaseCapability,
        array $catalog
    ): array {
        return [
            'scope_mode' =>
                TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

            'request_id' =>
                $request->id,

            'phase_id' =>
                $phase->id,

            'phase_sequence' =>
                $phase->sequence,

            'phase_name' =>
                $phase->name,

            'phase_capability_id' =>
                $phaseCapability->id,

            'capability_key' =>
                $phaseCapability->capability_key,

            'capability_label' =>
                $phaseCapability->capability_label,

            'purpose' =>
                $catalog['purpose']
                ?? $phaseCapability->capability_summary,

            'includes' =>
                array_values(
                    $catalog['includes']
                    ?? []
                ),

            'excludes' =>
                array_values(
                    $catalog['excludes']
                    ?? []
                ),

            'definition_scope_locked_to_request' =>
                true,
        ];
    }

    private function contextError(
        string $field,
        string $message
    ): never {
        throw ValidationException::withMessages([
            $field => [
                $message,
            ],
        ]);
    }
}
