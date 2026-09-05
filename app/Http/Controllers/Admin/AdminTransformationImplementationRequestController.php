<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminTransformationImplementationRequestController
    extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $status = trim(
            (string) $request->query(
                'status',
                ''
            )
        );

        $capability = trim(
            (string) $request->query(
                'capability',
                ''
            )
        );

        $search = trim(
            (string) $request->query(
                'search',
                ''
            )
        );

        $query =
            DB::table(
                'transformation_implementation_requests as request'
            )
                ->leftJoin(
                    'companies as company',
                    'company.id',
                    '=',
                    'request.company_id'
                )
                ->leftJoin(
                    'users as requested_by',
                    'requested_by.id',
                    '=',
                    'request.requested_by_user_id'
                )
                ->leftJoin(
                    'users as assigned_to',
                    'assigned_to.id',
                    '=',
                    'request.assigned_to_user_id'
                )
                ->leftJoin(
                    'transformation_implementation_phase_capabilities as capability',
                    'capability.id',
                    '=',
                    'request.transformation_implementation_phase_capability_id'
                )
                ->select([
                    'request.id',
                    'request.company_id',
                    'request.diagnosis_assessment_id',
                    'request.transformation_implementation_plan_id',
                    'request.transformation_implementation_phase_capability_id',
                    'request.capability_key',
                    'request.attempt',
                    'request.status',
                    'request.tenant_note',
                    'request.requested_at',
                    'request.review_started_at',
                    'request.definition_started_at',
                    'request.tenant_review_requested_at',
                    'request.changes_requested_at',
                    'request.definition_agreed_at',
                    'request.ready_for_commercial_at',
                    'request.cancelled_at',
                    'company.name as company_name',
                    'requested_by.name as requested_by_name',
                    'requested_by.email as requested_by_email',
                    'assigned_to.name as assigned_to_name',
                    'assigned_to.email as assigned_to_email',
                    'capability.capability_label',
                ]);

        if (
            $status !== ''
            && in_array(
                $status,
                TransformationImplementationRequestContract::STATUSES,
                true
            )
        ) {
            $query->where(
                'request.status',
                $status
            );
        }

        if ($capability !== '') {
            $query->where(
                'request.capability_key',
                $capability
            );
        }

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search): void {
                    $like =
                        '%'
                        .$search
                        .'%';

                    $builder
                        ->where(
                            'company.name',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'request.capability_key',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'capability.capability_label',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'requested_by.name',
                            'like',
                            $like
                        )
                        ->orWhere(
                            'requested_by.email',
                            'like',
                            $like
                        );
                }
            );
        }

        $rows =
            $query
                ->orderByRaw(
                    "
                    CASE request.status
                        WHEN 'requested' THEN 1
                        WHEN 'under_lauda_review' THEN 2
                        WHEN 'changes_requested' THEN 3
                        WHEN 'definition_preparation' THEN 4
                        WHEN 'awaiting_tenant_review' THEN 5
                        WHEN 'definition_agreed' THEN 6
                        WHEN 'ready_for_commercial' THEN 7
                        WHEN 'cancelled' THEN 8
                        ELSE 9
                    END
                    "
                )
                ->orderByDesc(
                    'request.requested_at'
                )
                ->orderByDesc(
                    'request.id'
                )
                ->get()
                ->map(
                    fn ($row): array =>
                        $this->queueRow(
                            $row
                        )
                )
                ->values()
                ->all();

        $counts =
            DB::table(
                'transformation_implementation_requests'
            )
                ->selectRaw(
                    'status, COUNT(*) as total'
                )
                ->groupBy(
                    'status'
                )
                ->pluck(
                    'total',
                    'status'
                )
                ->map(
                    fn ($value): int =>
                        (int) $value
                )
                ->all();

        $capabilities =
            DB::table(
                'transformation_implementation_requests as request'
            )
                ->leftJoin(
                    'transformation_implementation_phase_capabilities as capability',
                    'capability.id',
                    '=',
                    'request.transformation_implementation_phase_capability_id'
                )
                ->select([
                    'request.capability_key',
                    'capability.capability_label',
                ])
                ->distinct()
                ->orderBy(
                    'request.capability_key'
                )
                ->get()
                ->map(
                    fn ($row): array => [
                        'key' =>
                            (string) $row->capability_key,

                        'label' =>
                            (string) (
                                $row->capability_label
                                ?: $this->capabilityLabel(
                                    (string) $row->capability_key
                                )
                            ),
                    ]
                )
                ->values()
                ->all();

        return Inertia::render(
            'Admin/Transformation360/ImplementationRequests/Index',
            [
                'requests' =>
                    $rows,

                'filters' => [
                    'status' =>
                        $status !== ''
                            ? $status
                            : null,

                    'capability' =>
                        $capability !== ''
                            ? $capability
                            : null,

                    'search' =>
                        $search !== ''
                            ? $search
                            : null,
                ],

                'status_options' =>
                    $this->statusOptions(),

                'capability_options' =>
                    $capabilities,

                'summary' => [
                    'total' =>
                        array_sum(
                            $counts
                        ),

                    'requested' =>
                        (int) (
                            $counts[
                                TransformationImplementationRequestContract::STATUS_REQUESTED
                            ]
                            ?? 0
                        ),

                    'under_review' =>
                        (int) (
                            $counts[
                                TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW
                            ]
                            ?? 0
                        ),

                    'definition' =>
                        (int) (
                            $counts[
                                TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                            ]
                            ?? 0
                        ),

                    'awaiting_tenant' =>
                        (int) (
                            $counts[
                                TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                            ]
                            ?? 0
                        ),
                ],
            ]
        );
    }

    public function show(
        Request $request,
        TransformationImplementationRequest $implementationRequest
    ): Response {
        $this->authorizeAdmin($request);

        $context =
            DB::table(
                'transformation_implementation_requests as request'
            )
                ->leftJoin(
                    'companies as company',
                    'company.id',
                    '=',
                    'request.company_id'
                )
                ->leftJoin(
                    'diagnosis_assessments as assessment',
                    'assessment.id',
                    '=',
                    'request.diagnosis_assessment_id'
                )
                ->leftJoin(
                    'transformation_implementation_plans as plan',
                    'plan.id',
                    '=',
                    'request.transformation_implementation_plan_id'
                )
                ->leftJoin(
                    'transformation_implementation_phase_capabilities as capability',
                    'capability.id',
                    '=',
                    'request.transformation_implementation_phase_capability_id'
                )
                ->leftJoin(
                    'transformation_implementation_phases as phase',
                    'phase.id',
                    '=',
                    'capability.transformation_implementation_phase_id'
                )
                ->leftJoin(
                    'users as requested_by',
                    'requested_by.id',
                    '=',
                    'request.requested_by_user_id'
                )
                ->leftJoin(
                    'users as assigned_to',
                    'assigned_to.id',
                    '=',
                    'request.assigned_to_user_id'
                )
                ->where(
                    'request.id',
                    $implementationRequest->id
                )
                ->select([
                    'request.*',
                    'company.name as company_name',
                    'company.slug as company_slug',
                    'company.subscriber_id',
                    'assessment.organization_name',
                    'assessment.status as assessment_status',
                    'assessment.published_at as assessment_published_at',
                    'plan.version as plan_version',
                    'plan.status as plan_status',
                    'plan.presented_at as plan_presented_at',
                    'phase.id as phase_id',
                    'phase.sequence as phase_sequence',
                    'phase.name as phase_name',
                    'capability.capability_label',
                    'capability.capability_summary',
                    'requested_by.name as requested_by_name',
                    'requested_by.email as requested_by_email',
                    'assigned_to.name as assigned_to_name',
                    'assigned_to.email as assigned_to_email',
                ])
                ->firstOrFail();

        $events =
            DB::table(
                'transformation_implementation_request_events as event'
            )
                ->leftJoin(
                    'users as actor',
                    'actor.id',
                    '=',
                    'event.actor_user_id'
                )
                ->where(
                    'event.transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->select([
                    'event.id',
                    'event.event_type',
                    'event.from_status',
                    'event.to_status',
                    'event.actor_type',
                    'event.actor_user_id',
                    'event.notes',
                    'event.metadata',
                    'event.occurred_at',
                    'actor.name as actor_name',
                    'actor.email as actor_email',
                ])
                ->orderByDesc(
                    'event.occurred_at'
                )
                ->orderByDesc(
                    'event.id'
                )
                ->get()
                ->map(
                    fn ($event): array => [
                        'id' =>
                            (int) $event->id,

                        'event_type' =>
                            (string) $event->event_type,

                        'event_label' =>
                            $this->eventLabel(
                                (string) $event->event_type
                            ),

                        'from_status' =>
                            $event->from_status
                                ? (string) $event->from_status
                                : null,

                        'from_status_label' =>
                            $event->from_status
                                ? $this->statusLabel(
                                    (string) $event->from_status
                                )
                                : null,

                        'to_status' =>
                            (string) $event->to_status,

                        'to_status_label' =>
                            $this->statusLabel(
                                (string) $event->to_status
                            ),

                        'actor_type' =>
                            (string) $event->actor_type,

                        'actor_type_label' =>
                            $this->actorTypeLabel(
                                (string) $event->actor_type
                            ),

                        'actor' =>
                            $event->actor_user_id
                                ? [
                                    'id' =>
                                        (int) $event->actor_user_id,

                                    'name' =>
                                        $event->actor_name
                                            ? (string) $event->actor_name
                                            : null,

                                    'email' =>
                                        $event->actor_email
                                            ? (string) $event->actor_email
                                            : null,
                                ]
                                : null,

                        'notes' =>
                            $event->notes
                                ? (string) $event->notes
                                : null,

                        'occurred_at' =>
                            $event->occurred_at
                                ? (string) $event->occurred_at
                                : null,
                    ]
                )
                ->values()
                ->all();

        $snapshot =
            $this->decodeJson(
                $context->source_snapshot
            );

        $latestDefinition =
            \App\Models\TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

        /*
         * Después de definition_agreed dejamos de usar
         * "latest Definition" como fuente de verdad para cierre.
         *
         * La Definition acordada se obtiene exclusivamente del
         * evento específico definition_agreed_by_tenant.
         */
        $functionalClosureContext =
            null;

        $definitionFunctionalClosureAvailable =
            false;

        if (
            $implementationRequest->status
            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
        ) {
            $agreementEvents =
                \App\Models\TransformationImplementationRequestEvent::query()
                    ->where(
                        'transformation_implementation_request_id',
                        $implementationRequest->id
                    )
                    ->where(
                        'event_type',
                        'definition_agreed_by_tenant'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->get();

            if ($agreementEvents->count() === 1) {
                $agreementEvent =
                    $agreementEvents->first();

                $agreementMetadata =
                    is_array(
                        $agreementEvent->metadata
                    )
                        ? $agreementEvent->metadata
                        : [];

                $agreedDefinitionId =
                    (int) (
                        $agreementMetadata[
                            'definition_id'
                        ]
                        ?? 0
                    );

                $agreedDefinitionVersion =
                    (int) (
                        $agreementMetadata[
                            'definition_version'
                        ]
                        ?? 0
                    );

                if (
                    $agreedDefinitionId > 0
                    && $agreedDefinitionVersion > 0
                    && (int) (
                        $agreementMetadata[
                            'request_id'
                        ]
                        ?? 0
                    ) === (int) $implementationRequest->id
                    && (int) (
                        $agreementMetadata[
                            'company_id'
                        ]
                        ?? 0
                    ) === (int) $implementationRequest->company_id
                    && trim(
                        (string) (
                            $agreementMetadata[
                                'capability_key'
                            ]
                            ?? ''
                        )
                    ) === trim(
                        (string) $implementationRequest->capability_key
                    )
                ) {
                    $agreedDefinition =
                        \App\Models\TransformationImplementationDefinition::query()
                            ->whereKey(
                                $agreedDefinitionId
                            )
                            ->where(
                                'transformation_implementation_request_id',
                                $implementationRequest->id
                            )
                            ->where(
                                'company_id',
                                $implementationRequest->company_id
                            )
                            ->where(
                                'diagnosis_assessment_id',
                                $implementationRequest->diagnosis_assessment_id
                            )
                            ->where(
                                'transformation_implementation_plan_id',
                                $implementationRequest
                                    ->transformation_implementation_plan_id
                            )
                            ->where(
                                'transformation_implementation_phase_capability_id',
                                $implementationRequest
                                    ->transformation_implementation_phase_capability_id
                            )
                            ->where(
                                'capability_key',
                                $implementationRequest->capability_key
                            )
                            ->first();

                    if (
                        $agreedDefinition
                        && (int) $agreedDefinition->version
                            === $agreedDefinitionVersion
                    ) {
                        $definitionFunctionalClosureAvailable =
                            $agreedDefinition->status
                                === \App\Models\TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                            && data_get(
                                $agreedDefinition->readiness,
                                'state'
                            ) === 'under_review'
                            && data_get(
                                $agreedDefinition->readiness,
                                'definition_ready'
                            ) !== true
                            && $agreedDefinition->ready_at === null;

                        $functionalClosureContext = [
                            'agreement_event_id' =>
                                (int) $agreementEvent->id,

                            'definition_id' =>
                                (int) $agreedDefinition->id,

                            'definition_version' =>
                                (int) $agreedDefinition->version,

                            'definition_status' =>
                                (string) $agreedDefinition->status,

                            'definition_ready' =>
                                (bool) data_get(
                                    $agreedDefinition->readiness,
                                    'definition_ready',
                                    false
                                ),

                            'ready_at' =>
                                $agreedDefinition->ready_at
                                    ?->toISOString(),

                            'tenant_agreed_at' =>
                                $implementationRequest
                                    ->definition_agreed_at
                                    ?->toISOString(),

                            'can_finalize' =>
                                $definitionFunctionalClosureAvailable,
                        ];
                    }
                }
            }
        }

        /*
         * Gate posterior al cierre funcional.
         *
         * La fuente de verdad sigue siendo la Definition exacta
         * acordada por el tenant, no la última versión disponible.
         */
        $readyForCommercialContext =
            null;

        $readyForCommercialAvailable =
            false;

        if (
            $implementationRequest->status
            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
        ) {
            $agreementEventsForCommercialGate =
                \App\Models\TransformationImplementationRequestEvent::query()
                    ->where(
                        'transformation_implementation_request_id',
                        $implementationRequest->id
                    )
                    ->where(
                        'event_type',
                        'definition_agreed_by_tenant'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->get();

            if (
                $agreementEventsForCommercialGate->count()
                === 1
            ) {
                $agreementEventForCommercialGate =
                    $agreementEventsForCommercialGate->first();

                $agreementMetadataForCommercialGate =
                    is_array(
                        $agreementEventForCommercialGate->metadata
                    )
                        ? $agreementEventForCommercialGate->metadata
                        : [];

                $agreedDefinitionIdForCommercialGate =
                    (int) (
                        $agreementMetadataForCommercialGate[
                            'definition_id'
                        ]
                        ?? 0
                    );

                $agreedDefinitionVersionForCommercialGate =
                    (int) (
                        $agreementMetadataForCommercialGate[
                            'definition_version'
                        ]
                        ?? 0
                    );

                $agreedDefinitionForCommercialGate =
                    $agreedDefinitionIdForCommercialGate > 0
                        ? \App\Models\TransformationImplementationDefinition::query()
                            ->whereKey(
                                $agreedDefinitionIdForCommercialGate
                            )
                            ->where(
                                'transformation_implementation_request_id',
                                $implementationRequest->id
                            )
                            ->where(
                                'company_id',
                                $implementationRequest->company_id
                            )
                            ->where(
                                'diagnosis_assessment_id',
                                $implementationRequest->diagnosis_assessment_id
                            )
                            ->where(
                                'transformation_implementation_plan_id',
                                $implementationRequest
                                    ->transformation_implementation_plan_id
                            )
                            ->where(
                                'transformation_implementation_phase_capability_id',
                                $implementationRequest
                                    ->transformation_implementation_phase_capability_id
                            )
                            ->where(
                                'capability_key',
                                $implementationRequest->capability_key
                            )
                            ->first()
                        : null;

                $functionalClosureEventsForCommercialGate =
                    \App\Models\TransformationImplementationRequestEvent::query()
                        ->where(
                            'transformation_implementation_request_id',
                            $implementationRequest->id
                        )
                        ->where(
                            'event_type',
                            'definition_functionally_finalized_by_lauda'
                        )
                        ->orderByDesc(
                            'id'
                        )
                        ->get();

                $functionalClosureEventForCommercialGate =
                    $functionalClosureEventsForCommercialGate->count()
                        === 1
                            ? $functionalClosureEventsForCommercialGate->first()
                            : null;

                $functionalClosureMetadataForCommercialGate =
                    $functionalClosureEventForCommercialGate
                    && is_array(
                        $functionalClosureEventForCommercialGate->metadata
                    )
                        ? $functionalClosureEventForCommercialGate->metadata
                        : [];

                if (
                    $agreedDefinitionForCommercialGate
                    && $functionalClosureEventForCommercialGate
                    && $agreedDefinitionVersionForCommercialGate > 0
                    && (int) $agreedDefinitionForCommercialGate->version
                        === $agreedDefinitionVersionForCommercialGate
                    && (int) (
                        $agreementMetadataForCommercialGate[
                            'request_id'
                        ]
                        ?? 0
                    ) === (int) $implementationRequest->id
                    && (int) (
                        $agreementMetadataForCommercialGate[
                            'company_id'
                        ]
                        ?? 0
                    ) === (int) $implementationRequest->company_id
                    && trim(
                        (string) (
                            $agreementMetadataForCommercialGate[
                                'capability_key'
                            ]
                            ?? ''
                        )
                    ) === trim(
                        (string) $implementationRequest->capability_key
                    )
                    && (int) (
                        $functionalClosureMetadataForCommercialGate[
                            'agreement_event_id'
                        ]
                        ?? 0
                    ) === (int) $agreementEventForCommercialGate->id
                    && (int) (
                        $functionalClosureMetadataForCommercialGate[
                            'definition_id'
                        ]
                        ?? 0
                    ) === (int) $agreedDefinitionForCommercialGate->id
                    && (int) (
                        $functionalClosureMetadataForCommercialGate[
                            'definition_version'
                        ]
                        ?? 0
                    ) === (int) $agreedDefinitionForCommercialGate->version
                    && $agreedDefinitionForCommercialGate->status
                        === \App\Models\TransformationImplementationDefinition::STATUS_READY
                    && data_get(
                        $agreedDefinitionForCommercialGate->readiness,
                        'state'
                    ) === 'ready'
                    && data_get(
                        $agreedDefinitionForCommercialGate->readiness,
                        'definition_ready'
                    ) === true
                    && data_get(
                        $agreedDefinitionForCommercialGate->readiness,
                        'technical_readiness'
                    ) === true
                    && data_get(
                        $agreedDefinitionForCommercialGate->readiness,
                        'ready_for_execution'
                    ) === false
                    && data_get(
                        $agreedDefinitionForCommercialGate->readiness,
                        'execution_started'
                    ) === false
                    && $agreedDefinitionForCommercialGate->ready_at !== null
                    && (
                        $functionalClosureMetadataForCommercialGate[
                            'definition_ready'
                        ]
                        ?? null
                    ) === true
                    && (
                        $functionalClosureMetadataForCommercialGate[
                            'ready_for_commercial'
                        ]
                        ?? null
                    ) === false
                    && $implementationRequest->ready_for_commercial_at === null
                ) {
                    $readyForCommercialAvailable =
                        true;

                    $readyForCommercialContext = [
                        'agreement_event_id' =>
                            (int) $agreementEventForCommercialGate->id,

                        'functional_closure_event_id' =>
                            (int) $functionalClosureEventForCommercialGate->id,

                        'definition_id' =>
                            (int) $agreedDefinitionForCommercialGate->id,

                        'definition_version' =>
                            (int) $agreedDefinitionForCommercialGate->version,

                        'definition_status' =>
                            (string) $agreedDefinitionForCommercialGate->status,

                        'definition_ready' =>
                            true,

                        'ready_at' =>
                            $agreedDefinitionForCommercialGate
                                ->ready_at
                                ?->toISOString(),

                        'request_status' =>
                            (string) $implementationRequest->status,

                        'ready_for_commercial_at' =>
                            $implementationRequest
                                ->ready_for_commercial_at
                                ?->toISOString(),

                        'can_mark_ready_for_commercial' =>
                            true,
                    ];
                }
            }
        }

        /*
         * Estado posterior: conservar una proyección segura para
         * mostrar que el ciclo funcional ya fue completado.
         */
        if (
            $implementationRequest->status
            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL
        ) {
            $readyForCommercialContext = [
                'agreement_event_id' =>
                    null,

                'functional_closure_event_id' =>
                    null,

                'definition_id' =>
                    $latestDefinition
                        ? (int) $latestDefinition->id
                        : null,

                'definition_version' =>
                    $latestDefinition
                        ? (int) $latestDefinition->version
                        : null,

                'definition_status' =>
                    $latestDefinition
                        ? (string) $latestDefinition->status
                        : null,

                'definition_ready' =>
                    $latestDefinition
                        ? (bool) data_get(
                            $latestDefinition->readiness,
                            'definition_ready',
                            false
                        )
                        : false,

                'ready_at' =>
                    $latestDefinition
                        ?->ready_at
                        ?->toISOString(),

                'request_status' =>
                    (string) $implementationRequest->status,

                'ready_for_commercial_at' =>
                    $implementationRequest
                        ->ready_for_commercial_at
                        ?->toISOString(),

                'can_mark_ready_for_commercial' =>
                    false,
            ];
        }

        $definitionContentPrepared =
            $latestDefinition
            && is_array(
                $latestDefinition->deliverables
            )
            && is_array(
                $latestDefinition->dependencies
            )
            && is_array(
                $latestDefinition->responsibility_model
            )
            && is_array(
                $latestDefinition->readiness
            )
            && in_array(
                data_get(
                    $latestDefinition->readiness,
                    'state'
                ),
                [
                    'prepared_for_review',
                    'under_review',
                ],
                true
            );

        $definitionReadyForTenantReview =
            $implementationRequest->status
                === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
            && $latestDefinition !== null
            && $latestDefinition->status
                === \App\Models\TransformationImplementationDefinition::STATUS_UNDER_REVIEW
            && data_get(
                $latestDefinition->responsibility_model,
                'party_assignment_status'
            ) === 'confirmed'
            && data_get(
                $latestDefinition->readiness,
                'state'
            ) === 'under_review'
            && data_get(
                $latestDefinition->readiness,
                'human_validation.scope_confirmed'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'human_validation.deliverables_confirmed'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'human_validation.dependencies_confirmed'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'human_validation.inputs_validated'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'human_validation.accesses_validated'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'human_validation.responsibilities_confirmed'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'definition_ready'
            ) !== true
            && $latestDefinition->ready_at === null;

        $definitionRevisionAvailable =
            $implementationRequest->status
                === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
            && $latestDefinition !== null
            && (int) $latestDefinition
                ->transformation_implementation_request_id
                === (int) $implementationRequest->id
            && (int) $latestDefinition->company_id
                === (int) $implementationRequest->company_id
            && (int) $latestDefinition
                ->transformation_implementation_phase_capability_id
                === (int) $implementationRequest
                    ->transformation_implementation_phase_capability_id
            && trim(
                (string) $latestDefinition->capability_key
            ) === trim(
                (string) $implementationRequest->capability_key
            )
            && $latestDefinition->status
                === \App\Models\TransformationImplementationDefinition::STATUS_UNDER_REVIEW
            && data_get(
                $latestDefinition->source_snapshot,
                'source_type'
            ) === 'implementation_request'
            && data_get(
                $latestDefinition->implementation_scope,
                'scope_mode'
            ) === 'single_capability'
            && data_get(
                $latestDefinition->implementation_scope,
                'definition_scope_locked_to_request'
            ) === true
            && data_get(
                $latestDefinition->readiness,
                'definition_ready'
            ) !== true
            && $latestDefinition->ready_at === null;

        $tenantChangesEvent =
            collect(
                $events
            )->first(
                fn (array $event): bool =>
                    ($event['event_type'] ?? null)
                        === 'status_transition'
                    && ($event['from_status'] ?? null)
                        === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                    && ($event['to_status'] ?? null)
                        === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
            );


        $revisionProvenance =
            $latestDefinition
                ? data_get(
                    $latestDefinition->source_snapshot,
                    'revision'
                )
                : null;

        $revisionPreviousDefinition =
            is_array(
                $revisionProvenance
            )
            && ! empty(
                $revisionProvenance[
                    'revision_of_definition_id'
                ] ?? null
            )
                ? \App\Models\TransformationImplementationDefinition::query()
                    ->whereKey(
                        $revisionProvenance[
                            'revision_of_definition_id'
                        ]
                    )
                    ->where(
                        'transformation_implementation_request_id',
                        $implementationRequest->id
                    )
                    ->first()
                : null;

        $definitionRevisionContext =
            $definitionRevisionAvailable
                ? [
                    'previous_definition_id' =>
                        (int) $latestDefinition->id,

                    'previous_definition_version' =>
                        (int) $latestDefinition->version,

                    'previous_definition_status' =>
                        (string) $latestDefinition->status,

                    'tenant_change_reason' =>
                        $tenantChangesEvent['notes']
                            ?? null,

                    'changes_requested_at' =>
                        $implementationRequest->changes_requested_at
                            ?->toISOString(),

                    'current_definition_version' =>
                        null,
                ]
                : (
                    $implementationRequest->status
                        === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                    && $latestDefinition !== null
                    && (int) $latestDefinition->version >= 2
                    && is_array(
                        $revisionProvenance
                    )
                        ? [
                            'previous_definition_id' =>
                                (int) (
                                    $revisionProvenance[
                                        'revision_of_definition_id'
                                    ]
                                    ?? 0
                                ),

                            'previous_definition_version' =>
                                (int) (
                                    $revisionProvenance[
                                        'revision_of_definition_version'
                                    ]
                                    ?? 0
                                ),

                            'previous_definition_status' =>
                                $revisionPreviousDefinition
                                    ? (string) $revisionPreviousDefinition->status
                                    : null,

                            'tenant_change_reason' =>
                                (
                                    $revisionProvenance[
                                        'tenant_change_reason'
                                    ]
                                    ?? null
                                ),

                            'changes_requested_at' =>
                                (
                                    $revisionProvenance[
                                        'tenant_changes_requested_at'
                                    ]
                                    ?? null
                                ),

                            'current_definition_version' =>
                                (int) $latestDefinition->version,
                        ]
                        : null
                );


        return Inertia::render(
            'Admin/Transformation360/ImplementationRequests/Show',
            [
                'implementation_request' => [
                    'id' =>
                        (int) $context->id,

                    'status' =>
                        (string) $context->status,

                    'status_label' =>
                        $this->statusLabel(
                            (string) $context->status
                        ),

                    'attempt' =>
                        (int) $context->attempt,

                    'source_type' =>
                        (string) $context->source_type,

                    'tenant_note' =>
                        $context->tenant_note
                            ? (string) $context->tenant_note
                            : null,

                    'internal_notes' =>
                        $context->internal_notes
                            ? (string) $context->internal_notes
                            : null,

                    'requested_at' =>
                        $context->requested_at
                            ? (string) $context->requested_at
                            : null,

                    'review_started_at' =>
                        $context->review_started_at
                            ? (string) $context->review_started_at
                            : null,

                    'definition_started_at' =>
                        $context->definition_started_at
                            ? (string) $context->definition_started_at
                            : null,

                    'tenant_review_requested_at' =>
                        $context->tenant_review_requested_at
                            ? (string) $context->tenant_review_requested_at
                            : null,

                    'changes_requested_at' =>
                        $context->changes_requested_at
                            ? (string) $context->changes_requested_at
                            : null,

                    'definition_agreed_at' =>
                        $context->definition_agreed_at
                            ? (string) $context->definition_agreed_at
                            : null,

                    'ready_for_commercial_at' =>
                        $context->ready_for_commercial_at
                            ? (string) $context->ready_for_commercial_at
                            : null,

                    'cancelled_at' =>
                        $context->cancelled_at
                            ? (string) $context->cancelled_at
                            : null,

                    'cancellation_reason' =>
                        $context->cancellation_reason
                            ? (string) $context->cancellation_reason
                            : null,
                ],

                'company' => [
                    'id' =>
                        (int) $context->company_id,

                    'name' =>
                        (string) (
                            $context->company_name
                            ?: data_get(
                                $snapshot,
                                'company.name',
                                'Empresa'
                            )
                        ),

                    'subscriber_id' =>
                        $context->subscriber_id
                            ? (int) $context->subscriber_id
                            : null,
                ],

                'assessment' => [
                    'id' =>
                        (int) $context->diagnosis_assessment_id,

                    'organization_name' =>
                        $context->organization_name
                            ? (string) $context->organization_name
                            : null,

                    'status' =>
                        $context->assessment_status
                            ? (string) $context->assessment_status
                            : null,

                    'published_at' =>
                        $context->assessment_published_at
                            ? (string) $context->assessment_published_at
                            : null,
                ],

                'plan' => [
                    'id' =>
                        (int) $context->transformation_implementation_plan_id,

                    'version' =>
                        $context->plan_version
                            ? (int) $context->plan_version
                            : null,

                    'status' =>
                        $context->plan_status
                            ? (string) $context->plan_status
                            : null,

                    'presented_at' =>
                        $context->plan_presented_at
                            ? (string) $context->plan_presented_at
                            : null,
                ],

                'phase' => [
                    'id' =>
                        $context->phase_id
                            ? (int) $context->phase_id
                            : null,

                    'sequence' =>
                        $context->phase_sequence
                            ? (int) $context->phase_sequence
                            : (
                                data_get(
                                    $snapshot,
                                    'phase.sequence'
                                )
                                ? (int) data_get(
                                    $snapshot,
                                    'phase.sequence'
                                )
                                : null
                            ),

                    'name' =>
                        $context->phase_name
                            ? (string) $context->phase_name
                            : data_get(
                                $snapshot,
                                'phase.name'
                            ),
                ],

                'capability' => [
                    'phase_capability_id' =>
                        (int) $context->transformation_implementation_phase_capability_id,

                    'key' =>
                        (string) $context->capability_key,

                    'label' =>
                        (string) (
                            $context->capability_label
                            ?: data_get(
                                $snapshot,
                                'capability.label',
                                $this->capabilityLabel(
                                    (string) $context->capability_key
                                )
                            )
                        ),

                    'summary' =>
                        $context->capability_summary
                            ? (string) $context->capability_summary
                            : null,

                    'purpose' =>
                        data_get(
                            $snapshot,
                            'capability.purpose'
                        ),
                ],

                'requested_by' =>
                    $context->requested_by_user_id
                        ? [
                            'id' =>
                                (int) $context->requested_by_user_id,

                            'name' =>
                                $context->requested_by_name
                                    ? (string) $context->requested_by_name
                                    : null,

                            'email' =>
                                $context->requested_by_email
                                    ? (string) $context->requested_by_email
                                    : null,
                        ]
                        : null,

                'assigned_to' =>
                    $context->assigned_to_user_id
                        ? [
                            'id' =>
                                (int) $context->assigned_to_user_id,

                            'name' =>
                                $context->assigned_to_name
                                    ? (string) $context->assigned_to_name
                                    : null,

                            'email' =>
                                $context->assigned_to_email
                                    ? (string) $context->assigned_to_email
                                    : null,
                        ]
                        : null,

                'events' =>
                    $events,


                'admin_users' =>
                    User::query()
                        ->where(
                            'role',
                            'admin'
                        )
                        ->orderBy('name')
                        ->orderBy('id')
                        ->get([
                            'id',
                            'name',
                            'email',
                        ])
                        ->map(
                            fn (User $user): array => [
                                'id' =>
                                    (int) $user->id,

                                'name' =>
                                    (string) $user->name,

                                'email' =>
                                    (string) $user->email,
                            ]
                        )
                        ->values()
                        ->all(),

                                'definition' =>
                    $latestDefinition
                        ? [
                            'id' =>
                                (int) $latestDefinition->id,

                            'version' =>
                                (int) $latestDefinition->version,

                            'status' =>
                                (string) $latestDefinition->status,

                            'capability_key' =>
                                (string) $latestDefinition->capability_key,

                            'created_at' =>
                                $latestDefinition
                                    ->created_at
                                    ?->toISOString(),

                            'content_prepared' =>
                                $definitionContentPrepared,

                            'deliverable_count' =>
                                is_array(
                                    $latestDefinition->deliverables
                                )
                                    ? count(
                                        $latestDefinition->deliverables
                                    )
                                    : 0,

                            'dependency_count' =>
                                is_array(
                                    $latestDefinition->dependencies
                                )
                                    ? count(
                                        $latestDefinition->dependencies
                                    )
                                    : 0,
                        ]
                        : null,

                'definition_review' =>
                    $latestDefinition
                        ? [
                            'implementation_scope' =>
                                $latestDefinition
                                    ->implementation_scope
                                ?? [],

                            'deliverables' =>
                                $latestDefinition
                                    ->deliverables
                                ?? [],

                            'dependencies' =>
                                $latestDefinition
                                    ->dependencies
                                ?? [],

                            'responsibility_model' =>
                                $latestDefinition
                                    ->responsibility_model
                                ?? [],

                            'readiness' =>
                                $latestDefinition
                                    ->readiness
                                ?? [],

                            'reviewed_at' =>
                                $latestDefinition
                                    ->reviewed_at
                                    ?->toISOString(),

                            'reviewed_by_user_id' =>
                                $latestDefinition
                                    ->reviewed_by_user_id
                                    !== null
                                        ? (int) $latestDefinition
                                            ->reviewed_by_user_id
                                        : null,
                        ]
                        : null,

                'functional_closure_context' =>
                    $functionalClosureContext,

                'ready_for_commercial_context' =>
                    $readyForCommercialContext,

                'definition_revision_context' =>
                    $definitionRevisionContext,

                'actions' => [
    'can_create_definition_revision' =>
        $definitionRevisionAvailable,

    'definition_revision_endpoint' =>
        $definitionRevisionAvailable
            ? route(
                'admin.transformation360.implementation_requests.definition.revision.create',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,
                ]
            )
            : null,

                    'can_create_definition' =>
                        $implementationRequest->status
                            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        && $latestDefinition === null,

                    'definition_create_endpoint' =>
                        $implementationRequest->status
                            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                            ? route(
                                'admin.transformation360.implementation_requests.definition.create',
                                [
                                    'implementationRequest' =>
                                        $implementationRequest->id,
                                ]
                            )
                            : null,

                    'can_generate_definition' =>
                        $implementationRequest->status
                            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        && $latestDefinition !== null
                        && $latestDefinition->status
                            === \App\Models\TransformationImplementationDefinition::STATUS_DRAFT
                        && $latestDefinition->isEditable()
                        && ! $definitionContentPrepared,

                    'definition_generate_endpoint' =>
                        $implementationRequest->status
                            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        && $latestDefinition !== null
                            ? route(
                                'admin.transformation360.implementation_requests.definition.generate',
                                [
                                    'implementationRequest' =>
                                        $implementationRequest->id,

                                    'definition' =>
                                        $latestDefinition->id,
                                ]
                            )
                            : null,

                    'can_review_definition' =>
                        $implementationRequest->status
                            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        && $latestDefinition !== null
                        && $latestDefinition->isEditable()
                        && $definitionContentPrepared,

                    'definition_review_endpoint' =>
                        $implementationRequest->status
                            === \App\Services\Diagnosis\TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        && $latestDefinition !== null
                            ? route(
                                'admin.transformation360.implementation_requests.definition.review',
                                [
                                    'implementationRequest' =>
                                        $implementationRequest->id,

                                    'definition' =>
                                        $latestDefinition->id,
                                ]
                            )
                            : null,

                    'can_submit_definition_for_tenant_review' =>
                        $definitionReadyForTenantReview,

                    'definition_submit_tenant_review_endpoint' =>
                        $definitionReadyForTenantReview
                            ? route(
                                'admin.transformation360.implementation_requests.definition.submit_tenant_review',
                                [
                                    'implementationRequest' =>
                                        $implementationRequest->id,

                                    'definition' =>
                                        $latestDefinition->id,
                                ]
                            )
                            : null,

                    'can_mark_ready_for_commercial' =>
                        $readyForCommercialAvailable,

                    'ready_for_commercial_endpoint' =>
                        $readyForCommercialAvailable
                            ? route(
                                'admin.transformation360.implementation_requests.ready_for_commercial',
                                [
                                    'implementationRequest' =>
                                        $implementationRequest->id,
                                ]
                            )
                            : null,

                    'can_finalize_definition_functionally' =>
                        $definitionFunctionalClosureAvailable,

                    'definition_functional_finalize_endpoint' =>
                        $definitionFunctionalClosureAvailable
                            ? route(
                                'admin.transformation360.implementation_requests.definition.functional_finalize',
                                [
                                    'implementationRequest' =>
                                        $implementationRequest->id,
                                ]
                            )
                            : null,

                    'can_mutate' =>
                        true,

                    'assign_endpoint' =>
                        route(
                            'admin.transformation360.implementation_requests.assign',
                            [
                                'implementationRequest' =>
                                    $context->id,
                            ],
                            false
                        ),

                    'transition_endpoint' =>
                        route(
                            'admin.transformation360.implementation_requests.transition',
                            [
                                'implementationRequest' =>
                                    $context->id,
                            ],
                            false
                        ),

                    'allowed_transitions' =>
                        $this->allowedAdminTransitions(
                            (string) $context->status
                        ),
                ],
            ]
        );
    }


    public function assign(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationRequestService $implementationRequests
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $validated =
            $request->validate([
                'assigned_to_user_id' => [
                    'required',
                    'integer',
                    'exists:users,id',
                ],
            ]);

        $assignee =
            User::query()
                ->findOrFail(
                    (int) $validated[
                        'assigned_to_user_id'
                    ]
                );

        if (
            ($assignee->role ?? null)
                !== 'admin'
        ) {
            throw ValidationException::withMessages([
                'assigned_to_user_id' => [
                    'El responsable debe ser un usuario Admin LAUDA.',
                ],
            ]);
        }

        $implementationRequests->assignTo(
            $implementationRequest,
            $assignee,
            $request->user()
        );

        return back()->with(
            'success',
            'Responsable LAUDA asignado correctamente.'
        );
    }

    public function transition(
        Request $request,
        TransformationImplementationRequest $implementationRequest,
        TransformationImplementationRequestService $implementationRequests
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $validated =
            $request->validate([
                'target_status' => [
                    'required',
                    'string',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:4000',
                ],
            ]);

        $targetStatus =
            trim(
                (string) $validated[
                    'target_status'
                ]
            );

        $allowed =
            $this->allowedAdminTransitions(
                (string) $implementationRequest->status
            );

        if (
            ! in_array(
                $targetStatus,
                $allowed,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'target_status' => [
                    'Esta transición no está habilitada en la etapa administrativa actual.',
                ],
            ]);
        }

        $implementationRequests->transitionByLauda(
            $implementationRequest,
            $targetStatus,
            $request->user(),
            isset($validated['notes'])
                ? trim(
                    (string) $validated['notes']
                )
                : null
        );

        $message =
            match ($targetStatus) {
                TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW =>
                    'La solicitud fue recibida y está en revisión por LAUDA.',

                TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION =>
                    'La solicitud pasó a preparación de definición.',

                default =>
                    'Estado de la solicitud actualizado.',
            };

        return back()->with(
            'success',
            $message
        );
    }

    private function authorizeAdmin(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && ($request->user()->role ?? null) === 'admin',
            403
        );
    }

    private function queueRow(
        object $row
    ): array {
        return [
            'id' =>
                (int) $row->id,

            'company' => [
                'id' =>
                    (int) $row->company_id,

                'name' =>
                    (string) (
                        $row->company_name
                        ?: 'Empresa'
                    ),
            ],

            'assessment_id' =>
                (int) $row->diagnosis_assessment_id,

            'plan_id' =>
                (int) $row->transformation_implementation_plan_id,

            'phase_capability_id' =>
                (int) $row->transformation_implementation_phase_capability_id,

            'capability_key' =>
                (string) $row->capability_key,

            'capability_label' =>
                (string) (
                    $row->capability_label
                    ?: $this->capabilityLabel(
                        (string) $row->capability_key
                    )
                ),

            'attempt' =>
                (int) $row->attempt,

            'status' =>
                (string) $row->status,

            'status_label' =>
                $this->statusLabel(
                    (string) $row->status
                ),

            'tenant_note' =>
                $row->tenant_note
                    ? (string) $row->tenant_note
                    : null,

            'requested_at' =>
                $row->requested_at
                    ? (string) $row->requested_at
                    : null,

            'requested_by' =>
                $row->requested_by_name
                || $row->requested_by_email
                    ? [
                        'name' =>
                            $row->requested_by_name
                                ? (string) $row->requested_by_name
                                : null,

                        'email' =>
                            $row->requested_by_email
                                ? (string) $row->requested_by_email
                                : null,
                    ]
                    : null,

            'assigned_to' =>
                $row->assigned_to_name
                || $row->assigned_to_email
                    ? [
                        'name' =>
                            $row->assigned_to_name
                                ? (string) $row->assigned_to_name
                                : null,

                        'email' =>
                            $row->assigned_to_email
                                ? (string) $row->assigned_to_email
                                : null,
                    ]
                    : null,

            'detail_url' =>
                route(
                    'admin.transformation360.implementation_requests.show',
                    [
                        'implementationRequest' =>
                            $row->id,
                    ],
                    false
                ),
        ];
    }


    private function allowedAdminTransitions(
        string $status
    ): array {
        return match ($status) {
            TransformationImplementationRequestContract::STATUS_REQUESTED => [
                TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
            ],

            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW => [
                TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            ],

            default =>
                [],
        };
    }

    private function statusOptions(): array
    {
        return array_map(
            fn (string $status): array => [
                'value' =>
                    $status,

                'label' =>
                    $this->statusLabel(
                        $status
                    ),
            ],
            TransformationImplementationRequestContract::STATUSES
        );
    }

    private function statusLabel(
        string $status
    ): string {
        return match ($status) {
            TransformationImplementationRequestContract::STATUS_REQUESTED =>
                'Solicitud recibida',

            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW =>
                'En revisión por LAUDA',

            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION =>
                'Definición en preparación',

            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW =>
                'Esperando revisión de la empresa',

            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED =>
                'Ajustes solicitados',

            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED =>
                'Definición acordada',

            TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL =>
                'Lista para etapa comercial',

            TransformationImplementationRequestContract::STATUS_CANCELLED =>
                'Cancelada',

            default =>
                $status,
        };
    }

    private function capabilityLabel(
        string $capabilityKey
    ): string {
        return match ($capabilityKey) {
            'data_transformation_bi' =>
                'Datos e Inteligencia BI',

            default =>
                $capabilityKey,
        };
    }

    private function eventLabel(
        string $eventType
    ): string {
        return match ($eventType) {
            'request_created' =>
                'Solicitud creada',

            'status_transitioned' =>
                'Estado actualizado',

            'request_assigned' =>
                'Responsable asignado',

            default =>
                str_replace(
                    '_',
                    ' ',
                    $eventType
                ),
        };
    }

    private function actorTypeLabel(
        string $actorType
    ): string {
        return match ($actorType) {
            'tenant_admin' =>
                'Administrador de la empresa',

            'lauda_admin' =>
                'Admin LAUDA',

            'system' =>
                'Sistema',

            default =>
                $actorType,
        };
    }

    private function decodeJson(
        mixed $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return [];
        }

        $decoded =
            json_decode(
                $value,
                true
            );

        return is_array($decoded)
            ? $decoded
            : [];
    }
}
