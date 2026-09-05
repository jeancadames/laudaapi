<?php

namespace Tests\Feature\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAssessment;
use App\Models\Subscriber;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\TransformationImplementationRequest;
use App\Models\User;
use App\Services\Diagnosis\TransformationImplementationRequestContract;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransformationImplementationRequestAdminMutationHttpTest
    extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        /*
         * El objetivo de F4C2 es autorización Admin LAUDA
         * y lifecycle de la solicitud.
         */
        $this->withoutMiddleware(
            EnsureEmailIsVerified::class
        );
    }

    public function test_admin_can_assign_receive_and_start_definition_preparation(): void
    {
        $context =
            $this->createRequestedBiFixture();

        /** @var TransformationImplementationRequest $implementationRequest */
        $implementationRequest =
            $context['request'];

        /** @var User $admin */
        $admin =
            User::factory()->create([
                'name' =>
                    'F4C Admin Actor',

                'email' =>
                    'f4c-admin-actor-'
                    .Str::lower(
                        Str::random(12)
                    )
                    .'@example.test',

                'role' =>
                    'admin',
            ]);

        /** @var User $assignee */
        $assignee =
            User::factory()->create([
                'name' =>
                    'F4C Admin Responsable',

                'email' =>
                    'f4c-admin-assignee-'
                    .Str::lower(
                        Str::random(12)
                    )
                    .'@example.test',

                'role' =>
                    'admin',
            ]);

        /** @var User $nonAdmin */
        $nonAdmin =
            User::factory()->create([
                'name' =>
                    'F4C Non Admin',

                'email' =>
                    'f4c-non-admin-'
                    .Str::lower(
                        Str::random(12)
                    )
                    .'@example.test',

                'role' =>
                    'subscriber',
            ]);

        /*
         * Guardias de frontera.
         *
         * Se capturan DESPUÉS de crear la solicitud porque
         * F3 ya certificó su creación. Aquí medimos únicamente
         * los efectos de las acciones Admin F4C.
         */
        $guardTables = [
            'transformation_implementation_definitions',
            'transformation_capability_activations',
            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',
            'transformation_implementation_subscription_activations',
            'transformation_implementation_subscription_item_activations',
        ];

        $guards = [];

        foreach ($guardTables as $table) {
            if (Schema::hasTable($table)) {
                $guards[$table] =
                    DB::table($table)->count();
            }
        }

        /*
         * ==========================================================
         * 1. GET DETAIL REAL
         * ==========================================================
         */

        $this->actingAs($admin)
            ->get(
                route(
                    'admin.transformation360.implementation_requests.show',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                )
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/ImplementationRequests/Show'
                        )
                        ->where(
                            'implementation_request.id',
                            (int) $implementationRequest->id
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_REQUESTED
                        )
                        ->where(
                            'company.id',
                            (int) $context['company']->id
                        )
                        ->where(
                            'assessment.id',
                            (int) $context['assessment']->id
                        )
                        ->where(
                            'plan.id',
                            (int) $context['plan']->id
                        )
                        ->where(
                            'capability.key',
                            'data_transformation_bi'
                        )
                        ->where(
                            'actions.can_mutate',
                            true
                        )
                        ->where(
                            'actions.allowed_transitions.0',
                            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW
                        )
            );

        /*
         * ==========================================================
         * 2. NON-ADMIN NO PUEDE MUTAR
         * ==========================================================
         */

        $this->actingAs($nonAdmin)
            ->patch(
                route(
                    'admin.transformation360.implementation_requests.assign',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                ),
                [
                    'assigned_to_user_id' =>
                        $assignee->id,
                ]
            )
            ->assertForbidden();

        $implementationRequest->refresh();

        $this->assertNull(
            $implementationRequest->assigned_to_user_id
        );

        /*
         * ==========================================================
         * 3. ASIGNAR RESPONSABLE LAUDA
         * ==========================================================
         */

        $eventsBeforeAssignment =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->actingAs($admin)
            ->patch(
                route(
                    'admin.transformation360.implementation_requests.assign',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                ),
                [
                    'assigned_to_user_id' =>
                        $assignee->id,
                ]
            )
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Responsable LAUDA asignado correctamente.'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            (int) $assignee->id,
            (int) $implementationRequest->assigned_to_user_id
        );

        $this->assertSame(
            $eventsBeforeAssignment + 1,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        $assignmentEvent =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->orderByDesc('id')
                ->first();

        $this->assertNotNull(
            $assignmentEvent
        );

        $this->assertSame(
            'lauda_admin',
            (string) $assignmentEvent->actor_type
        );

        /*
         * No se permite asignar un usuario no-admin.
         */
        $this->actingAs($admin)
            ->from(
                route(
                    'admin.transformation360.implementation_requests.show',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                )
            )
            ->patch(
                route(
                    'admin.transformation360.implementation_requests.assign',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                ),
                [
                    'assigned_to_user_id' =>
                        $nonAdmin->id,
                ]
            )
            ->assertSessionHasErrors(
                'assigned_to_user_id'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            (int) $assignee->id,
            (int) $implementationRequest->assigned_to_user_id
        );

        /*
         * ==========================================================
         * 4. REQUESTED → UNDER_LAUDA_REVIEW
         * ==========================================================
         */

        $eventsBeforeReview =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->actingAs($admin)
            ->post(
                route(
                    'admin.transformation360.implementation_requests.transition',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                ),
                [
                    'target_status' =>
                        TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,

                    'notes' =>
                        'F4C2 · revisión administrativa iniciada.',
                ]
            )
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'La solicitud fue recibida y está en revisión por LAUDA.'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->review_started_at
        );

        $this->assertSame(
            (int) $admin->id,
            (int) $implementationRequest->status_changed_by_user_id
        );

        $this->assertSame(
            $eventsBeforeReview + 1,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        /*
         * Desde under_lauda_review no puede saltar directamente
         * a awaiting_tenant_review.
         */
        $this->actingAs($admin)
            ->from(
                route(
                    'admin.transformation360.implementation_requests.show',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                )
            )
            ->post(
                route(
                    'admin.transformation360.implementation_requests.transition',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                ),
                [
                    'target_status' =>
                        TransformationImplementationRequestContract::STATUS_AWAITING_TENANT_REVIEW,
                ]
            )
            ->assertSessionHasErrors(
                'target_status'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 5. UNDER_LAUDA_REVIEW → DEFINITION_PREPARATION
         * ==========================================================
         */

        $eventsBeforeDefinitionPreparation =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count();

        $this->actingAs($admin)
            ->post(
                route(
                    'admin.transformation360.implementation_requests.transition',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                ),
                [
                    'target_status' =>
                        TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,

                    'notes' =>
                        'F4C2 · iniciar preparación funcional de definición.',
                ]
            )
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'La solicitud pasó a preparación de definición.'
            );

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->definition_started_at
        );

        $this->assertSame(
            $eventsBeforeDefinitionPreparation + 1,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        /*
         * F4 termina aquí.
         *
         * definition_preparation NO crea Definition.
         */
        $this->actingAs($admin)
            ->get(
                route(
                    'admin.transformation360.implementation_requests.show',
                    [
                        'implementationRequest' =>
                            $implementationRequest->id,
                    ]
                )
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'actions.allowed_transitions',
                            []
                        )
            );

        /*
         * ==========================================================
         * 6. BOUNDARY ABSOLUTO
         * ==========================================================
         */

        foreach ($guards as $table => $before) {
            $this->assertSame(
                $before,
                DB::table($table)->count(),
                'Side effect no permitido en '.$table
            );
        }
    }

    private function createRequestedBiFixture(): array
    {
        $suffix =
            Str::lower(
                Str::random(14)
            );

        /*
         * ==========================================================
         * TENANT USER
         * ==========================================================
         */

        $user =
            User::factory()->create([
                'name' =>
                    'F4C Tenant Admin',

                'email' =>
                    'f4c-tenant-'
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
                        'F4C Subscriber '.$suffix,

                    'slug' =>
                        'f4c-subscriber-'.$suffix,

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
                    $user->id,

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
                        $user->id,

                    'name' =>
                        'F4C Company '.$suffix,

                    'slug' =>
                        'f4c-company-'.$suffix,

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
         * ==========================================================
         * DIAGNOSIS
         * ==========================================================
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

        $assessment =
            DiagnosisAssessment::query()
                ->findOrFail(
                    $assessmentId
                );

        /*
         * ==========================================================
         * HISTORICAL COMPANY LINK
         * ==========================================================
         */

        $contactId =
            $this->insertFixtureRow(
                'contact_requests',
                [
                    'name' =>
                        'F4C Contact '.$suffix,

                    'email' =>
                        'f4c-contact-'
                        .$suffix
                        .'@example.test',

                    'phone' =>
                        '0000000000',

                    'company' =>
                        $company->name,

                    'topic' =>
                        'Diagnóstico LAUDA 360',

                    'message' =>
                        'Fixture HTTP F4C2',

                    'terms' =>
                        1,

                    'metadata' =>
                        json_encode(
                            [
                                'source' =>
                                    'f4c2_http_fixture',
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
                                'f4c2_http_fixture',

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
                        $user->id,

                    'reviewed_by_user_id' =>
                        $user->id,

                    'methodology_version' =>
                        '1.0',

                    'source_snapshot' =>
                        json_encode(
                            [
                                'source' =>
                                    'f4c2_http_fixture',
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
                                            'Fixture funcional F4C2.',
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
         * PRESENTED PLAN
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

        $plan =
            TransformationImplementationPlan::query()
                ->findOrFail(
                    $planId
                );

        /*
         * ==========================================================
         * PHASE 3
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
         * ==========================================================
         * BI CAPABILITY
         * ==========================================================
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
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                ]
            );

        $phaseCapability =
            TransformationImplementationPhaseCapability::query()
                ->findOrFail(
                    $phaseCapabilityId
                );

        /*
         * ==========================================================
         * REQUEST REAL MEDIANTE DOMAIN SERVICE
         * ==========================================================
         */

        $implementationRequest =
            app(
                TransformationImplementationRequestService::class
            )->requestFromTenantAdmin(
                $company,
                $assessment,
                $plan,
                $phaseCapability,
                $user,
                'F4C2 · Solicitud tenant para QA.'
            );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_REQUESTED,
            $implementationRequest->status
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

            'request' =>
                $implementationRequest,
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
                    'Fixture F4C2 no definió FK obligatoria: '
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

        return 'QA F4C2';
    }
}
