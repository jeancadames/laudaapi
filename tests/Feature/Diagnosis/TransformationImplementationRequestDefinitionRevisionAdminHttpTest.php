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

final class TransformationImplementationRequestDefinitionRevisionAdminHttpTest
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

    public function test_admin_can_create_v2_from_tenant_changes_without_mutating_v1(): void
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
                    'F6D2C Admin LAUDA',

                'email' =>
                    'f6d2c-admin-'
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
                    'F6D2-C · Solicitud BI.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
                    $admin,
                    'F6D2-C · Revisión inicial LAUDA.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                    $admin,
                    'F6D2-C · Preparación de Definition V1.'
                );

        /*
         * ==========================================================
         * 2. CREATE + AUTOGENERATE V1
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

        $this->assertSame(
            1,
            (int) $v1->version
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $v1->status
        );

        $this->assertSame(
            'prepared_for_review',
            data_get(
                $v1->readiness,
                'state'
            )
        );

        /*
         * ==========================================================
         * 3. HUMAN REVIEW V1
         * ==========================================================
         */

        $assignments =
            data_get(
                $v1->responsibility_model,
                'assignments',
                []
            );

        $this->assertNotEmpty(
            $assignments
        );

        $parties = [
            'lauda',
            'client',
            'shared',
        ];

        $reviewAssignments =
            collect(
                $assignments
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
                                $reviewAssignments,
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

        $v1->refresh();

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v1->status
        );

        $this->assertSame(
            'under_review',
            data_get(
                $v1->readiness,
                'state'
            )
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
                    'F6D2-C · V1 enviada a revisión de la empresa.'
                );

        $implementationRequest->refresh();
        $v1->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 5. TENANT REQUESTS CHANGES VIA REAL HTTP ACTION
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
            'Ajustar el alcance de calidad de datos, precisar los entregables de homologación y definir claramente los accesos que aportará nuestra empresa.';

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
        $v1->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->changes_requested_at
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v1->status
        );

        /*
         * ==========================================================
         * 6. EXACT TENANT CHANGE EVENT
         * ==========================================================
         */

        $changeEvent =
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
                    TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
                )
                ->where(
                    'notes',
                    $tenantReason
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        $this->assertNotNull(
            $changeEvent
        );

        /*
         * ==========================================================
         * 7. V1 IMMUTABILITY BASELINE
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

        $v1Scope =
            $v1->implementation_scope;

        $v1Deliverables =
            $v1->deliverables;

        $v1Dependencies =
            $v1->dependencies;

        $v1Responsibilities =
            $v1->responsibility_model;

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
            1,
            $definitionCountBefore
        );

        $eventCountBeforeRevision =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
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

        /*
         * ==========================================================
         * 8. ADMIN READ MODEL BEFORE REVISION
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
                            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
                        )
                        ->where(
                            'definition.id',
                            (int) $v1->id
                        )
                        ->where(
                            'definition.version',
                            1
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
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
                            'definition_revision_context.previous_definition_status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'definition_revision_context.tenant_change_reason',
                            $tenantReason
                        )
                        ->where(
                            'actions.can_create_definition_revision',
                            true
                        )
                        ->where(
                            'actions.definition_revision_endpoint',
                            $revisionUrl
                        )
            );

        /*
         * ==========================================================
         * 9. NON-ADMIN CANNOT CREATE REVISION
         * ==========================================================
         */

        $regularContext =
            $this->createTenantBiFixture(
                tenantRole: 'user'
            );

        /** @var User $regular */
        $regular =
            $regularContext['user'];

        $this
            ->actingAs(
                $regular
            )
            ->from(
                $detailUrl
            )
            ->post(
                $revisionUrl
            )
            ->assertForbidden();

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
            $implementationRequest->status
        );

        $this->assertSame(
            1,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        /*
         * ==========================================================
         * 10. ADMIN HTTP POST · PREPARAR NUEVA VERSIÓN
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
                $revisionUrl
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();
        $v1->refresh();

        /*
         * ==========================================================
         * 11. REQUEST LIFECYCLE
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->definition_started_at
        );

        /*
         * ==========================================================
         * 12. V1 BYTE-FOR-BYTE IMMUTABLE
         * ==========================================================
         */

        $v1After =
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
            $v1After,
            'Definition V1 fue modificada al crear V2.'
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $v1->status
        );

        $this->assertSame(
            1,
            (int) $v1->version
        );

        /*
         * ==========================================================
         * 13. EXACTLY V1 + V2
         * ==========================================================
         */

        $versions =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->orderBy(
                    'version'
                )
                ->orderBy(
                    'id'
                )
                ->get();

        $this->assertCount(
            2,
            $versions
        );

        $this->assertSame(
            [
                1,
                2,
            ],
            $versions
                ->pluck(
                    'version'
                )
                ->map(
                    fn ($version): int =>
                        (int) $version
                )
                ->all()
        );

        /** @var TransformationImplementationDefinition $v2 */
        $v2 =
            $versions->last();

        $this->assertSame(
            2,
            (int) $v2->version
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $v2->status
        );

        /*
         * ==========================================================
         * 14. V2 EXACT REQUEST SCOPE
         * ==========================================================
         */

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) $v2
                ->transformation_implementation_request_id
        );

        $this->assertSame(
            (int) $implementationRequest->company_id,
            (int) $v2->company_id
        );

        $this->assertSame(
            (int) $implementationRequest->diagnosis_assessment_id,
            (int) $v2->diagnosis_assessment_id
        );

        $this->assertSame(
            (int) $implementationRequest->transformation_implementation_plan_id,
            (int) $v2->transformation_implementation_plan_id
        );

        $this->assertSame(
            (int) $implementationRequest->transformation_implementation_phase_capability_id,
            (int) $v2
                ->transformation_implementation_phase_capability_id
        );

        $this->assertSame(
            'data_transformation_bi',
            $v2->capability_key
        );

        $this->assertSame(
            'implementation_request',
            data_get(
                $v2->source_snapshot,
                'source_type'
            )
        );

        $this->assertSame(
            'single_capability',
            data_get(
                $v2->implementation_scope,
                'scope_mode'
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
         * 15. FUNCTIONAL BASELINE COPIED
         * ==========================================================
         */

        $this->assertSame(
            $v1Scope,
            $v2->implementation_scope
        );

        $this->assertSame(
            $v1Deliverables,
            $v2->deliverables
        );

        $this->assertSame(
            $v1Dependencies,
            $v2->dependencies
        );

        $this->assertSame(
            data_get(
                $v1Responsibilities,
                'assignments'
            ),
            data_get(
                $v2->responsibility_model,
                'assignments'
            )
        );

        /*
         * ==========================================================
         * 16. RESPONSIBILITY CONFIRMATION RESET
         * ==========================================================
         */

        $this->assertSame(
            'to_be_defined',
            data_get(
                $v2->responsibility_model,
                'party_assignment_status'
            )
        );

        $this->assertTrue(
            data_get(
                $v2->responsibility_model,
                'confirmation_required'
            )
        );

        /*
         * ==========================================================
         * 17. HUMAN REVIEW / READINESS RESET
         * ==========================================================
         */

        $this->assertSame(
            'prepared_for_review',
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

        $this->assertFalse(
            (bool) data_get(
                $v2->readiness,
                'human_review_completed'
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
            $this->assertFalse(
                (bool) data_get(
                    $v2->readiness,
                    'human_validation.'.$key
                ),
                'Confirmación no reiniciada: '.$key
            );
        }

        $this->assertNull(
            $v2->reviewed_by_user_id
        );

        $this->assertNull(
            $v2->reviewed_at
        );

        $this->assertNull(
            $v2->ready_at
        );

        /*
         * ==========================================================
         * 18. TENANT CHANGE PROVENANCE
         * ==========================================================
         */

        $this->assertSame(
            (int) $v1->id,
            (int) data_get(
                $v2->source_snapshot,
                'revision.revision_of_definition_id'
            )
        );

        $this->assertSame(
            1,
            (int) data_get(
                $v2->source_snapshot,
                'revision.revision_of_definition_version'
            )
        );

        $this->assertSame(
            (int) $changeEvent->id,
            (int) data_get(
                $v2->source_snapshot,
                'revision.tenant_change_event_id'
            )
        );

        $this->assertSame(
            $tenantReason,
            data_get(
                $v2->source_snapshot,
                'revision.tenant_change_reason'
            )
        );

        /*
         * ==========================================================
         * 19. EXACT REQUEST TRANSITION EVENT
         * ==========================================================
         */

        $eventCountAfterRevision =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->assertSame(
            $eventCountBeforeRevision + 1,
            $eventCountAfterRevision
        );

        $revisionTransition =
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
                    TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
                )
                ->where(
                    'to_status',
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                )
                ->orderByDesc(
                    'id'
                )
                ->first();

        $this->assertNotNull(
            $revisionTransition
        );

        $this->assertSame(
            (int) $admin->id,
            (int) $revisionTransition->actor_user_id
        );

        /*
         * ==========================================================
         * 20. SPECIFIC REVISION AUDIT
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
                        $auditMaxIdBefore
                    )
                    ->orderBy(
                        'id'
                    )
                    ->get();

            $auditJson =
                json_encode(
                    $newAudits,
                    JSON_THROW_ON_ERROR
                );

            $this->assertStringContainsString(
                'transformation_implementation_definition_revision_created',
                $auditJson
            );

            /*
             * No comparamos la razón humana contra json_encode()
             * de la fila completa porque JSON escapa Unicode
             * (\u00f3, \u00e1, etc.).
             *
             * Decodificamos audit_logs.data y comparamos el valor
             * semántico real.
             */
            $revisionAudit =
                $newAudits
                    ->map(
                        function ($audit) {
                            $data =
                                json_decode(
                                    (string) ($audit->data ?? ''),
                                    true
                                );

                            return [
                                'event' =>
                                    (string) ($audit->event ?? ''),

                                'data' =>
                                    is_array($data)
                                        ? $data
                                        : [],
                            ];
                        }
                    )
                    ->first(
                        fn (array $audit): bool =>
                            $audit['event']
                                === 'transformation_implementation_definition_revision_created'
                    );

            $this->assertNotNull(
                $revisionAudit
            );

            $this->assertSame(
                $tenantReason,
                data_get(
                    $revisionAudit,
                    'data.tenant_change_reason'
                )
            );

            $this->assertSame(
                'data_transformation_bi',
                data_get(
                    $revisionAudit,
                    'data.capability_key'
                )
            );

            $this->assertSame(
                1,
                (int) data_get(
                    $revisionAudit,
                    'data.previous_definition_version'
                )
            );

            $this->assertSame(
                2,
                (int) data_get(
                    $revisionAudit,
                    'data.revision_definition_version'
                )
            );

            $this->assertFalse(
                (bool) data_get(
                    $revisionAudit,
                    'data.previous_definition_modified'
                )
            );
        }

        /*
         * ==========================================================
         * 21. ADMIN READ MODEL AFTER REVISION
         * ==========================================================
         */

        $generateV2Url =
            route(
                'admin.transformation360.implementation_requests.definition.generate',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $v2->id,
                ]
            );

        $reviewV2Url =
            route(
                'admin.transformation360.implementation_requests.definition.review',
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
                            TransformationImplementationDefinition::STATUS_DRAFT
                        )
                        ->where(
                            'definition.content_prepared',
                            true
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
                            'definition_revision_context.previous_definition_status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
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
                            'actions.can_create_definition_revision',
                            false
                        )
                        ->where(
                            'actions.definition_revision_endpoint',
                            null
                        )
                        ->where(
                            'actions.can_generate_definition',
                            false
                        )
                        ->where(
                            'actions.definition_generate_endpoint',
                            $generateV2Url
                        )
                        ->where(
                            'actions.can_review_definition',
                            true
                        )
                        ->where(
                            'actions.definition_review_endpoint',
                            $reviewV2Url
                        )
            );

        /*
         * ==========================================================
         * 22. SECOND REVISION POST IS BLOCKED
         * ==========================================================
         */

        $eventCountBeforeSecond =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

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
            ->assertSessionHasErrors(
                'request'
            );

        $implementationRequest->refresh();

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

        $eventCountAfterSecond =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->assertSame(
            $eventCountBeforeSecond,
            $eventCountAfterSecond
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

        $v2->refresh();

        $this->assertFalse(
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

        $this->assertNull(
            $implementationRequest
                ->definition_agreed_at
        );

        $this->assertNull(
            $implementationRequest
                ->ready_for_commercial_at
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
