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

final class TransformationImplementationRequestDefinitionAdminHttpTest
    extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_explicitly_create_request_scoped_definition(): void
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

        /*
         * ==========================================================
         * 1. REQUEST YA EN DEFINITION_PREPARATION
         * ==========================================================
         */

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->definition_started_at
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
         * 2. GET ADMIN · ANTES DE CREAR
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
                            'implementation_request.id',
                            $implementationRequest->id
                        )
                        ->where(
                            'implementation_request.status',
                            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION
                        )
                        ->where(
                            'definition',
                            null
                        )
                        ->where(
                            'actions.can_create_definition',
                            true
                        )
                        ->where(
                            'actions.definition_create_endpoint',
                            $createUrl
                        )
            );

        /*
         * ==========================================================
         * 3. NON ADMIN NO PUEDE CREAR
         * ==========================================================
         */

        $this
            ->actingAs(
                $regular
            )
            ->from(
                $detailUrl
            )
            ->post(
                $createUrl
            )
            ->assertForbidden();

        $this->assertSame(
            0,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        /*
         * ==========================================================
         * 4. ADMIN CREA DEFINITION
         * ==========================================================
         */

        $eventsBefore =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_created'
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
                $createUrl
            )
            ->assertRedirect(
                $detailUrl
            )
            ->assertSessionHas(
                'success'
            );

        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->sole();

        $this->assertSame(
            1,
            (int) $definition->version
        );

        $this->assertSame(
            'data_transformation_bi',
            $definition->capability_key
        );

        $this->assertSame(
            (int) $implementationRequest
                ->transformation_implementation_phase_capability_id,
            (int) $definition
                ->transformation_implementation_phase_capability_id
        );

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) $definition
                ->transformation_implementation_request_id
        );

        $this->assertSame(
            'single_capability',
            data_get(
                $definition->source_snapshot,
                'scope_mode'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $definition->source_snapshot,
                'boundary.plan_wide_definition'
            )
        );

        /*
         * Request NO debe avanzar.
         */
        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 5. EVENTO
         * ==========================================================
         */

        $eventsAfter =
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_created'
                )
                ->count();

        $this->assertSame(
            $eventsBefore + 1,
            $eventsAfter
        );

        /*
         * ==========================================================
         * 6. GET ADMIN · DESPUÉS DE CREAR
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
                            'implementation_request.id',
                            $implementationRequest->id
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
                            'definition.version',
                            1
                        )
                        ->where(
                            'definition.status',
                            TransformationImplementationDefinition::STATUS_DRAFT
                        )
                        ->where(
                            'definition.capability_key',
                            'data_transformation_bi'
                        )
                        ->where(
                            'actions.can_create_definition',
                            false
                        )
                        ->where(
                            'actions.definition_create_endpoint',
                            $createUrl
                        )
            );

        /*
         * ==========================================================
         * 7. SEGUNDO POST · IDEMPOTENCIA
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

        $this->assertSame(
            1,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->count()
        );

        $this->assertSame(
            (int) $definition->id,
            (int) TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->sole()
                ->id
        );

        $this->assertSame(
            $eventsAfter,
            DB::table(
                'transformation_implementation_request_events'
            )
                ->where(
                    'transformation_implementation_request_id',
                    $implementationRequest->id
                )
                ->where(
                    'event_type',
                    'definition_created'
                )
                ->count()
        );

        /*
         * ==========================================================
         * 8. SIN SIDE EFFECTS DOWNSTREAM
         * ==========================================================
         */

        foreach (
            $context['guard_counts']
            as $table => $count
        ) {
            $this->assertSame(
                $count,
                DB::table(
                    $table
                )->count(),
                'Side effect no permitido: '.$table
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
