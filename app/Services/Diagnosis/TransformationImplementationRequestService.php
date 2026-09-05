<?php

namespace App\Services\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAccessRequest;
use App\Models\DiagnosisAssessment;
use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationRequestService
{
    public const ACTOR_TENANT_ADMIN = 'tenant_admin';

    public const ACTOR_LAUDA_ADMIN = 'lauda_admin';

    /**
     * Registra o recupera idempotentemente la solicitud activa del tenant.
     *
     * No activa capability.
     * No crea Definition.
     * No inicia ejecución.
     * No crea artefactos comerciales.
     */
    public function requestFromTenantAdmin(
        Company $company,
        DiagnosisAssessment $assessment,
        TransformationImplementationPlan $plan,
        TransformationImplementationPhaseCapability $phaseCapability,
        User $actor,
        ?string $tenantNote = null
    ): TransformationImplementationRequest {
        $catalog = $this->assertRequestableContext(
            $company,
            $assessment,
            $plan,
            $phaseCapability
        );

        return DB::transaction(function () use (
            $company,
            $assessment,
            $plan,
            $phaseCapability,
            $actor,
            $tenantNote,
            $catalog
        ): TransformationImplementationRequest {
            /*
             * Serializa las solicitudes de una misma empresa para que
             * dos submits concurrentes no creen dos attempts activos.
             */
            Company::query()
                ->whereKey($company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $scope = TransformationImplementationRequest::query()
                ->where('company_id', $company->id)
                ->where(
                    'transformation_implementation_plan_id',
                    $plan->id
                )
                ->where(
                    'capability_key',
                    $phaseCapability->capability_key
                );

            $active = (clone $scope)
                ->active()
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($active) {
                return $active->fresh();
            }

            $latest = (clone $scope)
                ->lockForUpdate()
                ->orderByDesc('attempt')
                ->orderByDesc('id')
                ->first();

            if (
                $latest
                && $latest->status
                    === TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL
            ) {
                throw ValidationException::withMessages([
                    'request' => [
                        'Esta capacidad ya completó la etapa funcional de solicitud y definición para este Plan.',
                    ],
                ]);
            }

            $attempt = $latest
                ? ((int) $latest->attempt + 1)
                : 1;

            $phase = TransformationImplementationPhase::query()
                ->findOrFail(
                    $phaseCapability
                        ->transformation_implementation_phase_id
                );

            $request =
                TransformationImplementationRequest::query()->create([
                    'company_id' => $company->id,
                    'diagnosis_assessment_id' => $assessment->id,
                    'transformation_implementation_plan_id' =>
                        $plan->id,
                    'transformation_implementation_phase_capability_id' =>
                        $phaseCapability->id,
                    'capability_key' =>
                        (string) $phaseCapability->capability_key,
                    'attempt' => $attempt,
                    'source_type' =>
                        TransformationImplementationRequestContract::SOURCE_TENANT_ADMIN,
                    'status' =>
                        TransformationImplementationRequestContract::STATUS_REQUESTED,
                    'source_snapshot' => [
                        'company' => [
                            'id' => (int) $company->id,
                            'name' => (string) $company->name,
                        ],
                        'assessment' => [
                            'id' => (int) $assessment->id,
                        ],
                        'plan' => [
                            'id' => (int) $plan->id,
                            'version' => (int) $plan->version,
                            'status' => (string) $plan->status,
                        ],
                        'phase' => [
                            'id' => (int) $phase->id,
                            'sequence' => (int) $phase->sequence,
                            'name' => (string) $phase->name,
                        ],
                        'capability' => [
                            'phase_capability_id' =>
                                (int) $phaseCapability->id,
                            'capability_key' =>
                                (string) $phaseCapability
                                    ->capability_key,
                            'label' =>
                                (string) $phaseCapability
                                    ->capability_label,
                            'kind' =>
                                (string) ($catalog['kind'] ?? ''),
                            'purpose' =>
                                $catalog['purpose'] ?? null,
                            'activation_policy' =>
                                $catalog['activation_policy'] ?? null,
                            'requires_lauda_review' =>
                                (bool) (
                                    $catalog[
                                        'requires_lauda_review'
                                    ] ?? false
                                ),
                        ],
                        'request_contract' => [
                            'request_is_activation' => false,
                            'definition_auto_create' => false,
                            'execution_started' => false,
                            'commercial_acceptance' => false,
                        ],
                    ],
                    'tenant_note' =>
                        $this->normalizeNullableText($tenantNote),
                    'requested_by_user_id' => $actor->id,
                    'status_changed_by_user_id' => $actor->id,
                    'requested_at' => now(),
                ]);

            $this->recordEvent(
                $request,
                null,
                TransformationImplementationRequestContract::STATUS_REQUESTED,
                self::ACTOR_TENANT_ADMIN,
                $actor,
                'request_created',
                $tenantNote,
                [
                    'attempt' => $attempt,
                ]
            );

            AuditService::log(
                'transformation_implementation_request_created',
                $request,
                [
                    'company_id' => (int) $company->id,
                    'assessment_id' => (int) $assessment->id,
                    'plan_id' => (int) $plan->id,
                    'phase_capability_id' =>
                        (int) $phaseCapability->id,
                    'capability_key' =>
                        (string) $phaseCapability->capability_key,
                    'attempt' => $attempt,
                    'actor_user_id' => (int) $actor->id,
                    'request_is_activation' => false,
                    'definition_created' => false,
                    'commercial_acceptance' => false,
                    'execution_started' => false,
                ]
            );

            return $request->fresh([
                'events',
            ]);
        }, 3);
    }

    public function transitionByLauda(
        TransformationImplementationRequest $request,
        string $targetStatus,
        User $actor,
        ?string $notes = null
    ): TransformationImplementationRequest {
        if (
            ! TransformationImplementationRequestContract::canLaudaTransition(
                (string) $request->status,
                $targetStatus
            )
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'Admin LAUDA no puede mover la solicitud de '
                    .$request->status
                    .' a '
                    .$targetStatus
                    .'.',
                ],
            ]);
        }

        return $this->transition(
            $request,
            $targetStatus,
            self::ACTOR_LAUDA_ADMIN,
            $actor,
            $notes
        );
    }

    public function transitionByTenant(
        TransformationImplementationRequest $request,
        string $targetStatus,
        User $actor,
        ?string $notes = null
    ): TransformationImplementationRequest {
        if (
            ! TransformationImplementationRequestContract::canTenantTransition(
                (string) $request->status,
                $targetStatus
            )
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'El tenant no puede mover la solicitud de '
                    .$request->status
                    .' a '
                    .$targetStatus
                    .'.',
                ],
            ]);
        }

        return $this->transition(
            $request,
            $targetStatus,
            self::ACTOR_TENANT_ADMIN,
            $actor,
            $notes
        );
    }

    public function assignTo(
        TransformationImplementationRequest $request,
        User $assignee,
        User $actor
    ): TransformationImplementationRequest {
        return DB::transaction(function () use (
            $request,
            $assignee,
            $actor
        ): TransformationImplementationRequest {
            $locked = TransformationImplementationRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'assigned_to_user_id' => $assignee->id,
                'status_changed_by_user_id' => $actor->id,
            ])->save();

            /*
             * La asignación no cambia el lifecycle,
             * pero sí forma parte del historial operativo
             * de la solicitud.
             */
            $this->recordEvent(
                $locked,
                (string) $locked->status,
                (string) $locked->status,
                self::ACTOR_LAUDA_ADMIN,
                $actor,
                'request_assigned',
                null,
                [
                    'assigned_to_user_id' =>
                        (int) $assignee->id,
                ]
            );

            AuditService::log(
                'transformation_implementation_request_assigned',
                $locked,
                [
                    'company_id' => (int) $locked->company_id,
                    'capability_key' =>
                        (string) $locked->capability_key,
                    'assigned_to_user_id' =>
                        (int) $assignee->id,
                    'actor_user_id' => (int) $actor->id,
                ]
            );

            return $locked->fresh();
        }, 3);
    }

    private function transition(
        TransformationImplementationRequest $request,
        string $targetStatus,
        string $actorType,
        User $actor,
        ?string $notes
    ): TransformationImplementationRequest {
        return DB::transaction(function () use (
            $request,
            $targetStatus,
            $actorType,
            $actor,
            $notes
        ): TransformationImplementationRequest {
            $locked = TransformationImplementationRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = (string) $locked->status;

            if ($fromStatus === $targetStatus) {
                return $locked->fresh([
                    'events',
                ]);
            }

            $allowed = $actorType === self::ACTOR_LAUDA_ADMIN
                ? TransformationImplementationRequestContract::canLaudaTransition(
                    $fromStatus,
                    $targetStatus
                )
                : TransformationImplementationRequestContract::canTenantTransition(
                    $fromStatus,
                    $targetStatus
                );

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'status' => [
                        'La transición solicitada no está permitida.',
                    ],
                ]);
            }

            $payload = [
                'status' => $targetStatus,
                'status_changed_by_user_id' => $actor->id,
            ];

            $timestampColumn =
                $this->timestampColumnFor($targetStatus);

            if ($timestampColumn !== null) {
                $payload[$timestampColumn] = now();
            }

            if (
                $targetStatus
                    === TransformationImplementationRequestContract::STATUS_CANCELLED
            ) {
                $payload['cancellation_reason'] =
                    $this->normalizeNullableText($notes);
            }

            $locked->forceFill($payload)->save();

            $this->recordEvent(
                $locked,
                $fromStatus,
                $targetStatus,
                $actorType,
                $actor,
                'status_transition',
                $notes
            );

            AuditService::log(
                'transformation_implementation_request_transitioned',
                $locked,
                [
                    'company_id' => (int) $locked->company_id,
                    'capability_key' =>
                        (string) $locked->capability_key,
                    'from_status' => $fromStatus,
                    'to_status' => $targetStatus,
                    'actor_type' => $actorType,
                    'actor_user_id' => (int) $actor->id,
                    'request_is_activation' => false,
                    'commercial_acceptance' => false,
                    'execution_started' => false,
                ]
            );

            return $locked->fresh([
                'events',
            ]);
        }, 3);
    }

    private function recordEvent(
        TransformationImplementationRequest $request,
        ?string $fromStatus,
        string $toStatus,
        string $actorType,
        User $actor,
        string $eventType,
        ?string $notes = null,
        ?array $metadata = null
    ): TransformationImplementationRequestEvent {
        return TransformationImplementationRequestEvent::query()
            ->create([
                'transformation_implementation_request_id' =>
                    $request->id,
                'event_type' => $eventType,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_type' => $actorType,
                'actor_user_id' => $actor->id,
                'notes' =>
                    $this->normalizeNullableText($notes),
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
    }

    private function assertRequestableContext(
        Company $company,
        DiagnosisAssessment $assessment,
        TransformationImplementationPlan $plan,
        TransformationImplementationPhaseCapability $phaseCapability
    ): array {
        $this->assertAssessmentCompany(
            $assessment,
            $company
        );

        if (
            (int) $plan->diagnosis_assessment_id
                !== (int) $assessment->id
            || $plan->status
                !== TransformationImplementationPlan::STATUS_PRESENTED
        ) {
            throw ValidationException::withMessages([
                'plan' => [
                    'La solicitud requiere un Plan de Implementación presentado y perteneciente al mismo Diagnóstico 360.',
                ],
            ]);
        }

        $phase = TransformationImplementationPhase::query()
            ->whereKey(
                $phaseCapability
                    ->transformation_implementation_phase_id
            )
            ->where(
                'transformation_implementation_plan_id',
                $plan->id
            )
            ->first();

        if (! $phase) {
            throw ValidationException::withMessages([
                'capability' => [
                    'La capacidad solicitada no pertenece al Plan de Implementación indicado.',
                ],
            ]);
        }

        $capabilityKey = trim(
            (string) $phaseCapability->capability_key
        );

        $catalog =
            TransformationProfessionalCapabilityCatalog::get(
                $capabilityKey
            );

        if (
            ! $catalog
            || ($catalog['kind'] ?? null)
                !== TransformationImplementationRequestContract::REQUIRED_CAPABILITY_KIND
            || ($catalog['activation_policy'] ?? null)
                !== TransformationImplementationRequestContract::REQUIRED_ACTIVATION_POLICY
        ) {
            throw ValidationException::withMessages([
                'capability' => [
                    'Solo una capacidad profesional implementation_only puede iniciar este flujo de solicitud.',
                ],
            ]);
        }

        return $catalog;
    }

    private function assertAssessmentCompany(
        DiagnosisAssessment $assessment,
        Company $company
    ): void {
        if (
            (int) ($assessment->organization_id ?? 0)
                === (int) $company->id
        ) {
            return;
        }

        $historicalCompanyLink =
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

        if ($historicalCompanyLink) {
            return;
        }

        throw ValidationException::withMessages([
            'assessment' => [
                'El Diagnóstico 360 no pertenece a la empresa indicada.',
            ],
        ]);
    }

    private function timestampColumnFor(
        string $status
    ): ?string {
        return match ($status) {
            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW =>
                'review_started_at',

            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION =>
                'definition_started_at',

            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW =>
                'tenant_review_requested_at',

            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED =>
                'changes_requested_at',

            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED =>
                'definition_agreed_at',

            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL =>
                'ready_for_commercial_at',

            TransformationImplementationRequestContract::STATUS_CANCELLED =>
                'cancelled_at',

            default => null,
        };
    }

    private function normalizeNullableText(
        ?string $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }
}
