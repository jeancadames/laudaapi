<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCommercialEngagement;
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

final class TransformationImplementationCommercialEngagementService
{
    public function __construct(
        private readonly SubscriberResolver $subscriberResolver,
        private readonly CompanyContextResolver $companyResolver,
        private readonly TenantAccessService $tenantAccessService
    ) {
    }

    private const READY_FOR_COMMERCIAL_EVENT =
        'request_ready_for_commercial_by_lauda';

    /**
     * Creates a request-scoped commercial draft.
     *
     * This action:
     * - requires LAUDA Admin;
     * - requires Request = ready_for_commercial;
     * - pins the exact Definition carried by the ready-for-commercial evidence;
     * - creates a new immutable commercial version in draft;
     * - does NOT present;
     * - does NOT accept;
     * - does NOT authorize implementation;
     * - does NOT modify Request or Definition;
     * - does NOT start execution.
     *
     * @param array{
     *     currency?: string,
     *     price_amount?: int|float|string|null,
     *     duration_days?: int|null,
     *     commercial_terms_snapshot?: array|null,
     *     internal_notes?: string|null
     * } $data
     */
    public function createDraft(
        TransformationImplementationRequest $request,
        User $actor,
        array $data = []
    ): TransformationImplementationCommercialEngagement {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $request,
                $actor,
                $data
            ): TransformationImplementationCommercialEngagement {
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

                $readyEvent =
                    $this->resolveReadyForCommercialEvidence(
                        $lockedRequest
                    );

                $metadata =
                    is_array(
                        $readyEvent->metadata
                    )
                        ? $readyEvent->metadata
                        : [];

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
                        'definition' => [
                            'La evidencia ready_for_commercial no identifica una Definition válida.',
                        ],
                    ]);
                }

                $definition =
                    TransformationImplementationDefinition::query()
                        ->whereKey(
                            $definitionId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertDefinitionContext(
                    $lockedRequest,
                    $definition,
                    $definitionVersion,
                    $metadata
                );

                $activeEngagement =
                    TransformationImplementationCommercialEngagement::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $lockedRequest->id
                        )
                        ->whereIn(
                            'status',
                            [
                                TransformationImplementationCommercialEngagement::STATUS_DRAFT,
                                TransformationImplementationCommercialEngagement::STATUS_PRESENTED,
                            ]
                        )
                        ->lockForUpdate()
                        ->first();

                if ($activeEngagement !== null) {
                    throw ValidationException::withMessages([
                        'commercial_engagement' => [
                            'Ya existe una propuesta comercial activa para esta solicitud.',
                        ],
                    ]);
                }

                $latestVersion =
                    (int) (
                        TransformationImplementationCommercialEngagement::query()
                            ->where(
                                'transformation_implementation_request_id',
                                $lockedRequest->id
                            )
                            ->lockForUpdate()
                            ->max(
                                'version'
                            )
                        ?? 0
                    );

                $version =
                    $latestVersion + 1;

                $currency =
                    strtoupper(
                        trim(
                            (string) (
                                $data['currency']
                                ?? 'DOP'
                            )
                        )
                    );

                if (
                    ! in_array(
                        $currency,
                        [
                            'DOP',
                            'USD',
                            'EUR',
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'currency' => [
                            'La moneda comercial debe ser DOP, USD o EUR.',
                        ],
                    ]);
                }

                $priceAmount =
                    $data['price_amount']
                    ?? null;

                if (
                    $priceAmount !== null
                    && (
                        ! is_numeric(
                            $priceAmount
                        )
                        || (float) $priceAmount < 0
                    )
                ) {
                    throw ValidationException::withMessages([
                        'price_amount' => [
                            'El precio comercial debe ser numérico y no negativo.',
                        ],
                    ]);
                }

                $durationDays =
                    $data['duration_days']
                    ?? null;

                if (
                    $durationDays !== null
                    && (
                        ! is_int(
                            $durationDays
                        )
                        || $durationDays <= 0
                    )
                ) {
                    throw ValidationException::withMessages([
                        'duration_days' => [
                            'La duración debe ser un entero mayor que cero.',
                        ],
                    ]);
                }

                $commercialTerms =
                    $data[
                        'commercial_terms_snapshot'
                    ]
                    ?? null;

                if (
                    $commercialTerms !== null
                    && ! is_array(
                        $commercialTerms
                    )
                ) {
                    throw ValidationException::withMessages([
                        'commercial_terms_snapshot' => [
                            'Los términos comerciales deben ser una estructura válida.',
                        ],
                    ]);
                }

                $contracted = app(
                    TransformationImplementationCommercialScopeService::class
                )->build($definition, $data);

                $engagement =
                    TransformationImplementationCommercialEngagement::query()
                        ->create([
                            'transformation_implementation_request_id' =>
                                $lockedRequest->id,

                            'transformation_implementation_definition_id' =>
                                $definition->id,

                            'company_id' =>
                                $lockedRequest->company_id,

                            'transformation_implementation_phase_capability_id' =>
                                $lockedRequest
                                    ->transformation_implementation_phase_capability_id,

                            'capability_key' =>
                                $lockedRequest->capability_key,

                            'version' =>
                                $version,

                            'status' =>
                                TransformationImplementationCommercialEngagement::STATUS_DRAFT,

                            'currency' =>
                                $currency,

                            'price_amount' =>
                                $priceAmount,

                            'duration_days' =>
                                $durationDays,

                            /*
                             * Functional snapshots copied from the exact
                             * Definition pinned by the commercial boundary.
                             *
                             * They are commercial evidence snapshots.
                             * The Definition itself remains untouched.
                             */
                            'scope_snapshot' =>
                                $definition->implementation_scope,

                            'deliverables_snapshot' =>
                                $definition->deliverables,

                            'contract_scope_schema_version' => 1,
                            'contracted_scope_snapshot' =>
                                $contracted['contracted_scope_snapshot'],
                            'contracted_deliverables_snapshot' =>
                                $contracted['contracted_deliverables_snapshot'],
                            'commercial_terms_snapshot' =>
                                $commercialTerms,

                            'internal_notes' =>
                                $data['internal_notes']
                                ?? null,

                            'created_by_user_id' =>
                                $actor->id,

                            'updated_by_user_id' =>
                                $actor->id,
                        ]);

                AuditService::log(
                    'transformation_implementation_commercial_engagement_draft_created',
                    $engagement,
                    [
                        'request_id' =>
                            (int) $lockedRequest->id,

                        'commercial_engagement_id' =>
                            (int) $engagement->id,

                        'commercial_engagement_version' =>
                            (int) $engagement->version,

                        'definition_id' =>
                            (int) $definition->id,

                        'definition_version' =>
                            (int) $definition->version,

                        'ready_for_commercial_event_id' =>
                            (int) $readyEvent->id,

                        'company_id' =>
                            (int) $lockedRequest->company_id,

                        'phase_capability_id' =>
                            (int) $lockedRequest
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $lockedRequest->capability_key,

                        'status' =>
                            TransformationImplementationCommercialEngagement::STATUS_DRAFT,

                        'presented' =>
                            false,

                        'commercial_acceptance' =>
                            false,

                        'implementation_authorized' =>
                            false,

                        'execution_started' =>
                            false,

                        'actor_user_id' =>
                            (int) $actor->id,
                    ]
                );

                return $engagement->fresh([
                    'request',
                    'definition',
                ]) ?? $engagement;
            },
            3
        );
    }

    /**
     * Presents an existing commercial draft to the tenant.
     *
     * Presentation freezes the commercial version at the application
     * lifecycle level.
     *
     * This action:
     * - requires LAUDA Admin;
     * - requires an exact draft engagement;
     * - requires Request = ready_for_commercial;
     * - revalidates the exact pinned Definition;
     * - requires materially complete commercial terms;
     * - marks only the engagement as presented;
     * - does NOT accept commercially;
     * - does NOT authorize implementation;
     * - does NOT mutate Request or Definition;
     * - does NOT start execution.
     */
    public function present(
        TransformationImplementationCommercialEngagement $engagement,
        User $actor
    ): TransformationImplementationCommercialEngagement {
        $this->assertLaudaAdmin(
            $actor
        );

        return DB::transaction(
            function () use (
                $engagement,
                $actor
            ): TransformationImplementationCommercialEngagement {
                $lockedEngagement =
                    TransformationImplementationCommercialEngagement::query()
                        ->whereKey(
                            $engagement->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedEngagement->status
                    !== TransformationImplementationCommercialEngagement::STATUS_DRAFT
                ) {
                    throw ValidationException::withMessages([
                        'commercial_engagement' => [
                            'Solo una propuesta comercial en draft puede ser presentada.',
                        ],
                    ]);
                }

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
                        $metadata[
                            'definition_version'
                        ]
                        ?? 0
                    );

                if (
                    $expectedDefinitionVersion <= 0
                ) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'La evidencia ready_for_commercial no contiene una versión de Definition válida.',
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

                $this->assertPresentableCommercialTerms(
                    $lockedEngagement,
                    $definition
                );

                $existingAuthorization =
                    DB::table(
                        'transformation_implementation_authorizations'
                    )
                        ->where(
                            'transformation_implementation_commercial_engagement_id',
                            $lockedEngagement->id
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($existingAuthorization) {
                    throw ValidationException::withMessages([
                        'commercial_engagement' => [
                            'La propuesta comercial ya tiene evidencia de autorización de implementación.',
                        ],
                    ]);
                }

                $lockedEngagement->forceFill([
                    'status' =>
                        TransformationImplementationCommercialEngagement::STATUS_PRESENTED,

                    'presented_by_user_id' =>
                        $actor->id,

                    'presented_at' =>
                        now(),

                    'updated_by_user_id' =>
                        $actor->id,
                ])->save();

                AuditService::log(
                    'transformation_implementation_commercial_engagement_presented',
                    $lockedEngagement,
                    [
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

                        'ready_for_commercial_event_id' =>
                            (int) $readyEvent->id,

                        'company_id' =>
                            (int) $request->company_id,

                        'phase_capability_id' =>
                            (int) $request
                                ->transformation_implementation_phase_capability_id,

                        'capability_key' =>
                            (string) $request->capability_key,

                        'status' =>
                            TransformationImplementationCommercialEngagement::STATUS_PRESENTED,

                        'price_amount' =>
                            (string) $lockedEngagement->price_amount,

                        'currency' =>
                            (string) $lockedEngagement->currency,

                        'duration_days' =>
                            (int) $lockedEngagement->duration_days,

                        'commercial_acceptance' =>
                            false,

                        'implementation_authorized' =>
                            false,

                        'execution_started' =>
                            false,

                        'actor_user_id' =>
                            (int) $actor->id,
                    ]
                );

                return $lockedEngagement->fresh([
                    'request',
                    'definition',
                ]) ?? $lockedEngagement;
            },
            3
        );
    }

    /**
     * Tenant explicitly accepts a presented commercial engagement.
     *
     * Commercial acceptance:
     * - requires an authorized Tenant Admin for the same Company;
     * - requires engagement = presented;
     * - revalidates Request = ready_for_commercial;
     * - revalidates the exact pinned Definition;
     * - preserves the frozen commercial material;
     * - does NOT authorize implementation;
     * - does NOT mutate Request or Definition;
     * - does NOT start execution;
     * - does NOT touch Canonical.
     */
    public function accept(
        TransformationImplementationCommercialEngagement $engagement,
        User $actor
    ): TransformationImplementationCommercialEngagement {
        return DB::transaction(
            function () use (
                $engagement,
                $actor
            ): TransformationImplementationCommercialEngagement {
                $lockedEngagement =
                    TransformationImplementationCommercialEngagement::query()
                        ->whereKey(
                            $engagement->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedEngagement->status
                    !== TransformationImplementationCommercialEngagement::STATUS_PRESENTED
                ) {
                    throw ValidationException::withMessages([
                        'commercial_engagement' => [
                            'Solo una propuesta comercial presentada puede ser aceptada.',
                        ],
                    ]);
                }

                if (
                    $lockedEngagement->presented_at
                    === null
                    || $lockedEngagement->presented_by_user_id
                    === null
                ) {
                    throw ValidationException::withMessages([
                        'commercial_engagement' => [
                            'La propuesta no contiene evidencia completa de presentación.',
                        ],
                    ]);
                }

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

                $this->assertTenantAdminForCompany(
                    $actor,
                    (int) $request->company_id
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
                        $metadata[
                            'definition_version'
                        ]
                        ?? 0
                    );

                if (
                    $expectedDefinitionVersion <= 0
                ) {
                    throw ValidationException::withMessages([
                        'definition' => [
                            'La evidencia ready_for_commercial no contiene una versión de Definition válida.',
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

                $this->assertPresentableCommercialTerms(
                    $lockedEngagement,
                    $definition
                );

                $existingAuthorization =
                    DB::table(
                        'transformation_implementation_authorizations'
                    )
                        ->where(
                            'transformation_implementation_commercial_engagement_id',
                            $lockedEngagement->id
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($existingAuthorization) {
                    throw ValidationException::withMessages([
                        'commercial_engagement' => [
                            'La propuesta ya tiene una autorización de implementación y no puede aceptar nuevamente el boundary comercial.',
                        ],
                    ]);
                }

                $lockedEngagement->forceFill([
                    'status' =>
                        TransformationImplementationCommercialEngagement::STATUS_ACCEPTED,

                    'accepted_by_user_id' =>
                        $actor->id,

                    'accepted_at' =>
                        now(),

                    'updated_by_user_id' =>
                        $actor->id,
                ])->save();

                AuditService::log(
                    'transformation_implementation_commercial_engagement_accepted_by_tenant',
                    $lockedEngagement,
                    [
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

                        'status' =>
                            TransformationImplementationCommercialEngagement::STATUS_ACCEPTED,

                        'commercial_acceptance' =>
                            true,

                        'implementation_authorized' =>
                            false,

                        'execution_started' =>
                            false,

                        'actor_user_id' =>
                            (int) $actor->id,
                    ]
                );

                return $lockedEngagement->fresh([
                    'request',
                    'definition',
                ]) ?? $lockedEngagement;
            },
            3
        );
    }

    private function assertTenantAdminForCompany(
        User $actor,
        int $companyId
    ): void {
        /*
         * Exact tenant boundary already used by the Definition
         * tenant-decision lifecycle.
         *
         * Merely belonging to a Subscriber is not enough:
         *
         * - actor must be a subscriber identity;
         * - SubscriberResolver must resolve the active tenant;
         * - TenantAccessService must resolve SUBSCRIBER_ADMIN;
         * - tenant_admin must be true;
         * - CompanyContextResolver must resolve the exact Company.
         *
         * LAUDA platform admins intentionally cannot commercially
         * accept on behalf of the tenant.
         */
        if (
            ($actor->role ?? null)
            !== 'subscriber'
        ) {
            throw new AuthorizationException(
                'La aceptación comercial requiere un Tenant Admin.'
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
                'La aceptación comercial requiere permisos de administrador de la empresa.'
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
            !== $companyId

            || (int) ($company->subscriber_id ?? 0)
            !== $subscriberId
        ) {
            throw new AuthorizationException(
                'La propuesta comercial no pertenece a la empresa del usuario.'
            );
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
                    'La propuesta comercial no corresponde exactamente a la solicitud y Definition pinneadas.',
                ],
            ]);
        }
    }

    private function assertPresentableCommercialTerms(
        TransformationImplementationCommercialEngagement $engagement,
        TransformationImplementationDefinition $definition
    ): void {
        if ($engagement->contract_scope_schema_version === null) {
            // Historical commercial versions must not contain half-populated V1 fields.
            if ($engagement->contracted_scope_snapshot !== null
                || $engagement->contracted_deliverables_snapshot !== null) {
                throw ValidationException::withMessages([
                    'contracted_scope_snapshot' => ['La propuesta histórica tiene evidencia contractual inconsistente.'],
                ]);
            }
        } elseif ((int) $engagement->contract_scope_schema_version === 1) {
            app(TransformationImplementationCommercialScopeService::class)
                ->assertPersistedContract($engagement, $definition);
        } else {
            throw ValidationException::withMessages([
                'contract_scope_schema_version' => ['Versión de contrato comercial no soportada.'],
            ]);
        }

        if (
            $engagement->price_amount === null

            || ! is_numeric(
                $engagement->price_amount
            )

            || (float) $engagement->price_amount < 0
        ) {
            throw ValidationException::withMessages([
                'price_amount' => [
                    'La propuesta debe tener un precio comercial definido antes de presentarse.',
                ],
            ]);
        }

        if (
            ! in_array(
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
        ) {
            throw ValidationException::withMessages([
                'currency' => [
                    'La propuesta debe tener una moneda comercial válida antes de presentarse.',
                ],
            ]);
        }

        if (
            $engagement->duration_days === null

            || (int) $engagement->duration_days <= 0
        ) {
            throw ValidationException::withMessages([
                'duration_days' => [
                    'La propuesta debe tener una duración válida antes de presentarse.',
                ],
            ]);
        }

        if (
            ! is_array(
                $engagement->scope_snapshot
            )
            || $engagement->scope_snapshot === []
        ) {
            throw ValidationException::withMessages([
                'scope_snapshot' => [
                    'La propuesta debe contener el alcance funcional pinneado.',
                ],
            ]);
        }

        if (
            ! is_array(
                $engagement->deliverables_snapshot
            )
            || $engagement->deliverables_snapshot === []
        ) {
            throw ValidationException::withMessages([
                'deliverables_snapshot' => [
                    'La propuesta debe contener entregables definidos.',
                ],
            ]);
        }

        if (
            ! is_array(
                $engagement->commercial_terms_snapshot
            )
            || $engagement->commercial_terms_snapshot === []
        ) {
            throw ValidationException::withMessages([
                'commercial_terms_snapshot' => [
                    'La propuesta debe contener términos comerciales antes de presentarse.',
                ],
            ]);
        }
    }

    private function assertLaudaAdmin(
        User $actor
    ): void {
        if (
            ($actor->role ?? null)
            !== 'admin'
        ) {
            throw new AuthorizationException(
                'La creación de la propuesta comercial requiere un Admin LAUDA.'
            );
        }
    }

    private function assertRequestState(
        TransformationImplementationRequest $request
    ): void {
        if (
            $request->status
            !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud debe estar en ready_for_commercial antes de crear una propuesta comercial.',
                ],
            ]);
        }

        if (
            $request->ready_for_commercial_at
            === null
        ) {
            throw ValidationException::withMessages([
                'request' => [
                    'La solicitud no contiene evidencia temporal del gate ready_for_commercial.',
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
                ->orderByDesc(
                    'id'
                )
                ->lockForUpdate()
                ->get();

        if (
            $events->count()
            !== 1
        ) {
            throw ValidationException::withMessages([
                'commercial_boundary' => [
                    'La solicitud debe tener una única evidencia exacta ready_for_commercial.',
                ],
            ]);
        }

        /** @var TransformationImplementationRequestEvent $event */
        $event =
            $events->first();

        $metadata =
            is_array(
                $event->metadata
            )
                ? $event->metadata
                : [];

        if (
            $event->to_status
            !== TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL

            || $event->actor_type
            !== TransformationImplementationRequestService::ACTOR_LAUDA_ADMIN

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
                    'functional_definition_ready'
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
                    'commercial_acceptance'
                ]
                ?? null
            ) !== false

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
        ) {
            throw ValidationException::withMessages([
                'commercial_boundary' => [
                    'La evidencia ready_for_commercial no corresponde exactamente a esta solicitud.',
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
                $metadata[
                    'definition_id'
                ]
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
                    'La Definition comercial no corresponde exactamente a esta solicitud.',
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
                    'La Definition pinneada no conserva el cierre funcional requerido.',
                ],
            ]);
        }
    }
}
