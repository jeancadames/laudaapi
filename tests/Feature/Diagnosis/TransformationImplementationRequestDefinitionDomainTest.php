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
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionService;
use App\Services\Diagnosis\TransformationImplementationRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TransformationImplementationRequestDefinitionDomainTest
    extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_request_scoped_bi_definition_domain_flow(): void
    {
        $context =
            $this->createBiFixture();

        /** @var User $tenant */
        $tenant =
            $context['tenant'];

        /** @var User $admin */
        $admin =
            User::factory()->create([
                'name' =>
                    'F5C2 Admin LAUDA',

                'email' =>
                    'f5c2-admin-'
                    .Str::lower(
                        Str::random(12)
                    )
                    .'@example.test',

                'role' =>
                    'admin',
            ]);

        /** @var TransformationImplementationRequest $implementationRequest */
        $implementationRequest =
            $context['request'];

        /** @var TransformationImplementationRequestDefinitionService $definitions */
        $definitions =
            app(
                TransformationImplementationRequestDefinitionService::class
            );

        /** @var TransformationImplementationRequestService $requests */
        $requests =
            app(
                TransformationImplementationRequestService::class
            );

        /*
         * ==========================================================
         * 1. TENANT NO PUEDE CREAR DEFINITION
         * ==========================================================
         */

        try {
            $definitions
                ->createOrGetDraftFromRequest(
                    $implementationRequest,
                    $tenant
                );

            $this->fail(
                'El tenant no debe poder crear la Definition.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'actor',
                $exception->errors()
            );
        }

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
         * 2. ADMIN TAMPOCO PUEDE CREARLA EN REQUESTED
         * ==========================================================
         */

        try {
            $definitions
                ->createOrGetDraftFromRequest(
                    $implementationRequest,
                    $admin
                );

            $this->fail(
                'No debe crearse Definition antes de definition_preparation.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'request',
                $exception->errors()
            );
        }

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_REQUESTED,
            $implementationRequest->status
        );

        /*
         * ==========================================================
         * 3. LAUDA REVIEW
         * ==========================================================
         */

        $implementationRequest =
            $requests->transitionByLauda(
                $implementationRequest,
                TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
                $admin,
                'F5C2 · revisión LAUDA.'
            );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_UNDER_LAUDA_REVIEW,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->review_started_at
        );

        $implementationRequest =
            $requests->transitionByLauda(
                $implementationRequest,
                TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
                $admin,
                'F5C2 · preparar Definition BI.'
            );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $implementationRequest->status
        );

        $this->assertNotNull(
            $implementationRequest->definition_started_at
        );

        /*
         * ==========================================================
         * 4. GUARDAS DE SIDE EFFECTS
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

        $definitionEventsBefore =
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

        /*
         * ==========================================================
         * 5. CREACIÓN EXPLÍCITA REQUEST → DEFINITION
         * ==========================================================
         */

        $definition =
            $definitions
                ->createOrGetDraftFromRequest(
                    $implementationRequest,
                    $admin
                );

        $this->assertNotNull(
            $definition->id
        );

        $this->assertSame(
            TransformationImplementationDefinition::STATUS_DRAFT,
            $definition->status
        );

        $this->assertSame(
            1,
            (int) $definition->version
        );

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) $definition
                ->transformation_implementation_request_id
        );

        $this->assertSame(
            (int) $context[
                'phase_capability'
            ]->id,
            (int) $definition
                ->transformation_implementation_phase_capability_id
        );

        $this->assertSame(
            'data_transformation_bi',
            $definition->capability_key
        );

        $this->assertSame(
            (int) $context['plan']->id,
            (int) $definition
                ->transformation_implementation_plan_id
        );

        $this->assertSame(
            (int) $context['assessment']->id,
            (int) $definition
                ->diagnosis_assessment_id
        );

        $this->assertSame(
            (int) $context['company']->id,
            (int) $definition->company_id
        );

        /*
         * ==========================================================
         * 6. SCOPE EXACTO
         * ==========================================================
         */

        $source =
            (array) $definition
                ->source_snapshot;

        $scope =
            (array) $definition
                ->implementation_scope;

        $readiness =
            (array) $definition
                ->readiness;

        $this->assertSame(
            'implementation_request',
            data_get(
                $source,
                'source_type'
            )
        );

        $this->assertSame(
            'single_capability',
            data_get(
                $source,
                'scope_mode'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $source,
                'capability.capability_key'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $source,
                'boundary.single_capability'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $source,
                'boundary.plan_wide_definition'
            )
        );

        $this->assertSame(
            'single_capability',
            data_get(
                $scope,
                'scope_mode'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $scope,
                'capability_key'
            )
        );

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) data_get(
                $scope,
                'request_id'
            )
        );

        $this->assertSame(
            (int) $context[
                'phase_capability'
            ]->id,
            (int) data_get(
                $scope,
                'phase_capability_id'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $scope,
                'definition_scope_locked_to_request'
            )
        );

        /*
         * No hay snapshot Plan-wide de capabilities.
         */
        $this->assertArrayNotHasKey(
            'capabilities',
            $source
        );

        /*
         * ==========================================================
         * 7. READINESS CONSERVADOR
         * ==========================================================
         */

        $this->assertFalse(
            (bool) data_get(
                $readiness,
                'definition_ready'
            )
        );

        $this->assertTrue(
            (bool) data_get(
                $readiness,
                'human_review_required'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $readiness,
                'human_review_completed'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $readiness,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $readiness,
                'execution_started'
            )
        );

        $this->assertFalse(
            (bool) data_get(
                $readiness,
                'commercial_stage_started'
            )
        );

        /*
         * ==========================================================
         * 8. REQUEST NO CAMBIA DE STATUS
         * ==========================================================
         */

        $implementationRequest->refresh();

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
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
         * 9. EVENTO definition_created
         * ==========================================================
         */

        $definitionEventsAfter =
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
            $definitionEventsBefore + 1,
            $definitionEventsAfter
        );

        $definitionEvent =
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
                ->latest('id')
                ->first();

        $this->assertNotNull(
            $definitionEvent
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $definitionEvent->from_status
        );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION,
            $definitionEvent->to_status
        );

        $this->assertSame(
            'lauda_admin',
            $definitionEvent->actor_type
        );

        /*
         * ==========================================================
         * 10. IDEMPOTENCIA
         * ==========================================================
         */

        $second =
            $definitions
                ->createOrGetDraftFromRequest(
                    $implementationRequest,
                    $admin
                );

        $this->assertSame(
            (int) $definition->id,
            (int) $second->id
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
            $definitionEventsAfter,
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
         * 11. RELACIONES
         * ==========================================================
         */

        $definition->refresh();

        $this->assertSame(
            (int) $implementationRequest->id,
            (int) $definition
                ->implementationRequest
                ->id
        );

        $this->assertSame(
            (int) $context[
                'phase_capability'
            ]->id,
            (int) $definition
                ->phaseCapability
                ->id
        );

        $implementationRequest->refresh();

        $this->assertSame(
            1,
            $implementationRequest
                ->definitions()
                ->count()
        );

        /*
         * ==========================================================
         * 12. SIN SIDE EFFECTS
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
    }

    private function createBiFixture(): array
    {
        $suffix =
            Str::lower(
                Str::random(14)
            );

        /*
         * ==========================================================
         * TENANT ADMIN
         * ==========================================================
         */

        $tenant =
            User::factory()->create([
                'name' =>
                    'F5C2 Tenant Admin',

                'email' =>
                    'f5c2-tenant-'
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
                        'F5C2 Subscriber '
                        .$suffix,

                    'slug' =>
                        'f5c2-subscriber-'
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
                        'F5C2 Company '
                        .$suffix,

                    'slug' =>
                        'f5c2-company-'
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
         * DIAGNOSIS
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
         * ACCESS / COMPANY LINK
         * ==========================================================
         */

        $contactId =
            $this->insertFixtureRow(
                'contact_requests',
                [
                    'name' =>
                        'F5C2 Contact '
                        .$suffix,

                    'email' =>
                        'f5c2-contact-'
                        .$suffix
                        .'@example.test',

                    'phone' =>
                        '0000000000',

                    'company' =>
                        $company->name,

                    'topic' =>
                        'Diagnóstico LAUDA 360',

                    'message' =>
                        'Fixture F5C2',

                    'terms' =>
                        1,

                    'metadata' =>
                        json_encode(
                            [
                                'source' =>
                                    'f5c2_fixture',
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
                                'f5c2_fixture',

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
                        $tenant->id,

                    'reviewed_by_user_id' =>
                        $tenant->id,

                    'methodology_version' =>
                        '1.0',

                    'source_snapshot' =>
                        json_encode(
                            [
                                'source' =>
                                    'f5c2_fixture',
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

                                        'purpose' =>
                                            'Fixture BI F5C2.',
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
                        $tenant->id,

                    'updated_by_user_id' =>
                        $tenant->id,

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
                        $tenant->id,

                    'updated_by_user_id' =>
                        $tenant->id,
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
         * REQUEST REAL
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
                $tenant,
                'F5C2 · Solicitud BI.'
            );

        $this->assertSame(
            TransformationImplementationRequestContract::STATUS_REQUESTED,
            $implementationRequest->status
        );

        return [
            'tenant' =>
                $tenant,

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
                    'Fixture F5C2 no definió FK obligatoria: '
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

        return 'QA F5C2';
    }
}
