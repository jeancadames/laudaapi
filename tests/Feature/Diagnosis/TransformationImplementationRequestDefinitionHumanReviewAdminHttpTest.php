<?php

namespace Tests\Feature\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAssessment;
use App\Models\Subscriber;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransformationImplementationRequestDefinitionHumanReviewAdminHttpTest
    extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_save_request_scoped_human_review_without_advancing_lifecycle(): void
    {
        $context =
            $this->fixture();

        /** @var User $admin */
        $admin =
            $context['admin'];

        /** @var User $regular */
        $regular =
            $context['regular'];

        /** @var TransformationImplementationRequest $implementationRequest */
        $implementationRequest =
            $context['request'];

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $detailUrl =
            route(
                'admin.transformation360.implementation_requests.show',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,
                ]
            );

        $createUrl =
            route(
                'admin.transformation360.implementation_requests.definition.create',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,
                ]
            );

        /*
         * ==========================================================
         * 1. CREATE DEFINITION
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
                $createUrl
            )
            ->assertRedirect(
                $detailUrl
            );

        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->sole();

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $definition->status
        );

        $generateUrl =
            route(
                'admin.transformation360.implementation_requests.definition.generate',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $definition->id,
                ]
            );

        $reviewUrl =
            route(
                'admin.transformation360.implementation_requests.definition.review',
                [
                    'implementationRequest' =>
                        $implementationRequest->id,

                    'definition' =>
                        $definition->id,
                ]
            );

        /*
         * ==========================================================
         * 2. AUTOGENERATE BI CONTENT
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
                $generateUrl
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $definition->refresh();

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $definition->status
        );

        $this->assertSame(
            'prepared_for_review',
            data_get(
                $definition->readiness,
                'state'
            )
        );

        $this->assertSame(
            'single_capability',
            data_get(
                $definition->implementation_scope,
                'scope_mode'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $definition->implementation_scope,
                'capability_key'
            )
        );

        /*
         * ==========================================================
         * 3. RESPONSIBILITY PAYLOAD
         * ==========================================================
         */

        $assignments =
            data_get(
                $definition->responsibility_model,
                'assignments',
                []
            );

        $this->assertIsArray(
            $assignments
        );

        $this->assertNotEmpty(
            $assignments,
            'La Definition BI debe producir al menos una responsabilidad funcional.'
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

        $reviewPayload = [
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
        ];

        /*
         * ==========================================================
         * 4. GET BEFORE REVIEW
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
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'definition.id',
                            $definition->id
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
                            'actions.can_generate_definition',
                            false
                        )
                        ->where(
                            'actions.can_review_definition',
                            true
                        )
                        ->where(
                            'actions.definition_review_endpoint',
                            $reviewUrl
                        )
            );

        /*
         * ==========================================================
         * 5. NON ADMIN CANNOT REVIEW
         * ==========================================================
         */

        $this
            ->actingAs(
                $regular
            )
            ->from(
                $detailUrl
            )
            ->patch(
                $reviewUrl,
                $reviewPayload
            )
            ->assertForbidden();

        $definition->refresh();

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $definition->status
        );

        /*
         * ==========================================================
         * 6. REQUEST HISTORY BASELINE
         * ==========================================================
         */

        $reviewEventsBefore =
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

        /*
         * ==========================================================
         * 7. ADMIN HUMAN REVIEW
         * ==========================================================
         */

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->patch(
                $reviewUrl,
                $reviewPayload
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $definition->refresh();

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $definition->status
        );

        $this->assertNotNull(
            $definition->reviewed_at
        );

        $this->assertSame(
            (int) $admin->id,
            (int) $definition
                ->reviewed_by_user_id
        );

        $this->assertNull(
            $definition->ready_at
        );

        /*
         * ==========================================================
         * 8. HUMAN VALIDATION
         * ==========================================================
         */

        $this->assertSame(
            'under_review',
            data_get(
                $definition->readiness,
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
        ] as $confirmation) {
            $this->assertTrue(
                (bool) data_get(
                    $definition->readiness,
                    'human_validation.'
                    .$confirmation
                ),
                'Confirmación humana faltante: '
                .$confirmation
            );
        }

        $this->assertFalse(
            (bool) data_get(
                $definition->readiness,
                'definition_ready'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $definition->readiness,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $definition->readiness,
                'execution_started'
            )
        );

        /*
         * ==========================================================
         * 9. RESPONSIBILITIES CONFIRMED
         * ==========================================================
         */

        $this->assertSame(
            'confirmed',
            data_get(
                $definition->responsibility_model,
                'party_assignment_status'
            )
        );

        $reviewedAssignments =
            data_get(
                $definition->responsibility_model,
                'assignments',
                []
            );

        $this->assertCount(
            count(
                $reviewAssignments
            ),
            $reviewedAssignments
        );

        foreach (
            $reviewedAssignments
            as $assignment
        ) {
            $this->assertContains(
                $assignment[
                    'responsible_party'
                ] ?? null,
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
                ] ?? null
            );
        }

        /*
         * ==========================================================
         * 10. REQUEST DOES NOT ADVANCE
         * ==========================================================
         */

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 11. REQUEST HISTORY EVENT
         * ==========================================================
         */

        $reviewEventsAfter =
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
            $reviewEventsBefore + 1,
            $reviewEventsAfter
        );

        $reviewEvent =
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
                ->latest('id')
                ->first();

        $this->assertNotNull(
            $reviewEvent
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $reviewEvent->from_status
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $reviewEvent->to_status
        );

        $this->assertSame(
            'lauda_admin',
            $reviewEvent->actor_type
        );

        /*
         * ==========================================================
         * 12. GET AFTER REVIEW
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
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_UNDER_REVIEW
                        )
                        ->where(
                            'definition.content_prepared',
                            true
                        )
                        ->where(
                            'actions.can_generate_definition',
                            false
                        )
                        ->where(
                            'actions.can_review_definition',
                            true
                        )
                        ->where(
                            'definition_review.readiness.state',
                            'under_review'
                        )
                        ->where(
                            'definition_review.readiness.human_validation.scope_confirmed',
                            true
                        )
                        ->where(
                            'definition_review.readiness.human_validation.deliverables_confirmed',
                            true
                        )
                        ->where(
                            'definition_review.readiness.human_validation.dependencies_confirmed',
                            true
                        )
                        ->where(
                            'definition_review.readiness.human_validation.inputs_validated',
                            true
                        )
                        ->where(
                            'definition_review.readiness.human_validation.accesses_validated',
                            true
                        )
                        ->where(
                            'definition_review.readiness.human_validation.responsibilities_confirmed',
                            true
                        )
                        ->where(
                            'definition_review.responsibility_model.party_assignment_status',
                            'confirmed'
                        )
            );

        /*
         * ==========================================================
         * 13. AUTOGENERATION MUST BE BLOCKED AFTER HUMAN REVIEW
         * ==========================================================
         */

        $protectedContent =
            json_encode(
                [
                    'implementation_scope' =>
                        $definition
                            ->implementation_scope,

                    'deliverables' =>
                        $definition
                            ->deliverables,

                    'dependencies' =>
                        $definition
                            ->dependencies,

                    'responsibility_model' =>
                        $definition
                            ->responsibility_model,

                    'readiness' =>
                        $definition
                            ->readiness,

                    'status' =>
                        $definition
                            ->status,

                    'reviewed_at' =>
                        $definition
                            ->reviewed_at
                            ?->toISOString(),

                    'reviewed_by_user_id' =>
                        $definition
                            ->reviewed_by_user_id,
                ],
                JSON_THROW_ON_ERROR
            );

        $auditBeforeBlockedGenerate =
            Schema::hasTable(
                'audit_logs'
            )
                ? DB::table(
                    'audit_logs'
                )->count()
                : null;

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->post(
                $generateUrl
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHasErrors(
                'definition'
            );

        $definition->refresh();

        $this->assertSame(
            $protectedContent,
            json_encode(
                [
                    'implementation_scope' =>
                        $definition
                            ->implementation_scope,

                    'deliverables' =>
                        $definition
                            ->deliverables,

                    'dependencies' =>
                        $definition
                            ->dependencies,

                    'responsibility_model' =>
                        $definition
                            ->responsibility_model,

                    'readiness' =>
                        $definition
                            ->readiness,

                    'status' =>
                        $definition
                            ->status,

                    'reviewed_at' =>
                        $definition
                            ->reviewed_at
                            ?->toISOString(),

                    'reviewed_by_user_id' =>
                        $definition
                            ->reviewed_by_user_id,
                ],
                JSON_THROW_ON_ERROR
            )
        );

        if (
            $auditBeforeBlockedGenerate
            !== null
        ) {
            $this->assertSame(
                $auditBeforeBlockedGenerate,
                DB::table(
                    'audit_logs'
                )->count()
            );
        }

        /*
         * ==========================================================
         * 14. SECOND HUMAN REVIEW IS ALLOWED
         * ==========================================================
         */

        $secondPayload =
            $reviewPayload;

        /*
         * Change one assignment deliberately to prove that an
         * under_review Definition remains human-editable.
         */
        $secondPayload[
            'responsibility_model'
        ][
            'assignments'
        ][0][
            'responsible_party'
        ] =
            (
                $secondPayload[
                    'responsibility_model'
                ][
                    'assignments'
                ][0][
                    'responsible_party'
                ]
                === 'shared'
            )
                ? 'lauda'
                : 'shared';

        $this
            ->actingAs(
                $admin
            )
            ->from(
                $detailUrl
            )
            ->patch(
                $reviewUrl,
                $secondPayload
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $definition->refresh();

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $definition->status
        );

        $this->assertSame(
            $secondPayload[
                'responsibility_model'
            ][
                'assignments'
            ][0][
                'responsible_party'
            ],
            data_get(
                $definition->responsibility_model,
                'assignments.0.responsible_party'
            )
        );

        $this->assertSame(
            $reviewEventsBefore + 2,
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
                ->count()
        );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 15. STILL NOT READY / NOT TENANT REVIEW
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $definition->status
        );

        $this->assertFalse(
            (bool) data_get(
                $definition->readiness,
                'definition_ready'
            )
        );

        $this->assertNull(
            $definition->ready_at
        );

        /*
         * ==========================================================
         * 16. NO DOWNSTREAM SIDE EFFECTS
         * ==========================================================
         */

        foreach (
            $context[
                'guard_counts'
            ]
            as $table => $count
        ) {
            $this->assertSame(
                $count,
                DB::table(
                    $table
                )->count(),
                'Side effect no permitido: '
                .$table
            );
        }
    }

    private function fixture(): array
    {
        $suffix =
            Str::lower(
                Str::random(14)
            );

        /*
         * ==========================================================
         * USERS
         * ==========================================================
         */

        $tenant =
            User::factory()->create([
                'name' =>
                    'F5D2 Tenant Admin',

                'email' =>
                    'f5d2-tenant-'
                    .$suffix
                    .'@example.test',

                'role' =>
                    'subscriber',
            ]);

        $admin =
            User::factory()->create([
                'name' =>
                    'F5D2 Admin LAUDA',

                'email' =>
                    'f5d2-admin-'
                    .$suffix
                    .'@example.test',

                'role' =>
                    'admin',
            ]);

        $regular =
            User::factory()->create([
                'name' =>
                    'F5D2 Regular',

                'email' =>
                    'f5d2-regular-'
                    .$suffix
                    .'@example.test',

                'role' =>
                    'subscriber',
            ]);

        /*
         * ==========================================================
         * SUBSCRIBER
         * ==========================================================
         */

        $subscriberId =
            $this->insertFixtureRow(
                'subscribers',
                [
                    'name' =>
                        'F5D2 Subscriber '
                        .$suffix,

                    'slug' =>
                        'f5d2-subscriber-'
                        .$suffix,

                    'currency' =>
                        'DOP',

                    'timezone' =>
                        'America/Santo_Domingo',

                    'active' =>
                        1,
                ]
            );

        $subscriber =
            Subscriber::query()
                ->findOrFail(
                    $subscriberId
                );

        $this->insertFixtureRow(
            'subscriber_user',
            [
                'subscriber_id' =>
                    $subscriber->id,

                'user_id' =>
                    $tenant->id,

                'role' =>
                    'owner',

                'active' =>
                    1,
            ],
            false
        );

        /*
         * ==========================================================
         * COMPANY
         * ==========================================================
         */

        $companyId =
            $this->insertFixtureRow(
                'companies',
                [
                    'subscriber_id' =>
                        $subscriber->id,

                    'owner_user_id' =>
                        $tenant->id,

                    'name' =>
                        'F5D2 Company '
                        .$suffix,

                    'slug' =>
                        'f5d2-company-'
                        .$suffix,

                    'currency' =>
                        'DOP',

                    'timezone' =>
                        'America/Santo_Domingo',

                    'active' =>
                        1,
                ]
            );

        $company =
            Company::query()
                ->findOrFail(
                    $companyId
                );

        $userUpdates = [];

        if (
            Schema::hasColumn(
                'users',
                'company_id'
            )
        ) {
            $userUpdates[
                'company_id'
            ] =
                $company->id;
        }

        if (
            Schema::hasColumn(
                'users',
                'subscriber_id'
            )
        ) {
            $userUpdates[
                'subscriber_id'
            ] =
                $subscriber->id;
        }

        if (
            $userUpdates !== []
        ) {
            DB::table(
                'users'
            )
                ->where(
                    'id',
                    $tenant->id
                )
                ->update(
                    $userUpdates
                );
        }

        $tenant->refresh();

        /*
         * ==========================================================
         * ASSESSMENT
         * ==========================================================
         */

        $assessmentId =
            $this->insertFixtureRow(
                'diagnosis_assessments',
                [
                    'user_id' =>
                        $tenant->id,

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
                                'data' =>
                                    0,
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'published_at' =>
                        now(),
                ]
            );

        $assessment =
            DiagnosisAssessment::query()
                ->findOrFail(
                    $assessmentId
                );

        /*
         * ==========================================================
         * CONTACT + ACCESS
         * ==========================================================
         */

        $contactId =
            $this->insertFixtureRow(
                'contact_requests',
                [
                    'name' =>
                        'F5D2 Contact '
                        .$suffix,

                    'email' =>
                        'f5d2-contact-'
                        .$suffix
                        .'@example.test',

                    'phone' =>
                        '0000000000',

                    'company' =>
                        $company->name,

                    'topic' =>
                        'Diagnóstico LAUDA 360',

                    'message' =>
                        'Fixture F5D2',

                    'terms' =>
                        1,

                    'metadata' =>
                        json_encode(
                            [
                                'source' =>
                                    'f5d2_fixture',
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
                    $tenant->id,

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
                                'f5d2_fixture',

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
         * ==========================================================
         * ROADMAP
         * ==========================================================
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
                        $admin->id,

                    'reviewed_by_user_id' =>
                        $admin->id,

                    'methodology_version' =>
                        '1.0',

                    'source_snapshot' =>
                        json_encode(
                            [
                                'source' =>
                                    'f5d2_fixture',
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

                                        'kind' =>
                                            'professional_service',

                                        'recommended' =>
                                            true,

                                        'requires_lauda_review' =>
                                            true,

                                        'activation_policy' =>
                                            'implementation_only',
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
         * ==========================================================
         * PLAN PRESENTED
         * ==========================================================
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
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'created_by_user_id' =>
                        $admin->id,

                    'updated_by_user_id' =>
                        $admin->id,

                    'presented_at' =>
                        now(),
                ]
            );

        $plan =
            TransformationImplementationPlan::query()
                ->findOrFail(
                    $planId
                );

        /*
         * ==========================================================
         * PHASE
         * ==========================================================
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
                                'dependencies' =>
                                    [],

                                'deliverables' => [
                                    'Capa fundacional BI',
                                ],
                            ],
                            JSON_THROW_ON_ERROR
                        ),

                    'created_by_user_id' =>
                        $admin->id,

                    'updated_by_user_id' =>
                        $admin->id,
                ]
            );

        /*
         * ==========================================================
         * BI CAPABILITY
         * ==========================================================
         */

        $capabilityId =
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
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                ]
            );

        $capability =
            TransformationImplementationPhaseCapability::query()
                ->findOrFail(
                    $capabilityId
                );

        /*
         * ==========================================================
         * REQUEST REAL
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
                    'F5D2 · Solicitud BI.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
                    $admin,
                    'F5D2 · revisión.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                    $admin,
                    'F5D2 · preparación de Definition.'
                );

        /*
         * ==========================================================
         * DOWNSTREAM GUARDS
         * ==========================================================
         */

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
            $guardTables as $table
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

        return [
            'tenant' =>
                $tenant,

            'admin' =>
                $admin,

            'regular' =>
                $regular,

            'company' =>
                $company,

            'assessment' =>
                $assessment,

            'plan' =>
                $plan,

            'capability' =>
                $capability,

            'request' =>
                $implementationRequest,

            'guard_counts' =>
                $guards,
        ];
    }

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
                ->pluck(
                    'Field'
                )
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
            $values[
                'created_at'
            ] =
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
            $values[
                'updated_at'
            ] =
                now();
        }

        $values =
            collect(
                $values
            )
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

        foreach (
            $columns as $column
        ) {
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
                    'Fixture F5D2 no definió FK obligatoria: '
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
            DB::table(
                $table
            )->insert(
                $values
            );

            return 0;
        }

        return (int) DB::table(
            $table
        )->insertGetId(
            $values
        );
    }

    private function defaultFixtureValue(
        string $field,
        string $type
    ): mixed {
        $type =
            strtolower(
                $type
            );

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

            return $matches[1]
                ?? '';
        }

        if (
            str_contains(
                $field,
                'email'
            )
        ) {
            return Str::lower(
                Str::random(12)
            )
                .'@example.test';
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

        return 'QA F5D2';
    }
}
