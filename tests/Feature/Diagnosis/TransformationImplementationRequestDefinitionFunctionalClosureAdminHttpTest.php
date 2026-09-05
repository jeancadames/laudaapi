<?php

namespace Tests\Feature\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAssessment;
use App\Models\Subscriber;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionTenantDecisionService;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequestEvent;
use App\Services\Diagnosis\TransformationImplementationDefinitionAutogenerator;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionReviewService;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionService;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionTenantReviewService;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransformationImplementationRequestDefinitionFunctionalClosureAdminHttpTest
    extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * DEV no ejecuta npm run build como parte de este QA.
         */
        $this->withoutVite();

        /*
         * Este contrato prueba autorización tenant y flujo HTTP.
         * Email verification pertenece a otro contrato.
         */
        $this->withoutMiddleware(
            EnsureEmailIsVerified::class
        );
    }

    public function test_admin_functionally_finalizes_exact_tenant_agreed_v2_without_commercial_or_execution_side_effects(): void
    {
        $context =
            $this->createTenantBiFixture();

        /** @var User $tenant */
        $tenant =
            $context['user'];

        /** @var Company $company */
        $company =
            $context['company'];

        /** @var DiagnosisAssessment $assessment */
        $assessment =
            $context['assessment'];

        /** @var TransformationImplementationPlan $plan */
        $plan =
            $context['plan'];

        /** @var TransformationImplementationPhaseCapability $capability */
        $capability =
            $context['phase_capability'];

        $admin =
            User::factory()->create([
                'name' =>
                    'F6D3C Admin LAUDA',

                'email' =>
                    'f6d3c-admin-'
                    .Str::lower(
                        Str::random(14)
                    )
                    .'@example.test',

                'role' =>
                    'admin',
            ]);

        /*
         * ==========================================================
         * 1. REQUEST → DEFINITION_PREPARATION
         * ==========================================================
         */

        /** @var TransformationImplementationRequestService $requests */
        $requests =
            app(
                TransformationImplementationRequestService::class
            );

        $implementationRequest =
            $requests
                ->requestFromTenantAdmin(
                    $company,
                    $assessment,
                    $plan,
                    $capability,
                    $tenant,
                    'F6D3-C · Solicitud BI.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
                    $admin,
                    'F6D3-C · Revisión LAUDA.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                    $admin,
                    'F6D3-C · Preparación de V1.'
                );

        /*
         * ==========================================================
         * 2. CREATE + GENERATE V1
         * ==========================================================
         */

        /** @var TransformationImplementationRequestDefinitionService $definitions */
        $definitions =
            app(
                TransformationImplementationRequestDefinitionService::class
            );

        $v1 =
            $definitions
                ->createOrGetDraftFromRequest(
                    $implementationRequest,
                    $admin
                );

        /** @var TransformationImplementationDefinitionAutogenerator $autogenerator */
        $autogenerator =
            app(
                TransformationImplementationDefinitionAutogenerator::class
            );

        $v1 =
            $autogenerator
                ->generate(
                    $v1,
                    (int) $admin->id
                );

        $v1->refresh();

        /*
         * ==========================================================
         * 3. HUMAN REVIEW V1
         * ==========================================================
         */

        $v1Assignments =
            data_get(
                $v1->responsibility_model,
                'assignments',
                []
            );

        $this->assertNotEmpty(
            $v1Assignments
        );

        $parties = [
            'lauda',
            'client',
            'shared',
        ];

        $v1ReviewAssignments =
            collect(
                $v1Assignments
            )
                ->values()
                ->map(
                    function (
                        array $assignment,
                        int $index
                    ) use (
                        $parties
                    ): array {
                        return array_merge(
                            $assignment,
                            [
                                'responsible_party' =>
                                    $parties[
                                        $index
                                        % count(
                                            $parties
                                        )
                                    ],
                            ]
                        );
                    }
                )
                ->all();

        /** @var TransformationImplementationRequestDefinitionReviewService $reviews */
        $reviews =
            app(
                TransformationImplementationRequestDefinitionReviewService::class
            );

        $v1 =
            $reviews
                ->saveReview(
                    $implementationRequest,
                    $v1,
                    [
                        'responsibility_model' => [
                            'assignments' =>
                                $v1ReviewAssignments,
                        ],

                        'readiness' => [
                            'scope_confirmed' =>
                                true,

                            'deliverables_confirmed' =>
                                true,

                            'dependencies_confirmed' =>
                                true,

                            'inputs_validated' =>
                                true,

                            'accesses_validated' =>
                                true,

                            'responsibilities_confirmed' =>
                                true,
                        ],
                    ],
                    $admin
                );

        /*
         * ==========================================================
         * 4. SUBMIT V1 TO TENANT
         * ==========================================================
         */

        /** @var TransformationImplementationRequestDefinitionTenantReviewService $tenantReview */
        $tenantReview =
            app(
                TransformationImplementationRequestDefinitionTenantReviewService::class
            );

        $implementationRequest =
            $tenantReview
                ->submit(
                    $implementationRequest,
                    $v1,
                    $admin,
                    'F6D3-C · V1 enviada al tenant.'
                );

        $implementationRequest->refresh();
        $v1->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 5. TENANT REQUESTS CHANGES THROUGH REAL HTTP ENDPOINT
         * ==========================================================
         */

        $tenantShowUrl =
            route(
                'app.transformation.data_bi.show'
            );

        $changesUrl =
            route(
                'app.transformation.data_bi.definition.request_changes'
            );

        $tenantReason =
            'Separar la homologación de fuentes por prioridad, precisar entregables de calidad de datos y aclarar qué accesos técnicos aportará nuestra empresa.';

        $this
            ->actingAs(
                $tenant
            )
            ->from(
                $tenantShowUrl
            )
            ->post(
                $changesUrl,
                [
                    'reason' =>
                        $tenantReason,
                ]
            )
            ->assertRedirect(
                $tenantShowUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 6. ADMIN CREATES V2 THROUGH REAL HTTP ENDPOINT
         * ==========================================================
         */

        $detailUrl =
            route(
                'admin.transformation360.implementation_requests.show',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,
                ]
            );

        $revisionUrl =
            route(
                'admin.transformation360.implementation_requests.definition.revision.create',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,
                ]
            );

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->post(
                $revisionUrl
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $versions =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->orderBy(
                    'version'
                )
                ->get();

        $this->assertCount(
            2,
            $versions
        );

        /** @var TransformationImplementationDefinition $v1 */
        $v1 =
            $versions->first();

        /** @var TransformationImplementationDefinition $v2 */
        $v2 =
            $versions->last();

        $this->assertSame(
            1,
            (int) $v1->version
        );

        $this->assertSame(
            2,
            (int) $v2->version
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v1->status
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $v2->status
        );

        $this->assertSame(
            'prepared_for_review',
            data_get(
                $v2->readiness,
                'state'
            )
        );

        /*
         * ==========================================================
         * 7. RAW DATABASE IMMUTABILITY BASELINES
         * ==========================================================
         */

        $v1Before =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first();

        $v2Before =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first();

        $definitionCountBefore =
            DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->assertSame(
            2,
            $definitionCountBefore
        );

        /*
         * ==========================================================
         * 8. BUILD VALID FUNCTIONAL V2 PAYLOAD
         * ==========================================================
         */

        $editedScope =
            $v2->implementation_scope;

        $editedScope[
            'scope_mode'
        ] =
            'browser_tampered_scope_mode';

        $editedScope[
            'capability_key'
        ] =
            'browser_tampered_capability';

        $editedScope[
            'definition_scope_locked_to_request'
        ] =
            false;

        $editedScope[
            'revision_edit_note'
        ] =
            'F6D3-C · Alcance ajustado según comentarios del tenant.';

        $editedDeliverables =
            $v2->deliverables;

        $editedDeliverables[] = [
            'id' =>
                'f6d3c-revised-deliverable',

            'title' =>
                'Matriz priorizada de homologación y calidad de datos',

            'description' =>
                'Entregable agregado específicamente en la revisión V2 solicitada por la empresa.',
        ];

        $editedDependencies =
            $v2->dependencies;

        $editedDependencies[] = [
            'id' =>
                'f6d3c-revised-dependency',

            'title' =>
                'Inventario confirmado de accesos técnicos del cliente',

            'description' =>
                'Dependencia precisada durante la revisión V2.',
        ];

        $v2Assignments =
            data_get(
                $v2->responsibility_model,
                'assignments',
                []
            );

        $this->assertNotEmpty(
            $v2Assignments
        );

        $editedAssignments =
            collect(
                $v2Assignments
            )
                ->values()
                ->map(
                    function (
                        array $assignment,
                        int $index
                    ): array {
                        return array_merge(
                            $assignment,
                            [
                                'responsible_party' =>
                                    $index === 0
                                        ? 'shared'
                                        : (
                                            $assignment[
                                                'responsible_party'
                                            ]
                                            ?? 'lauda'
                                        ),
                            ]
                        );
                    }
                )
                ->all();

        $reviewPayload = [
            'implementation_scope' =>
                $editedScope,

            'deliverables' =>
                $editedDeliverables,

            'dependencies' =>
                $editedDependencies,

            'responsibility_model' => [
                'assignments' =>
                    $editedAssignments,
            ],

            'readiness' => [
                'scope_confirmed' =>
                    true,

                'deliverables_confirmed' =>
                    true,

                'dependencies_confirmed' =>
                    true,

                'inputs_validated' =>
                    true,

                'accesses_validated' =>
                    true,

                'responsibilities_confirmed' =>
                    true,
            ],
        ];

        /*
         * ==========================================================
         * 9. ADMIN READ MODEL BEFORE EDIT
         * ==========================================================
         */

        $v2ReviewUrl =
            route(
                'admin.transformation360.implementation_requests.definition.review',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $v2->id,
                ]
            );

        $v1ReviewUrl =
            route(
                'admin.transformation360.implementation_requests.definition.review',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $v1->id,
                ]
            );

        $this
            ->actingAs(
                $admin
            )
            ->get(
                $detailUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/ImplementationRequests/Show'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'definition.id',
                            (int) $v2->id
                        )
                        ->where(
                            'definition.version',
                            2
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_DRAFT
                        )
                        ->where(
                            'definition_review.implementation_scope',
                            $v2->implementation_scope
                        )
                        ->where(
                            'definition_review.deliverables',
                            $v2->deliverables
                        )
                        ->where(
                            'definition_review.dependencies',
                            $v2->dependencies
                        )
                        ->where(
                            'definition_revision_context.previous_definition_id',
                            (int) $v1->id
                        )
                        ->where(
                            'definition_revision_context.previous_definition_version',
                            1
                        )
                        ->where(
                            'definition_revision_context.current_definition_version',
                            2
                        )
                        ->where(
                            'definition_revision_context.tenant_change_reason',
                            $tenantReason
                        )
                        ->where(
                            'actions.can_review_definition',
                            true
                        )
                        ->where(
                            'actions.definition_review_endpoint',
                            $v2ReviewUrl
                        )
            );

        /*
         * ==========================================================
         * 10. MALICIOUS / STALE PATCH AGAINST V1 MUST FAIL
         * ==========================================================
         */

        $reviewEventsBeforeStaleAttempt =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_review_saved'
                )
                ->count();

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->patch(
                $v1ReviewUrl,
                $reviewPayload
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHasErrors(
                'definition'
            );

        $v1AfterStaleAttempt =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first();

        $v2AfterStaleAttempt =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first();

        $this->assertSame(
            $v1Before,
            $v1AfterStaleAttempt,
            'V1 cambió durante un PATCH stale bloqueado.'
        );

        $this->assertSame(
            $v2Before,
            $v2AfterStaleAttempt,
            'V2 cambió durante el intento stale contra V1.'
        );

        $reviewEventsAfterStaleAttempt =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_review_saved'
                )
                ->count();

        $this->assertSame(
            $reviewEventsBeforeStaleAttempt,
            $reviewEventsAfterStaleAttempt
        );

        /*
         * ==========================================================
         * 11. REAL FUNCTIONAL PATCH AGAINST V2
         * ==========================================================
         */

        $auditMaxIdBeforeReview =
            Schema::hasTable(
                'audit_logs'
            )
                ? (int) (
                    DB::table(
                        'audit_logs'
                    )->max(
                        'id'
                    )
                    ?? 0
                )
                : 0;

        $guardTables = [
            'transformation_capability_activations',
            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',
            'transformation_implementation_subscription_activations',
            'transformation_implementation_subscription_item_activations',
            'subscriptions',
            'subscription_items',
        ];

        $guards = [];

        foreach (
            $guardTables
            as $table
        ) {
            if (
                Schema::hasTable(
                    $table
                )
            ) {
                $guards[$table] =
                    DB::table(
                        $table
                    )->count();
            }
        }

        $reviewEventsBeforeV2 =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_review_saved'
                )
                ->count();

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->patch(
                $v2ReviewUrl,
                $reviewPayload
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();
        $v1->refresh();
        $v2->refresh();

        /*
         * ==========================================================
         * 12. REQUEST STAYS IN DEFINITION_PREPARATION
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 13. V1 STILL BYTE-FOR-BYTE IMMUTABLE
         * ==========================================================
         */

        $v1AfterV2Review =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first();

        $this->assertSame(
            $v1Before,
            $v1AfterV2Review,
            'V1 fue modificada al revisar V2.'
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v1->status
        );

        /*
         * ==========================================================
         * 14. NO V3
         * ==========================================================
         */

        $this->assertSame(
            2,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        $this->assertSame(
            2,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->max(
                    'version'
                )
        );

        /*
         * ==========================================================
         * 15. V2 FUNCTIONAL CONTENT ACTUALLY CHANGED
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v2->status
        );

        $this->assertSame(
            'F6D3-C · Alcance ajustado según comentarios del tenant.',
            data_get(
                $v2->implementation_scope,
                'revision_edit_note'
            )
        );

        $this->assertSame(
            $editedDeliverables,
            $v2->deliverables
        );

        $this->assertSame(
            $editedDependencies,
            $v2->dependencies
        );

        /*
         * ==========================================================
         * 16. REQUEST-SCOPED LOCKS IGNORE BROWSER TAMPERING
         * ==========================================================
         */

        $this->assertSame(
            'single_capability',
            data_get(
                $v2->implementation_scope,
                'scope_mode'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $v2->implementation_scope,
                'capability_key'
            )
        );

        $this->assertTrue(
            data_get(
                $v2->implementation_scope,
                'definition_scope_locked_to_request'
            )
        );

        /*
         * ==========================================================
         * 17. RESPONSIBILITY MODEL CONFIRMED
         * ==========================================================
         */

        $this->assertSame(
            'confirmed',
            data_get(
                $v2->responsibility_model,
                'party_assignment_status'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->responsibility_model,
                'confirmation_required'
            )
        );

        $this->assertSame(
            [],
            data_get(
                $v2->responsibility_model,
                'unresolved'
            )
        );

        $normalizedAssignments =
            data_get(
                $v2->responsibility_model,
                'assignments',
                []
            );

        $this->assertCount(
            count(
                $editedAssignments
            ),
            $normalizedAssignments
        );

        foreach (
            $normalizedAssignments
            as $assignment
        ) {
            $this->assertContains(
                $assignment[
                    'responsible_party'
                ],
                [
                    'lauda',
                    'client',
                    'shared',
                ]
            );

            $this->assertSame(
                'confirmed',
                $assignment[
                    'confirmation_status'
                ]
                ?? null
            );
        }

        /*
         * ==========================================================
         * 18. SIX HUMAN CONFIRMATIONS
         * ==========================================================
         */

        $this->assertSame(
            'under_review',
            data_get(
                $v2->readiness,
                'state'
            )
        );

        foreach ([
            'scope_confirmed',
            'deliverables_confirmed',
            'dependencies_confirmed',
            'inputs_validated',
            'accesses_validated',
            'responsibilities_confirmed',
        ] as $key) {
            $this->assertTrue(
                data_get(
                    $v2->readiness,
                    'human_validation.'.$key
                ),
                'Confirmación humana no guardada: '.$key
            );
        }

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'definition_ready'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'technical_readiness'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'execution_started'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $v2->readiness,
                'human_review_required'
            )
        );

        $this->assertNotNull(
            $v2->reviewed_at
        );

        $this->assertSame(
            (int) $admin->id,
            (int) $v2->reviewed_by_user_id
        );

        $this->assertNull(
            $v2->ready_at
        );

        /*
         * ==========================================================
         * 19. REQUEST REVIEW EVENT PINS V2
         * ==========================================================
         */

        $reviewEventsAfterV2 =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_review_saved'
                )
                ->count();

        $this->assertSame(
            $reviewEventsBeforeV2 + 1,
            $reviewEventsAfterV2
        );

        $reviewEvent =
            TransformationImplementationRequestEvent::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_review_saved'
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        $this->assertNotNull(
            $reviewEvent
        );

        $this->assertSame(
            (int) $v2->id,
            (int) data_get(
                $reviewEvent->metadata,
                'definition_id'
            )
        );

        $this->assertSame(
            2,
            (int) data_get(
                $reviewEvent->metadata,
                'definition_version'
            )
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            data_get(
                $reviewEvent->metadata,
                'definition_status'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $reviewEvent->metadata,
                'definition_ready'
            )
        );

        /*
         * ==========================================================
         * 20. REVIEW AUDIT IS FOR V2
         * ==========================================================
         */

        if (
            Schema::hasTable(
                'audit_logs'
            )
        ) {
            $newAudits =
                DB::table(
                    'audit_logs'
                )
                    ->where(
                        'id',
                        '>',
                        $auditMaxIdBeforeReview
                    )
                    ->where(
                        'event',
                        'transformation_implementation_definition_review_saved'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->get();

            $this->assertNotEmpty(
                $newAudits
            );

            $reviewAudit =
                $newAudits
                    ->map(
                        function ($audit) {
                            $data =
                                json_decode(
                                    (string) (
                                        $audit->data
                                        ?? ''
                                    ),
                                    true
                                );

                            return [
                                'model_id' =>
                                    (int) (
                                        $audit->model_id
                                        ?? 0
                                    ),

                                'data' =>
                                    is_array(
                                        $data
                                    )
                                        ? $data
                                        : [],
                            ];
                        }
                    )
                    ->first(
                        fn (array $audit): bool =>
                            $audit[
                                'model_id'
                            ] === (int) $v2->id
                    );

            $this->assertNotNull(
                $reviewAudit
            );

            $this->assertSame(
                2,
                (int) data_get(
                    $reviewAudit,
                    'data.definition_version'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $reviewAudit,
                    'data.definition_ready'
                )
            );
        }

        /*
         * ==========================================================
         * 21. ADMIN READ MODEL AFTER REVIEW
         * ==========================================================
         */

        $submitV2Url =
            route(
                'admin.transformation360.implementation_requests.definition.submit_tenant_review',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $v2->id,
                ]
            );

        $this
            ->actingAs(
                $admin
            )
            ->get(
                $detailUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/ImplementationRequests/Show'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'definition.id',
                            (int) $v2->id
                        )
                        ->where(
                            'definition.version',
                            2
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'definition_review.implementation_scope.revision_edit_note',
                            'F6D3-C · Alcance ajustado según comentarios del tenant.'
                        )
                        ->where(
                            'definition_review.deliverables',
                            $editedDeliverables
                        )
                        ->where(
                            'definition_review.dependencies',
                            $editedDependencies
                        )
                        ->where(
                            'definition_revision_context.previous_definition_id',
                            (int) $v1->id
                        )
                        ->where(
                            'definition_revision_context.previous_definition_version',
                            1
                        )
                        ->where(
                            'definition_revision_context.current_definition_version',
                            2
                        )
                        ->where(
                            'definition_revision_context.tenant_change_reason',
                            $tenantReason
                        )
                        ->where(
                            'actions.can_review_definition',
                            true
                        )
                        ->where(
                            'actions.can_submit_definition_for_tenant_review',
                            true
                        )
                        ->where(
                            'actions.definition_submit_tenant_review_endpoint',
                            $submitV2Url
                        )
            );

        /*
         * ==========================================================
         * 22. NO AUTOMATIC TENANT RESUBMIT / AGREEMENT / READY
         * ==========================================================
         */

        $implementationRequest->refresh();
        $v2->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertNull(
            $implementationRequest->definition_agreed_at
        );

        $this->assertNull(
            $implementationRequest->ready_for_commercial_at
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'definition_ready'
            )
        );

        $this->assertNull(
            $v2->ready_at
        );

        /*
         * ==========================================================
         * 23. NO DOWNSTREAM SIDE EFFECTS
         * ==========================================================
         */

        foreach (
            $guards
            as $table => $before
        ) {
            $this->assertSame(
                $before,
                DB::table(
                    $table
                )->count(),
                'Side effect no permitido en '.$table
            );
        }
    

        /*
         * ==========================================================
         * F6-E3 · RESUBMIT EXACT V2
         * ==========================================================
         */

        $v1BeforeResubmit =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first();

        $v2BeforeResubmit =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertSame(
            2,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        /*
         * ----------------------------------------------------------
         * Baseline del segundo envío definition_preparation
         * -> awaiting_tenant_review.
         *
         * Ya existe un evento previo correspondiente a V1.
         * ----------------------------------------------------------
         */

        $submissionTransitionCountBefore =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'from_status',
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->count();

        $auditMaxIdBefore =
            Schema::hasTable(
                'audit_logs'
            )
                ? (int) (
                    DB::table(
                        'audit_logs'
                    )->max(
                        'id'
                    )
                    ?? 0
                )
                : 0;

        /*
         * ==========================================================
         * STALE V1 SUBMIT · MUST BE BLOCKED BY DOMAIN
         * ==========================================================
         */

        $submitV1Url =
            route(
                'admin.transformation360.implementation_requests.definition.submit_tenant_review',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $v1->id,
                ]
            );

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->post(
                $submitV1Url,
                [
                    'notes' =>
                        'F6-E3 · Intento stale contra V1.',
                ]
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHasErrors(
                'definition'
            );

        $implementationRequest->refresh();
        $v1->refresh();
        $v2->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertSame(
            $v1BeforeResubmit,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first(),
            'V1 cambió durante el submit stale.'
        );

        $this->assertSame(
            $v2BeforeResubmit,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first(),
            'V2 cambió durante el submit stale de V1.'
        );

        $this->assertSame(
            $submissionTransitionCountBefore,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'from_status',
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->count()
        );

        /*
         * ==========================================================
         * VALID V2 SUBMIT
         * ==========================================================
         */

        $submitV2Url =
            route(
                'admin.transformation360.implementation_requests.definition.submit_tenant_review',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $v2->id,
                ]
            );

        $this
            ->actingAs(
                $admin
            )
            ->get(
                $detailUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/ImplementationRequests/Show'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'definition.id',
                            (int) $v2->id
                        )
                        ->where(
                            'definition.version',
                            2
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'actions.can_submit_definition_for_tenant_review',
                            true
                        )
                        ->where(
                            'actions.definition_submit_tenant_review_endpoint',
                            $submitV2Url
                        )
            );

        $resubmitNote =
            'F6-E3 · V2 revisada y reenviada explícitamente por LAUDA.';

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->post(
                $submitV2Url,
                [
                    'notes' =>
                        $resubmitNote,
                ]
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();
        $v1->refresh();
        $v2->refresh();

        /*
         * ==========================================================
         * REQUEST -> awaiting_tenant_review
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->tenant_review_requested_at
        );

        $this->assertNull(
            $implementationRequest->definition_agreed_at
        );

        $this->assertNull(
            $implementationRequest->ready_for_commercial_at
        );

        /*
         * ==========================================================
         * SUBMIT DOES NOT MUTATE V1 OR V2
         * ==========================================================
         */

        $this->assertSame(
            $v1BeforeResubmit,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first(),
            'V1 fue modificada al reenviar V2.'
        );

        $this->assertSame(
            $v2BeforeResubmit,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first(),
            'V2 fue modificada durante su reenvío.'
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v2->status
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'definition_ready'
            )
        );

        $this->assertNull(
            $v2->ready_at
        );

        /*
         * ==========================================================
         * EXACT TRANSITION
         * ==========================================================
         */

        $submissionTransitionCountAfter =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'from_status',
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->count();

        $this->assertSame(
            $submissionTransitionCountBefore + 1,
            $submissionTransitionCountAfter
        );

        $latestSubmissionTransition =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'from_status',
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        $this->assertNotNull(
            $latestSubmissionTransition
        );

        $this->assertSame(
            'lauda_admin',
            $latestSubmissionTransition->actor_type
        );

        $this->assertSame(
            (int) $admin->id,
            (int) $latestSubmissionTransition->actor_user_id
        );

        /*
         * ==========================================================
         * SUBMISSION AUDIT MUST PIN V2
         * ==========================================================
         */

        if (
            Schema::hasTable(
                'audit_logs'
            )
        ) {
            $submissionAudits =
                DB::table(
                    'audit_logs'
                )
                    ->where(
                        'id',
                        '>',
                        $auditMaxIdBefore
                    )
                    ->where(
                        'event',
                        'transformation_implementation_definition_submitted_for_tenant_review'
                    )
                    ->orderBy(
                        'id'
                    )
                    ->get();

            $this->assertCount(
                1,
                $submissionAudits
            );

            $audit =
                $submissionAudits->first();

            $this->assertSame(
                (int) $v2->id,
                (int) $audit->model_id
            );

            $auditData =
                json_decode(
                    (string) (
                        $audit->data
                        ?? ''
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            $this->assertSame(
                (int) $implementationRequest->id,
                (int) data_get(
                    $auditData,
                    'request_id'
                )
            );

            $this->assertSame(
                (int) $v2->id,
                (int) data_get(
                    $auditData,
                    'definition_id'
                )
            );

            $this->assertSame(
                2,
                (int) data_get(
                    $auditData,
                    'definition_version'
                )
            );

            $this->assertSame(
                'data_transformation_bi',
                data_get(
                    $auditData,
                    'capability_key'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $auditData,
                    'definition_ready'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $auditData,
                    'tenant_agreed'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $auditData,
                    'ready_for_execution'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $auditData,
                    'execution_started'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $auditData,
                    'commercial_stage_started'
                )
            );
        }

        /*
         * ==========================================================
         * TENANT MUST NOW RECEIVE EXACT V2
         * ==========================================================
         */

        $this
            ->actingAs(
                $tenant
            )
            ->get(
                $tenantShowUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'App/DataTransformationBi'
                        )
                        ->where(
                            'implementation_request.definition_review.id',
                            (int) $v2->id
                        )
                        ->where(
                            'implementation_request.definition_review.version',
                            2
                        )
                        ->where(
                            'implementation_request.definition_review.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'implementation_request.definition_review.capability_key',
                            'data_transformation_bi'
                        )
            );

        /*
         * ==========================================================
         * F6-F4 · TENANT AGREES EXACT CURRENT V2
         * ==========================================================
         */

        $agreementUrl =
            route(
                'app.transformation.data_bi.definition.agree'
            );

        /*
         * Read model must expose agreement only while the exact
         * latest V2 is awaiting tenant review.
         */
        $this
            ->actingAs(
                $tenant
            )
            ->get(
                $tenantShowUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'App/DataTransformationBi'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                        )
                        ->where(
                            'implementation_request.agreement_endpoint',
                            $agreementUrl
                        )
                        ->where(
                            'implementation_request.definition_review.id',
                            (int) $v2->id
                        )
                        ->where(
                            'implementation_request.definition_review.version',
                            2
                        )
                        ->where(
                            'implementation_request.definition_review.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'implementation_request.definition_review.capability_key',
                            'data_transformation_bi'
                        )
            );

        /*
         * Snapshot immutable Definitions before tenant agreement.
         */
        $v1BeforeAgreement =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first();

        $v2BeforeAgreement =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first();

        $agreementTransitionCountBefore =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
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
                    TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                )
                ->count();

        $agreementSpecificEventCountBefore =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_agreed_by_tenant'
                )
                ->count();

        $agreementAuditMaxIdBefore =
            Schema::hasTable(
                'audit_logs'
            )
                ? (int) (
                    DB::table(
                        'audit_logs'
                    )->max(
                        'id'
                    )
                    ?? 0
                )
                : 0;

        /*
         * Real tenant HTTP agreement.
         *
         * Browser sends no Request ID and no Definition ID.
         */
        $this
            ->actingAs(
                $tenant
            )
            ->from(
                $tenantShowUrl
            )
            ->post(
                $agreementUrl,
                []
            )
            ->assertRedirect(
                $tenantShowUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();
        $v1->refresh();
        $v2->refresh();

        /*
         * Request lifecycle only.
         */
        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->definition_agreed_at
        );

        $this->assertNotNull(
            $implementationRequest->tenant_review_requested_at
        );

        $this->assertNull(
            $implementationRequest->ready_for_commercial_at
        );

        /*
         * V1 and V2 remain byte-for-byte immutable.
         */
        $this->assertSame(
            $v1BeforeAgreement,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first(),
            'V1 fue modificada durante el acuerdo del tenant.'
        );

        $this->assertSame(
            $v2BeforeAgreement,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first(),
            'V2 fue modificada durante el acuerdo del tenant.'
        );

        /*
         * Definition is NOT finalized by tenant agreement.
         */
        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v2->status
        );

        $this->assertSame(
            'under_review',
            data_get(
                $v2->readiness,
                'state'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'definition_ready'
            )
        );

        $this->assertNull(
            $v2->ready_at
        );

        /*
         * Generic lifecycle transition.
         */
        $agreementTransitionCountAfter =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
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
                    TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                )
                ->count();

        $this->assertSame(
            $agreementTransitionCountBefore + 1,
            $agreementTransitionCountAfter
        );

        $latestAgreementTransition =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
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
                    TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        $this->assertNotNull(
            $latestAgreementTransition
        );

        $this->assertSame(
            (int) $tenant->id,
            (int) $latestAgreementTransition->actor_user_id
        );

        /*
         * Specific event pins exact Definition V2.
         */
        $agreementSpecificEvents =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_agreed_by_tenant'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $this->assertCount(
            $agreementSpecificEventCountBefore + 1,
            $agreementSpecificEvents
        );

        $agreementEvent =
            $agreementSpecificEvents->last();

        $this->assertNotNull(
            $agreementEvent
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $agreementEvent->from_status
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            $agreementEvent->to_status
        );

        $this->assertSame(
            'tenant_admin',
            $agreementEvent->actor_type
        );

        $this->assertSame(
            (int) $tenant->id,
            (int) $agreementEvent->actor_user_id
        );

        $agreementEventMetadata =
            json_decode(
                (string) (
                    $agreementEvent->metadata
                    ?? ''
                ),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) data_get(
                $agreementEventMetadata,
                'request_id'
            )
        );

        $this->assertSame(
            (int) $v2->id,
            (int) data_get(
                $agreementEventMetadata,
                'definition_id'
            )
        );

        $this->assertSame(
            2,
            (int) data_get(
                $agreementEventMetadata,
                'definition_version'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $agreementEventMetadata,
                'capability_key'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $agreementEventMetadata,
                'tenant_agreed'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'definition_modified'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'definition_ready'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'mark_ready_used'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'commercial_acceptance'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'ready_for_commercial'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'activation_started'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'execution_started'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $agreementEventMetadata,
                'subscription_created'
            )
        );

        /*
         * Specific audit also pins exact V2.
         */
        if (
            Schema::hasTable(
                'audit_logs'
            )
        ) {
            $agreementAudits =
                DB::table(
                    'audit_logs'
                )
                    ->where(
                        'id',
                        '>',
                        $agreementAuditMaxIdBefore
                    )
                    ->where(
                        'event',
                        'transformation_implementation_definition_agreed_by_tenant'
                    )
                    ->orderBy(
                        'id'
                    )
                    ->get();

            $this->assertCount(
                1,
                $agreementAudits
            );

            $agreementAudit =
                $agreementAudits->first();

            $this->assertSame(
                (int) $v2->id,
                (int) $agreementAudit->model_id
            );

            $agreementAuditData =
                json_decode(
                    (string) (
                        $agreementAudit->data
                        ?? ''
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            $this->assertSame(
                (int) $implementationRequest->id,
                (int) data_get(
                    $agreementAuditData,
                    'request_id'
                )
            );

            $this->assertSame(
                (int) $v2->id,
                (int) data_get(
                    $agreementAuditData,
                    'definition_id'
                )
            );

            $this->assertSame(
                2,
                (int) data_get(
                    $agreementAuditData,
                    'definition_version'
                )
            );

            $this->assertSame(
                'data_transformation_bi',
                data_get(
                    $agreementAuditData,
                    'capability_key'
                )
            );

            $this->assertTrue(
                (bool) data_get(
                    $agreementAuditData,
                    'tenant_agreed'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'definition_modified'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'definition_ready'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'mark_ready_used'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'commercial_acceptance'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'commercial_stage_started'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'ready_for_commercial'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'activation_started'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'execution_started'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $agreementAuditData,
                    'subscription_created'
                )
            );
        }

        /*
         * Tenant continues seeing EXACT AGREED V2.
         *
         * Decision endpoints disappear after agreement.
         */
        $this
            ->actingAs(
                $tenant
            )
            ->get(
                $tenantShowUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'App/DataTransformationBi'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                        )
                        ->where(
                            'implementation_request.agreement_endpoint',
                            null
                        )
                        ->where(
                            'implementation_request.changes_request_endpoint',
                            null
                        )
                        ->where(
                            'implementation_request.definition_review.id',
                            (int) $v2->id
                        )
                        ->where(
                            'implementation_request.definition_review.version',
                            2
                        )
                        ->where(
                            'implementation_request.definition_review.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
            );

        /*
         * ==========================================================
         * F6-G4 · ADMIN READ MODEL BEFORE FUNCTIONAL CLOSURE
         * ==========================================================
         */

        $functionalFinalizeUrl =
            route(
                'admin.transformation360.implementation_requests.definition.functional_finalize',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,
                ]
            );

        $implementationRequest->refresh();
        $v1->refresh();
        $v2->refresh();

        $this
            ->actingAs(
                $admin
            )
            ->get(
                $detailUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/ImplementationRequests/Show'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                        )
                        ->where(
                            'functional_closure_context.definition_id',
                            (int) $v2->id
                        )
                        ->where(
                            'functional_closure_context.definition_version',
                            2
                        )
                        ->where(
                            'functional_closure_context.definition_status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'functional_closure_context.definition_ready',
                            false
                        )
                        ->where(
                            'functional_closure_context.ready_at',
                            null
                        )
                        ->where(
                            'functional_closure_context.can_finalize',
                            true
                        )
                        ->where(
                            'actions.can_finalize_definition_functionally',
                            true
                        )
                        ->where(
                            'actions.definition_functional_finalize_endpoint',
                            $functionalFinalizeUrl
                        )
            );

        /*
         * Browser route contains Request only.
         * Tenant cannot execute the LAUDA Admin endpoint.
         */
        $this
            ->actingAs(
                $tenant
            )
            ->post(
                $functionalFinalizeUrl,
                []
            )
            ->assertForbidden();

        /*
         * Snapshots immediately before functional closure.
         */
        $requestBeforeFunctionalClosure =
            (array) DB::table(
                'transformation_implementation_requests'
            )
                ->where(
                    'id',
                    $implementationRequest->id
                )
                ->first();

        $v1BeforeFunctionalClosure =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first();

        $v2FunctionalContentBefore = [
            'implementation_scope' =>
                $v2->implementation_scope,

            'deliverables' =>
                $v2->deliverables,

            'dependencies' =>
                $v2->dependencies,

            'responsibility_model' =>
                $v2->responsibility_model,

            'source_snapshot' =>
                $v2->source_snapshot,

            'internal_notes' =>
                $v2->internal_notes,

            'reviewed_by_user_id' =>
                $v2->reviewed_by_user_id,

            'reviewed_at' =>
                $v2->reviewed_at?->toISOString(),
        ];

        $requestStatusTransitionCountBeforeClosure =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'status_transition'
                )
                ->count();

        $readyForCommercialTransitionCountBefore =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL
                )
                ->count();

        $functionalClosureEventCountBefore =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_functionally_finalized_by_lauda'
                )
                ->count();

        $functionalClosureAuditMaxIdBefore =
            Schema::hasTable(
                'audit_logs'
            )
                ? (int) (
                    DB::table(
                        'audit_logs'
                    )->max(
                        'id'
                    )
                    ?? 0
                )
                : 0;

        /*
         * ==========================================================
         * REAL ADMIN HTTP FUNCTIONAL CLOSURE
         * ==========================================================
         */

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->post(
                $functionalFinalizeUrl,
                []
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();
        $v1->refresh();
        $v2->refresh();

        /*
         * Request lifecycle is byte-for-byte unchanged.
         *
         * Functional closure is a Definition operation only.
         */
        $this->assertSame(
            $requestBeforeFunctionalClosure,
            (array) DB::table(
                'transformation_implementation_requests'
            )
                ->where(
                    'id',
                    $implementationRequest->id
                )
                ->first(),
            'El cierre funcional modificó el Request.'
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->definition_agreed_at
        );

        $this->assertNull(
            $implementationRequest->ready_for_commercial_at
        );

        /*
         * Historical V1 remains byte-for-byte immutable.
         */
        $this->assertSame(
            $v1BeforeFunctionalClosure,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v1->id
                )
                ->first(),
            'V1 histórica fue modificada durante cierre funcional.'
        );

        /*
         * Exact agreed V2 is the Definition finalized.
         */
        $this->assertSame(
            TransformationImplementationDefinition::STATUS_READY,
            $v2->status
        );

        $this->assertSame(
            'ready',
            data_get(
                $v2->readiness,
                'state'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $v2->readiness,
                'definition_ready'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $v2->readiness,
                'technical_readiness'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'execution_started'
            )
        );

        $this->assertNotNull(
            $v2->ready_at
        );

        /*
         * markReady must not alter functional content.
         */
        $this->assertSame(
            $v2FunctionalContentBefore,
            [
                'implementation_scope' =>
                    $v2->implementation_scope,

                'deliverables' =>
                    $v2->deliverables,

                'dependencies' =>
                    $v2->dependencies,

                'responsibility_model' =>
                    $v2->responsibility_model,

                'source_snapshot' =>
                    $v2->source_snapshot,

                'internal_notes' =>
                    $v2->internal_notes,

                'reviewed_by_user_id' =>
                    $v2->reviewed_by_user_id,

                'reviewed_at' =>
                    $v2->reviewed_at?->toISOString(),
            ],
            'El cierre funcional alteró contenido funcional de V2.'
        );

        /*
         * No Request status transition was generated.
         */
        $this->assertSame(
            $requestStatusTransitionCountBeforeClosure,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'status_transition'
                )
                ->count()
        );

        /*
         * No ready_for_commercial transition.
         */
        $this->assertSame(
            $readyForCommercialTransitionCountBefore,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL
                )
                ->count()
        );

        /*
         * Dedicated request-scoped functional closure event.
         */
        $functionalClosureEvents =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_functionally_finalized_by_lauda'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $this->assertCount(
            $functionalClosureEventCountBefore + 1,
            $functionalClosureEvents
        );

        $functionalClosureEvent =
            $functionalClosureEvents->last();

        $this->assertNotNull(
            $functionalClosureEvent
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            $functionalClosureEvent->from_status
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            $functionalClosureEvent->to_status
        );

        $this->assertSame(
            'lauda_admin',
            $functionalClosureEvent->actor_type
        );

        $this->assertSame(
            (int) $admin->id,
            (int) $functionalClosureEvent->actor_user_id
        );

        $functionalClosureMetadata =
            json_decode(
                (string) (
                    $functionalClosureEvent->metadata
                    ?? ''
                ),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) data_get(
                $functionalClosureMetadata,
                'request_id'
            )
        );

        $this->assertSame(
            (int) $v2->id,
            (int) data_get(
                $functionalClosureMetadata,
                'definition_id'
            )
        );

        $this->assertSame(
            2,
            (int) data_get(
                $functionalClosureMetadata,
                'definition_version'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $functionalClosureMetadata,
                'capability_key'
            )
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            data_get(
                $functionalClosureMetadata,
                'definition_status_from'
            )
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_READY,
            data_get(
                $functionalClosureMetadata,
                'definition_status_to'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $functionalClosureMetadata,
                'definition_ready'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $functionalClosureMetadata,
                'technical_readiness'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'execution_started'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'commercial_acceptance'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'commercial_stage_started'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'ready_for_commercial'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'activation_started'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $functionalClosureMetadata,
                'subscription_created'
            )
        );

        /*
         * Request-scoped functional closure audit pins exact V2.
         */
        if (
            Schema::hasTable(
                'audit_logs'
            )
        ) {
            $functionalClosureAudits =
                DB::table(
                    'audit_logs'
                )
                    ->where(
                        'id',
                        '>',
                        $functionalClosureAuditMaxIdBefore
                    )
                    ->where(
                        'event',
                        'transformation_implementation_definition_functionally_finalized_by_lauda'
                    )
                    ->orderBy(
                        'id'
                    )
                    ->get();

            $this->assertCount(
                1,
                $functionalClosureAudits
            );

            $functionalClosureAudit =
                $functionalClosureAudits->first();

            $this->assertSame(
                (int) $v2->id,
                (int) $functionalClosureAudit->model_id
            );

            $functionalClosureAuditData =
                json_decode(
                    (string) (
                        $functionalClosureAudit->data
                        ?? ''
                    ),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            $this->assertSame(
                (int) $implementationRequest->id,
                (int) data_get(
                    $functionalClosureAuditData,
                    'request_id'
                )
            );

            $this->assertSame(
                (int) $v2->id,
                (int) data_get(
                    $functionalClosureAuditData,
                    'definition_id'
                )
            );

            $this->assertSame(
                2,
                (int) data_get(
                    $functionalClosureAuditData,
                    'definition_version'
                )
            );

            $this->assertTrue(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'definition_ready'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'ready_for_execution'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'execution_started'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'commercial_acceptance'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'ready_for_commercial'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'activation_started'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $functionalClosureAuditData,
                    'subscription_created'
                )
            );
        }

        /*
         * ==========================================================
         * ADMIN READ MODEL AFTER FUNCTIONAL CLOSURE
         * ==========================================================
         */

        $this
            ->actingAs(
                $admin
            )
            ->get(
                $detailUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/ImplementationRequests/Show'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                        )
                        ->where(
                            'definition.id',
                            (int) $v2->id
                        )
                        ->where(
                            'definition.version',
                            2
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_READY
                        )
                        ->where(
                            'functional_closure_context.definition_id',
                            (int) $v2->id
                        )
                        ->where(
                            'functional_closure_context.definition_version',
                            2
                        )
                        ->where(
                            'functional_closure_context.definition_status',
                            TransformationImplementationDefinition::STATUS_READY
                        )
                        ->where(
                            'functional_closure_context.definition_ready',
                            true
                        )
                        ->where(
                            'functional_closure_context.ready_at',
                            $v2->ready_at?->toISOString()
                        )
                        ->where(
                            'functional_closure_context.can_finalize',
                            false
                        )
                        ->where(
                            'actions.can_finalize_definition_functionally',
                            false
                        )
                        ->where(
                            'actions.definition_functional_finalize_endpoint',
                            null
                        )
            );

        /*
         * Tenant also continues to see the exact agreed V2,
         * now functionally ready.
         */
        $this
            ->actingAs(
                $tenant
            )
            ->get(
                $tenantShowUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'App/DataTransformationBi'
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED
                        )
                        ->where(
                            'implementation_request.definition_review.id',
                            (int) $v2->id
                        )
                        ->where(
                            'implementation_request.definition_review.version',
                            2
                        )
                        ->where(
                            'implementation_request.definition_review.status',
                            TransformationImplementationDefinition::STATUS_READY
                        )
                        ->where(
                            'implementation_request.agreement_endpoint',
                            null
                        )
                        ->where(
                            'implementation_request.changes_request_endpoint',
                            null
                        )
            );

        /*
         * ==========================================================
         * REPEAT FINALIZATION IS BLOCKED
         * ==========================================================
         */

        $functionalClosureEventCountBeforeReplay =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_functionally_finalized_by_lauda'
                )
                ->count();

        $functionalClosureAuditCountBeforeReplay =
            Schema::hasTable(
                'audit_logs'
            )
                ? DB::table(
                    'audit_logs'
                )
                    ->where(
                        'event',
                        'transformation_implementation_definition_functionally_finalized_by_lauda'
                    )
                    ->where(
                        'model_id',
                        $v2->id
                    )
                    ->count()
                : 0;

        $v2AfterFirstClosure =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first();

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->post(
                $functionalFinalizeUrl,
                []
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHasErrors(
                'definition'
            );

        $implementationRequest->refresh();
        $v2->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
            $implementationRequest->status
        );

        $this->assertNull(
            $implementationRequest->ready_for_commercial_at
        );

        $this->assertSame(
            $v2AfterFirstClosure,
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $v2->id
                )
                ->first(),
            'La segunda finalización volvió a modificar V2.'
        );

        $this->assertSame(
            $functionalClosureEventCountBeforeReplay,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_functionally_finalized_by_lauda'
                )
                ->count()
        );

        if (
            Schema::hasTable(
                'audit_logs'
            )
        ) {
            $this->assertSame(
                $functionalClosureAuditCountBeforeReplay,
                DB::table(
                    'audit_logs'
                )
                    ->where(
                        'event',
                        'transformation_implementation_definition_functionally_finalized_by_lauda'
                    )
                    ->where(
                        'model_id',
                        $v2->id
                    )
                    ->count()
            );
        }

        /*
         * No new Definition/version created by functional closure.
         */
        $this->assertSame(
            2,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        $this->assertSame(
            2,
            (int) TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->max(
                    'version'
                )
        );

        /*
         * No downstream side effects.
         */
        foreach (
            $guards
            as $table => $before
        ) {
            $this->assertSame(
                $before,
                DB::table(
                    $table
                )->count(),
                'Side effect no permitido en '.$table
            );
        }

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_READY,
            $v2->status
        );

        $this->assertTrue(
            (bool) data_get(
                $v2->readiness,
                'definition_ready'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'execution_started'
            )
        );

        $this->assertNotNull(
            $v2->ready_at
        );

        $this->assertNull(
            $implementationRequest->ready_for_commercial_at
        );

}


    private function createTenantBiFixture(
        string $tenantRole = 'owner'
    ): array {
        $suffix =
            Str::lower(
                Str::random(14)
            );

        /*
         * Usuario.
         */
        $user = User::factory()->create([
            'name' =>
                'F3B Tenant QA',

            'email' =>
                'f3b-tenant-'
                .$suffix
                .'@example.test',

            'role' =>
                'subscriber',
        ]);

        /*
         * Subscriber.
         */
        $subscriberId =
            $this->insertFixtureRow(
                'subscribers',
                [
                    'name' =>
                        'F3B Subscriber '.$suffix,

                    'slug' =>
                        'f3b-subscriber-'.$suffix,

                    'currency' =>
                        'DOP',

                    'timezone' =>
                        'America/Santo_Domingo',

                    'active' =>
                        1,
                ]
            );

        /** @var Subscriber $subscriber */
        $subscriber =
            Subscriber::query()
                ->findOrFail(
                    $subscriberId
                );

        /*
         * Membresía tenant.
         */
        $this->insertFixtureRow(
            'subscriber_user',
            [
                'subscriber_id' =>
                    $subscriber->id,

                'user_id' =>
                    $user->id,

                'role' =>
                    $tenantRole,

                'active' =>
                    1,
            ],
            false
        );

        /*
         * Company.
         */
        $companyId =
            $this->insertFixtureRow(
                'companies',
                [
                    'subscriber_id' =>
                        $subscriber->id,

                    'owner_user_id' =>
                        $tenantRole === 'owner'
                            ? $user->id
                            : null,

                    'name' =>
                        'F3B Company '.$suffix,

                    'slug' =>
                        'f3b-company-'.$suffix,

                    'currency' =>
                        'DOP',

                    'timezone' =>
                        'America/Santo_Domingo',

                    'active' =>
                        1,
                ]
            );

        /** @var Company $company */
        $company =
            Company::query()
                ->findOrFail(
                    $companyId
                );

        /*
         * Ayudamos al mismo resolver productivo sin depender
         * de una sola estrategia histórica de contexto.
         */
        $userUpdates = [];

        if (
            Schema::hasColumn(
                'users',
                'company_id'
            )
        ) {
            $userUpdates['company_id'] =
                $company->id;
        }

        if (
            Schema::hasColumn(
                'users',
                'subscriber_id'
            )
        ) {
            $userUpdates['subscriber_id'] =
                $subscriber->id;
        }

        if ($userUpdates !== []) {
            DB::table('users')
                ->where(
                    'id',
                    $user->id
                )
                ->update(
                    $userUpdates
                );
        }

        $user->refresh();

        /*
         * Diagnóstico oficial publicado.
         */
        $assessmentId =
            $this->insertFixtureRow(
                'diagnosis_assessments',
                [
                    'user_id' =>
                        $user->id,

                    'organization_id' =>
                        $company->id,

                    'organization_name' =>
                        $company->name,

                    'methodology_version' =>
                        '1.0',

                    'status' =>
                        'reviewed',

                    'is_active' =>
                        1,

                    'current_step' =>
                        8,

                    'review_required' =>
                        0,

                    'dimension_scores' =>
                        json_encode(
                            [
                                'data' => 0,
                                'people' => 50,
                                'presence' => 50,
                                'strategy' => 50,
                                'commercial' => 50,
                                'governance' => 50,
                                'operations' => 50,
                                'technology' => 50,
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'published_at' =>
                        now(),
                ]
            );

        /** @var DiagnosisAssessment $assessment */
        $assessment =
            DiagnosisAssessment::query()
                ->findOrFail(
                    $assessmentId
                );

        /*
         * Contact + access request:
         * es la fuente real usada por el dashboard para
         * asociar Diagnóstico ↔ Company/Subscriber.
         */
        $contactId =
            $this->insertFixtureRow(
                'contact_requests',
                [
                    'name' =>
                        'F3B Contact '.$suffix,

                    'email' =>
                        'f3b-contact-'
                        .$suffix
                        .'@example.test',

                    'phone' =>
                        '0000000000',

                    'company' =>
                        $company->name,

                    'topic' =>
                        'Diagnóstico LAUDA 360',

                    'message' =>
                        'Fixture HTTP F3B',

                    'terms' =>
                        1,

                    'metadata' =>
                        json_encode(
                            [
                                'source' =>
                                    'f3b_http_fixture',
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                ]
            );

        $this->insertFixtureRow(
            'diagnosis_access_requests',
            [
                'public_id' =>
                    (string) Str::ulid(),

                'contact_request_id' =>
                    $contactId,

                'user_id' =>
                    $user->id,

                'diagnosis_assessment_id' =>
                    $assessment->id,

                'status' =>
                    'active',

                'approved_at' =>
                    now(),

                'meta' =>
                    json_encode(
                        [
                            'source' =>
                                'f3b_http_fixture',

                            'company_id' =>
                                (int) $company->id,

                            'subscriber_id' =>
                                (int) $subscriber->id,

                            'apphub_native' =>
                                true,
                        ],
                        JSON_THROW_ON_ERROR
                    ),
            ]
        );

        /*
         * Roadmap publicado mínimo.
         *
         * No contiene ejecución ni datos comerciales.
         * Solo expone la capability profesional BI.
         */
        $roadmapId =
            $this->insertFixtureRow(
                'diagnosis_detailed_roadmaps',
                [
                    'diagnosis_assessment_id' =>
                        $assessment->id,

                    'source_expanded_report_id' =>
                        null,

                    'version' =>
                        1,

                    'status' =>
                        'published',

                    'generated_by_user_id' =>
                        $user->id,

                    'reviewed_by_user_id' =>
                        $user->id,

                    'methodology_version' =>
                        '1.0',

                    'source_snapshot' =>
                        json_encode(
                            [
                                'source' =>
                                    'f3b_http_fixture',
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'roadmap' =>
                        json_encode(
                            [
                                'transformation_capabilities' => [
                                    'data_transformation_bi' => [
                                        'title' =>
                                            'Transformación e Inteligencia de Datos para BI',

                                        'type' =>
                                            'professional_service',

                                        'recommended' =>
                                            true,

                                        'requires_lauda_review' =>
                                            true,

                                        'activation_policy' =>
                                            'implementation_only',

                                        'purpose' =>
                                            'Fixture funcional F3B.',

                                        'includes' => [
                                            'Datos fundacionales para BI.',
                                        ],
                                    ],
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'reviewed_at' =>
                        now(),

                    'published_at' =>
                        now(),
                ]
            );

        /*
         * Plan presentado.
         */
        $planId =
            $this->insertFixtureRow(
                'transformation_implementation_plans',
                [
                    'diagnosis_assessment_id' =>
                        $assessment->id,

                    'diagnosis_detailed_roadmap_id' =>
                        $roadmapId,

                    'version' =>
                        1,

                    'status' =>
                        TransformationImplementationPlan::STATUS_PRESENTED,

                    'source_snapshot' =>
                        json_encode(
                            [
                                'source_type' =>
                                    'published_roadmap',

                                'assessment_id' =>
                                    (int) $assessment->id,

                                'roadmap_id' =>
                                    $roadmapId,

                                'transformation_capabilities' => [
                                    'data_transformation_bi',
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'created_by_user_id' =>
                        $user->id,

                    'updated_by_user_id' =>
                        $user->id,

                    'presented_at' =>
                        now(),
                ]
            );

        /** @var TransformationImplementationPlan $plan */
        $plan =
            TransformationImplementationPlan::query()
                ->findOrFail(
                    $planId
                );

        /*
         * Fase 3.
         */
        $phaseId =
            $this->insertFixtureRow(
                'transformation_implementation_phases',
                [
                    'transformation_implementation_plan_id' =>
                        $plan->id,

                    'sequence' =>
                        3,

                    'name' =>
                        'Conectar y medir',

                    'objective' =>
                        'Preparar la capa fundacional de datos.',

                    'source_snapshot' =>
                        json_encode(
                            [
                                'horizon' =>
                                    'Fase 3',

                                'dependencies' =>
                                    [],

                                'deliverables' => [
                                    'Capa fundacional de datos',
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'created_by_user_id' =>
                        $user->id,

                    'updated_by_user_id' =>
                        $user->id,
                ]
            );

        /*
         * Capability BI dentro del Plan.
         */
        $phaseCapabilityId =
            $this->insertFixtureRow(
                'transformation_implementation_phase_capabilities',
                [
                    'transformation_implementation_phase_id' =>
                        $phaseId,

                    'sequence' =>
                        1,

                    'capability_key' =>
                        'data_transformation_bi',

                    'capability_label' =>
                        'Transformación e Inteligencia de Datos para BI',

                    'capability_summary' =>
                        'Capa fundacional de datos.',

                    'source_snapshot' =>
                        json_encode(
                            [
                                'capability_key' =>
                                    'data_transformation_bi',

                                'activation_policy' =>
                                    'implementation_only',

                                'requires_lauda_review' =>
                                    true,

                                'includes' => [
                                    'Datos fundacionales para BI',
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                ]
            );

        /** @var TransformationImplementationPhaseCapability $phaseCapability */
        $phaseCapability =
            TransformationImplementationPhaseCapability::query()
                ->findOrFail(
                    $phaseCapabilityId
                );

        return [
            'user' =>
                $user,

            'subscriber' =>
                $subscriber,

            'company' =>
                $company,

            'assessment' =>
                $assessment,

            'plan' =>
                $plan,

            'phase_capability' =>
                $phaseCapability,
        ];
    }

    /**
     * Inserta fixtures directamente para evitar que eventos de
     * modelos creen infraestructura secundaria.
     *
     * También completa automáticamente columnas NOT NULL
     * sin default que sean puramente escalares.
     */
    private function insertFixtureRow(
        string $table,
        array $values,
        bool $returnId = true
    ): int {
        $columns =
            collect(
                DB::select(
                    'SHOW COLUMNS FROM `'
                    .$table
                    .'`'
                )
            );

        $columnNames =
            $columns
                ->pluck('Field')
                ->all();

        if (
            in_array(
                'created_at',
                $columnNames,
                true
            )
            && ! array_key_exists(
                'created_at',
                $values
            )
        ) {
            $values['created_at'] =
                now();
        }

        if (
            in_array(
                'updated_at',
                $columnNames,
                true
            )
            && ! array_key_exists(
                'updated_at',
                $values
            )
        ) {
            $values['updated_at'] =
                now();
        }

        /*
         * Quitar campos opcionales que no existan en una
         * versión histórica concreta del schema.
         */
        $values =
            collect($values)
                ->filter(
                    fn (
                        mixed $value,
                        string $key
                    ): bool =>
                        in_array(
                            $key,
                            $columnNames,
                            true
                        )
                )
                ->all();

        /*
         * Si aparece una columna escalar obligatoria que
         * no estaba en el payload, producir un fixture seguro.
         *
         * Las FKs conocidas ya fueron enviadas explícitamente.
         */
        foreach ($columns as $column) {
            $field =
                (string) $column->Field;

            if (
                $field === 'id'
                || array_key_exists(
                    $field,
                    $values
                )
                || $column->Null === 'YES'
                || $column->Default !== null
                || str_contains(
                    strtolower(
                        (string) $column->Extra
                    ),
                    'auto_increment'
                )
            ) {
                continue;
            }

            if (
                str_ends_with(
                    $field,
                    '_id'
                )
            ) {
                throw new \RuntimeException(
                    'Fixture F3B no definió FK obligatoria: '
                    .$table
                    .'.'
                    .$field
                );
            }

            $values[$field] =
                $this->defaultFixtureValue(
                    $field,
                    (string) $column->Type
                );
        }

        if (! $returnId) {
            DB::table($table)
                ->insert($values);

            return 0;
        }

        return (int) DB::table($table)
            ->insertGetId($values);
    }

    private function defaultFixtureValue(
        string $field,
        string $type
    ): mixed {
        $type =
            strtolower($type);

        if (
            str_contains(
                $type,
                'json'
            )
        ) {
            return json_encode(
                [],
                JSON_THROW_ON_ERROR
            );
        }

        if (
            str_contains(
                $type,
                'timestamp'
            )
            || str_contains(
                $type,
                'datetime'
            )
            || $type === 'date'
        ) {
            return now();
        }

        if (
            str_contains(
                $type,
                'int'
            )
            || str_contains(
                $type,
                'decimal'
            )
            || str_contains(
                $type,
                'float'
            )
            || str_contains(
                $type,
                'double'
            )
        ) {
            return 0;
        }

        if (
            str_starts_with(
                $type,
                'enum('
            )
        ) {
            preg_match(
                "/^enum\\('([^']+)'/",
                $type,
                $matches
            );

            return $matches[1] ?? '';
        }

        if (
            str_contains(
                $field,
                'email'
            )
        ) {
            return Str::lower(
                Str::random(12)
            ).'@example.test';
        }

        if (
            str_contains(
                $field,
                'slug'
            )
        ) {
            return 'qa-'
                .Str::lower(
                    Str::random(12)
                );
        }

        return 'QA F3B';
    }
}
