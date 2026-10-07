<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationAuthorization;
use App\Models\TransformationImplementationCommercialEngagement;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransformationImplementationAuthorizationService
{
    private const READY_FOR_COMMERCIAL_EVENT =
        'request_ready_for_commercial_by_lauda';

    /**
     * LAUDA explicitly authorizes technical implementation of one exact
     * commercially accepted professional-service engagement.
     *
     * Authorization is a permission boundary only.
     *
     * It does NOT:
     * - change Request;
     * - change Definition;
     * - change Engagement;
     * - set ready_for_execution;
     * - set execution_started;
     * - start historical execution;
     * - mutate Canonical;
     * - create subscription, invoice or payment artifacts.
     */
    public function authorize(
        TransformationImplementationCommercialEngagement $engagement,
        User $actor
    ): TransformationImplementationAuthorization {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $engagement,
                $actor
            ): TransformationImplementationAuthorization {
                $lockedEngagement =
                    TransformationImplementationCommercialEngagement::query()
                        ->whereKey(
                            $engagement->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertAcceptedEngagement(
                    $lockedEngagement
                );

                $request =
                    TransformationImplementationRequest::query()
                        ->whereKey(
                            $lockedEngagement
                                ->transformation_implementation_request_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertRequestState(
                    $request
                );

                $readyEvent =
                    $this->resolveReadyForCommercialEvidence(
                        $request
                    );

                $metadata =
                    is_array(
                        $readyEvent->metadata
                    )
                        ? $readyEvent->metadata
                        : [];

                $definition =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $lockedEngagement
                                ->transformation_implementation_definition_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $expectedDefinitionVersion =
                    (int) (
                        $metadata['definition_version']
                        ?? 0
                    );

                if (
                    $expectedDefinitionVersion <= 0
                ) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'La evidencia ready_for_commercial no contiene una versión válida de Definition.',
                        ],
                    ]);
                }

                $this->assertDefinitionContext(
                    $request,
                    $definition,
                    $expectedDefinitionVersion,
                    $metadata
                );

                $this->assertEngagementContext(
                    $lockedEngagement,
                    $request,
                    $definition
                );

                $this->assertCommercialEvidence(
                    $lockedEngagement
                );

                /*
                 * One authorization aggregate per immutable commercial
                 * engagement version.
                 *
                 * A revoked authorization remains the historical
                 * authorization aggregate for that exact engagement.
                 */
                $existing =
                    TransformationImplementationAuthorization::query()
                        ->where(
                            'transformation_implementation_commercial_engagement_id',
                            $lockedEngagement->id
                        )
                        ->lockForUpdate()
                        ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'implementation_authorization' => [
                            'Esta versión comercial ya posee un registro de autorización de implementación.',
                        ],
                    ]);
                }

                $snapshot = [
                    'request' => [
                        'id' =>
                            (int) $request->id,

                        'status' =>
                            (string) $request->status,

                        'ready_for_commercial_at' =>
                            $request->ready_for_commercial_at
                                ?->toIso8601String(),
                    ],

                    'ready_for_commercial_evidence' => [
                        'event_id' =>
                            (int) $readyEvent->id,

                        'event_type' =>
                            (string) $readyEvent->event_type,

                        'actor_type' =>
                            (string) $readyEvent->actor_type,

                        'actor_user_id' =>
                            $readyEvent->actor_user_id !== null
                                ? (int) $readyEvent->actor_user_id
                                : null,

                        'occurred_at' =>
                            $readyEvent->occurred_at
                                ?->toIso8601String(),

                        'definition_id' =>
                            (int) $definition->id,

                        'definition_version' =>
                            (int) $definition->version,
                    ],

                    'definition' => [
                        'id' =>
                            (int) $definition->id,

                        'version' =>
                            (int) $definition->version,

                        'status' =>
                            (string) $definition->status,

                        'definition_ready' =>
                            data_get(
                                $definition->readiness,
                                'definition_ready'
                            ) === true,

                        'technical_readiness' =>
                            data_get(
                                $definition->readiness,
                                'technical_readiness'
                            ) === true,

                        'ready_for_execution' =>
                            data_get(
                                $definition->readiness,
                                'ready_for_execution'
                            ) === true,

                        'execution_started' =>
                            data_get(
                                $definition->readiness,
                                'execution_started'
                            ) === true,

                        'ready_at' =>
                            $definition->ready_at
                                ?->toIso8601String(),
                    ],

                    'commercial_engagement' => [
                        'id' =>
                            (int) $lockedEngagement->id,

                        'version' =>
                            (int) $lockedEngagement->version,

                        'status' =>
                            (string) $lockedEngagement->status,

                        'currency' =>
                            (string) $lockedEngagement->currency,

                        'price_amount' =>
                            (string) $lockedEngagement->price_amount,

                        'duration_days' =>
                            (int) $lockedEngagement->duration_days,

                        'scope_snapshot' =>
                            $lockedEngagement->scope_snapshot,

                        'deliverables_snapshot' =>
                            $lockedEngagement->deliverables_snapshot,

                        'commercial_terms_snapshot' =>
                            $lockedEngagement->commercial_terms_snapshot,

                        'presented_by_user_id' =>
                            (int) $lockedEngagement->presented_by_user_id,

                        'presented_at' =>
                            $lockedEngagement->presented_at
                                ?->toIso8601String(),

                        'accepted_by_user_id' =>
                            (int) $lockedEngagement->accepted_by_user_id,

                        'accepted_at' =>
                            $lockedEngagement->accepted_at
                                ?->toIso8601String(),
                    ],

                    'scope' => [
                        'company_id' =>
                            (int) $request->company_id,

                        'phase_capability_id' =>
                            (int) $request
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $request->capability_key,
                    ],

                    'authorization_boundary' => [
                        'commercial_acceptance' =>
                            true,

                        'implementation_authorized' =>
                            true,

                        'ready_for_execution_mutated' =>
                            false,

                        'execution_started' =>
                            false,

                        'canonical_write' =>
                            false,
                    ],
                ];

                $authorization =
                    TransformationImplementationAuthorization::query()
                        ->create([
                            'transformation_implementation_commercial_engagement_id' =>
                                $lockedEngagement->id,

                            'transformation_implementation_request_id' =>
                                $request->id,

                            'transformation_implementation_definition_id' =>
                                $definition->id,

                            'company_id' =>
                                $request->company_id,

                            'transformation_implementation_phase_capability_id' =>
                                $request
                                    ->transformation_implementation_phase_capability_id,

                            'capability_key' =>
                                $request->capability_key,

                            'status' =>
                                TransformationImplementationAuthorization::STATUS_AUTHORIZED,

                            'authorization_snapshot' =>
                                $snapshot,

                            'authorized_by_user_id' =>
                                $actor->id,

                            'authorized_at' =>
                                now(),
                        ]);

                AuditService::log(
                    'transformation_implementation_authorized_by_lauda',
                    $authorization,
                    [
                        'authorization_id' =>
                            (int) $authorization->id,

                        'request_id' =>
                            (int) $request->id,

                        'commercial_engagement_id' =>
                            (int) $lockedEngagement->id,

                        'commercial_engagement_version' =>
                            (int) $lockedEngagement->version,

                        'definition_id' =>
                            (int) $definition->id,

                        'definition_version' =>
                            (int) $definition->version,

                        'company_id' =>
                            (int) $request->company_id,

                        'phase_capability_id' =>
                            (int) $request
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $request->capability_key,

                        'commercial_acceptance' =>
                            true,

                        'implementation_authorized' =>
                            true,

                        'ready_for_execution_mutated' =>
                            false,

                        'execution_started' =>
                            false,

                        'canonical_write' =>
                            false,

                        'actor_user_id' =>
                            (int) $actor->id,
                    ]
                );

                return $authorization->fresh([
                    'commercialEngagement',
                    'request',
                    'definition',
                    'company',
                    'phaseCapability',
                    'authorizedBy',
                ]) ?? $authorization;
            },
            3
        );
    }

    private function assertLaudaAdmin(
        User $actor
    ): void {
        if (
            (string) ($actor->role ?? '')
            !== 'admin'
        ) {
            throw new AuthorizationException(
                'La autorización de implementación requiere un Admin LAUDA.'
            );
        }
    }

    private function assertAcceptedEngagement(
        TransformationImplementationCommercialEngagement $engagement
    ): void {
        if (
            $engagement->status
            !== TransformationImplementationCommercialEngagement::STATUS_ACCEPTED

            || $engagement->accepted_at
            === null

            || $engagement->accepted_by_user_id
            === null

            || $engagement->presented_at
            === null

            || $engagement->presented_by_user_id
            === null
        ) {
            throw ValidationException::withMessages([
                'commercial_engagement' => [
                    'La implementación solo puede autorizarse para una propuesta presentada y aceptada explícitamente por el tenant.',
                ],
            ]);
        }
    }

    private function assertRequestState(
        TransformationImplementationRequest $request
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL

            || $request->ready_for_commercial_at
            === null
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud debe conservar el estado ready_for_commercial antes de autorizar implementación.',
                ],
            ]);
        }
    }

    private function resolveReadyForCommercialEvidence(
        TransformationImplementationRequest $request
    ): TransformationImplementationRequestEvent {
        $events =
            TransformationImplementationRequestEvent::query()
                ->where(
                    'transformation_implementation_request_id',
                    $request->id
                )
                ->where(
                    'event_type',
                    self::READY_FOR_COMMERCIAL_EVENT
                )
                ->lockForUpdate()
                ->get();

        if ($events->count() !== 1) {
            throw ValidationException::withMessages([
                'commercial_boundary' => [
                    'Debe existir exactamente una evidencia ready_for_commercial de LAUDA para esta solicitud.',
                ],
            ]);
        }

        $event =
            $events->first();

        $metadata =
            is_array($event->metadata)
                ? $event->metadata
                : [];

        if (
            $event->to_status
            !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL

            || $event->actor_type
            !== TransformationImplementationRequestService::ACTOR_LAUDA_ADMIN

            || (int) (
                $metadata['request_id']
                ?? 0
            ) !== (int) $request->id

            || (int) (
                $metadata['company_id']
                ?? 0
            ) !== (int) $request->company_id

            || (int) (
                $metadata['phase_capability_id']
                ?? 0
            ) !== (int) $request
                ->transformation_implementation_phase_capability_id

            || trim(
                (string) (
                    $metadata['capability_key']
                    ?? ''
                )
            ) !== trim(
                (string) $request->capability_key
            )

            || (
                $metadata['definition_ready']
                ?? null
            ) !== true

            || (
                $metadata['technical_readiness']
                ?? null
            ) !== true

            || (
                $metadata['commercial_acceptance']
                ?? null
            ) !== false

            || (
                $metadata['ready_for_execution']
                ?? null
            ) !== false

            || (
                $metadata['execution_started']
                ?? null
            ) !== false
        ) {
            throw ValidationException::withMessages([
                'commercial_boundary' => [
                    'La evidencia ready_for_commercial no corresponde exactamente a la solicitud autorizada.',
                ],
            ]);
        }

        return $event;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function assertDefinitionContext(
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition,
        int $expectedVersion,
        array $metadata
    ): void {
        if (
            (int) $definition->id
            !== (int) (
                $metadata['definition_id']
                ?? 0
            )

            || (int) $definition->version
            !== $expectedVersion

            || (int) $definition
                ->transformation_implementation_request_id
            !== (int) $request->id

            || (int) $definition->company_id
            !== (int) $request->company_id

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
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition de autorización no corresponde exactamente a la solicitud.',
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

            || $definition->ready_at
            === null
        ) {
            throw ValidationException::withMessages([
                'definition' => [
                    'La Definition pinneada no conserva el cierre funcional previo a autorización.',
                ],
            ]);
        }
    }

    private function assertEngagementContext(
        TransformationImplementationCommercialEngagement $engagement,
        TransformationImplementationRequest $request,
        TransformationImplementationDefinition $definition
    ): void {
        if (
            (int) $engagement
                ->transformation_implementation_request_id
            !== (int) $request->id

            || (int) $engagement
                ->transformation_implementation_definition_id
            !== (int) $definition->id

            || (int) $engagement->company_id
            !== (int) $request->company_id

            || (int) $engagement
                ->transformation_implementation_phase_capability_id
            !== (int) $request
                ->transformation_implementation_phase_capability_id

            || trim(
                (string) $engagement->capability_key
            ) !== trim(
                (string) $request->capability_key
            )
        ) {
            throw ValidationException::withMessages([
                'commercial_engagement' => [
                    'La propuesta aceptada no corresponde exactamente al Request y Definition autorizados.',
                ],
            ]);
        }
    }

    private function assertCommercialEvidence(
        TransformationImplementationCommercialEngagement $engagement
    ): void {
        if (
            $engagement->price_amount === null

            || ! is_numeric(
                $engagement->price_amount
            )

            || (float) $engagement->price_amount < 0

            || ! in_array(
                strtoupper(
                    trim(
                        (string) $engagement->currency
                    )
                ),
                [
                    'DOP',
                    'USD',
                    'EUR',
                ],
                true
            )

            || $engagement->duration_days === null

            || (int) $engagement->duration_days <= 0

            || ! is_array(
                $engagement->scope_snapshot
            )

            || $engagement->scope_snapshot === []

            || ! is_array(
                $engagement->deliverables_snapshot
            )

            || $engagement->deliverables_snapshot === []

            || ! is_array(
                $engagement->commercial_terms_snapshot
            )

            || $engagement->commercial_terms_snapshot === []
        ) {
            throw ValidationException::withMessages([
                'commercial_engagement' => [
                    'La propuesta aceptada no conserva evidencia comercial completa para autorizar implementación.',
                ],
            ]);
        }
    }
}
