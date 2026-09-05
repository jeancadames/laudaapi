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

final class DataTransformationBiTenantDefinitionChangesHttpTest
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

    public function test_tenant_can_request_changes_without_mutating_definition_v1(): void
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
                    'F6C2 Admin LAUDA',

                'email' =>
                    'f6c2-admin-'
                    .Str::lower(
                        Str::random(14)
                    )
                    .'@example.test',

                'role' =>
                    'admin',
            ]);

        /** @var TransformationImplementationRequestService $requests */
        $requests =
            app(
                TransformationImplementationRequestService::class
            );

        /*
         * ==========================================================
         * 1. REQUEST -> DEFINITION_PREPARATION
         * ==========================================================
         */

        $implementationRequest =
            $requests
                ->requestFromTenantAdmin(
                    $company,
                    $assessment,
                    $plan,
                    $capability,
                    $tenant,
                    'F6C2 · Solicitud BI.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
                    $admin,
                    'F6C2 · Revisión LAUDA.'
                );

        $implementationRequest =
            $requests
                ->transitionByLauda(
                    $implementationRequest,
                    TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                    $admin,
                    'F6C2 · Preparación Definition.'
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

        $definition =
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

        $definition =
            $autogenerator
                ->generate(
                    $definition,
                    (int) $admin->id
                );

        $definition->refresh();

        $this->assertSame(
            1,
            (int) $definition->version
        );

        /*
         * ==========================================================
         * 3. LAUDA HUMAN REVIEW
         * ==========================================================
         */

        $assignments =
            data_get(
                $definition->responsibility_model,
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

        $definition =
            $reviews
                ->saveReview(
                    $implementationRequest,
                    $definition,
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

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $definition->status
        );

        /*
         * ==========================================================
         * 4. LAUDA SUBMITS V1 TO TENANT
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
                    $definition,
                    $admin,
                    'F6C2 · Definition V1 enviada al tenant.'
                );

        $implementationRequest->refresh();
        $definition->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_UNDER_REVIEW,
            $definition->status
        );

        /*
         * ==========================================================
         * 5. V1 IMMUTABILITY BASELINE
         * ==========================================================
         */

        $v1Before =
            (array) DB::table(
                'transformation_implementation_definitions'
            )
                ->where(
                    'id',
                    $definition->id
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
            1,
            $definitionCountBefore
        );

        $eventCountBefore =
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

        $showUrl =
            route(
                'app.transformation.data_bi.show'
            );

        $changesUrl =
            route(
                'app.transformation.data_bi.definition.request_changes'
            );

        /*
         * ==========================================================
         * 6. TENANT READ MODEL EXPOSES ACTION
         * ==========================================================
         */

        $this
            ->actingAs(
                $tenant
            )
            ->get(
                $showUrl
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'App/DataTransformationBi'
                        )
                        ->where(
                            'implementation_request.id',
                            (int) $implementationRequest->id
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW
                        )
                        ->where(
                            'implementation_request.definition_review.id',
                            (int) $definition->id
                        )
                        ->where(
                            'implementation_request.definition_review.version',
                            1
                        )
                        ->where(
                            'implementation_request.changes_request_endpoint',
                            $changesUrl
                        )
            );

        /*
         * ==========================================================
         * 7. INVALID REASON IS BLOCKED BEFORE DOMAIN TRANSITION
         * ==========================================================
         */

        $this
            ->actingAs(
                $tenant
            )
            ->from(
                $showUrl
            )
            ->post(
                $changesUrl,
                [
                    'reason' =>
                        'corto',
                ]
            )
            ->assertRedirect(
                $showUrl
            )
            ->assertSessionHasErrors(
                'reason'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 8. FOREIGN TENANT IS BLOCKED AT DOMAIN BOUNDARY
         * ==========================================================
         */

        $foreignContext =
            $this->createTenantBiFixture();

        /** @var User $foreignTenant */
        $foreignTenant =
            $foreignContext['user'];

        /** @var TransformationImplementationRequestDefinitionTenantDecisionService $decisions */
        $decisions =
            app(
                TransformationImplementationRequestDefinitionTenantDecisionService::class
            );

        try {
            $decisions
                ->requestChanges(
                    $implementationRequest,
                    $definition,
                    $foreignTenant,
                    'Intento cross-tenant que debe ser rechazado por el dominio.'
                );

            $this->fail(
                'El dominio permitió una decisión cross-tenant.'
            );
        } catch (
            AuthorizationException $exception
        ) {
            $this->assertStringContainsString(
                'empresa',
                mb_strtolower(
                    $exception->getMessage()
                )
            );
        }

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 9. SUBSCRIBER NON-ADMIN IS BLOCKED BY HTTP ACTION
         * ==========================================================
         */

        $regularContext =
            $this->createTenantBiFixture(
                tenantRole: 'user'
            );

        /** @var User $regularTenant */
        $regularTenant =
            $regularContext['user'];

        $this
            ->actingAs(
                $regularTenant
            )
            ->from(
                $showUrl
            )
            ->post(
                $changesUrl,
                [
                    'reason' =>
                        'Este usuario no debe poder solicitar cambios.',
                ]
            )
            ->assertForbidden();

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 10. REAL TENANT POST
         * ==========================================================
         */

        $reason =
            'Necesitamos precisar las responsabilidades del entregable de calidad de datos y los accesos que debe suministrar nuestra empresa.';

        $this
            ->actingAs(
                $tenant
            )
            ->from(
                $showUrl
            )
            ->post(
                $changesUrl,
                [
                    'reason' =>
                        $reason,
                ]
            )
            ->assertRedirect(
                $showUrl
            )
            ->assertSessionHas(
                'success'
            );

        $implementationRequest->refresh();
        $definition->refresh();

        /*
         * ==========================================================
         * 11. REQUEST MOVED EXACTLY ONCE
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest
                ->changes_requested_at
        );

        $eventCountAfter =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->assertSame(
            $eventCountBefore + 1,
            $eventCountAfter
        );

        $changesEvent =
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
                    $reason
                )
                ->first();

        $this->assertNotNull(
            $changesEvent
        );

        $this->assertSame(
            (int) $tenant->id,
            (int) $changesEvent
                ->actor_user_id
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
                    $definition->id
                )
                ->first();

        $this->assertSame(
            $v1Before,
            $v1After,
            'Definition V1 cambió durante changes_requested.'
        );

        $definitionCountAfter =
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
            $definitionCountAfter
        );

        $this->assertSame(
            1,
            (int) $definition->version
        );

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
         * 13. SPECIFIC AUDIT PINS V1 + REASON
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
                'transformation_implementation_definition_changes_requested_by_tenant',
                $auditJson
            );

            $this->assertStringContainsString(
                $reason,
                $auditJson
            );

            $this->assertStringContainsString(
                'data_transformation_bi',
                $auditJson
            );
        }

        /*
         * ==========================================================
         * 14. READ MODEL AFTER CHANGES
         * ==========================================================
         *
         * V1 remains visible as historical presented Definition,
         * but the action endpoint disappears.
         */

        $this
            ->actingAs(
                $tenant
            )
            ->get(
                $showUrl
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
                            TransformationImplementationRequestContract::STATUS_CHANGES_REQUESTED
                        )
                        ->where(
                            'implementation_request.definition_review.id',
                            (int) $definition->id
                        )
                        ->where(
                            'implementation_request.definition_review.version',
                            1
                        )
                        ->where(
                            'implementation_request.changes_request_endpoint',
                            null
                        )
            );

        /*
         * ==========================================================
         * 15. SECOND POST CANNOT CREATE SECOND TRANSITION
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
                $tenant
            )
            ->post(
                $changesUrl,
                [
                    'reason' =>
                        'Segundo intento que debe permanecer bloqueado.',
                ]
            )
            ->assertNotFound();

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
         * 16. DOWNSTREAM SIDE EFFECTS REMAIN ZERO
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
                'Side effect no permitido en '
                .$table
            );
        }
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
