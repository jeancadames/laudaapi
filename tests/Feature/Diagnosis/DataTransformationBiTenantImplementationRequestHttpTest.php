<?php

namespace Tests\Feature\Diagnosis;

use App\Models\Company;
use App\Models\DiagnosisAssessment;
use App\Models\Subscriber;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DataTransformationBiTenantImplementationRequestHttpTest
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

    public function test_tenant_admin_can_request_bi_idempotently(): void
    {
        $context = $this->createTenantBiFixture();

        /** @var User $user */
        $user = $context['user'];

        /** @var Company $company */
        $company = $context['company'];

        /** @var DiagnosisAssessment $assessment */
        $assessment = $context['assessment'];

        /** @var TransformationImplementationPlan $plan */
        $plan = $context['plan'];

        /** @var TransformationImplementationPhaseCapability $phaseCapability */
        $phaseCapability =
            $context['phase_capability'];

        $requestBefore = DB::table(
            'transformation_implementation_requests'
        )->count();

        $eventBefore = DB::table(
            'transformation_implementation_request_events'
        )->count();

        /*
         * Ninguna de estas tablas puede ser modificada
         * por la solicitud F3.
         */
        $guardTables = [
            'transformation_capability_activations',
            'transformation_implementation_definitions',
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
         * 1. El owner/admin puede ver BI.
         */
        $this->actingAs($user)
            ->get(
                route(
                    'app.transformation.data_bi.show'
                )
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'App/DataTransformationBi'
                        )
                        ->where(
                            'company.id',
                            (int) $company->id
                        )
                        ->where(
                            'transformation360.assessment_id',
                            (int) $assessment->id
                        )
                        ->where(
                            'implementation_request.status',
                            null
                        )
                        ->where(
                            'implementation_request.can_request',
                            true
                        )
                        ->where(
                            'implementation_request.request_endpoint',
                            route(
                                'app.transformation.data_bi.request',
                                [],
                                false
                            )
                        )
            );

        /*
         * 2. Primer POST.
         */
        $this->actingAs($user)
            ->post(
                route(
                    'app.transformation.data_bi.request'
                )
            )
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'Solicitud de implementación enviada. LAUDA revisará el alcance antes de avanzar.'
            );

        $row = DB::table(
            'transformation_implementation_requests'
        )
            ->where(
                'company_id',
                $company->id
            )
            ->where(
                'diagnosis_assessment_id',
                $assessment->id
            )
            ->where(
                'transformation_implementation_plan_id',
                $plan->id
            )
            ->where(
                'transformation_implementation_phase_capability_id',
                $phaseCapability->id
            )
            ->where(
                'capability_key',
                'data_transformation_bi'
            )
            ->first();

        $this->assertNotNull($row);

        $this->assertSame(
            'requested',
            $row->status
        );

        $this->assertSame(
            1,
            (int) $row->attempt
        );

        $this->assertSame(
            (int) $user->id,
            (int) $row->requested_by_user_id
        );

        $firstRequestId =
            (int) $row->id;

        /*
         * El snapshot debe conservar el contexto real
         * resuelto por servidor.
         */
        $snapshot = json_decode(
            (string) $row->source_snapshot,
            true
        );

        $this->assertIsArray($snapshot);

        $this->assertSame(
            (int) $company->id,
            (int) data_get(
                $snapshot,
                'company.id'
            )
        );

        $this->assertSame(
            (int) $plan->id,
            (int) data_get(
                $snapshot,
                'plan.id'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $snapshot,
                'capability.capability_key'
            )
        );

        /*
         * 3. Segundo POST: idempotente.
         */
        $this->actingAs($user)
            ->post(
                route(
                    'app.transformation.data_bi.request'
                )
            )
            ->assertRedirect();

        $requests = DB::table(
            'transformation_implementation_requests'
        )
            ->where(
                'company_id',
                $company->id
            )
            ->where(
                'transformation_implementation_plan_id',
                $plan->id
            )
            ->where(
                'capability_key',
                'data_transformation_bi'
            )
            ->get();

        $this->assertCount(
            1,
            $requests
        );

        $this->assertSame(
            $firstRequestId,
            (int) $requests->first()->id
        );

        $this->assertSame(
            $requestBefore + 1,
            DB::table(
                'transformation_implementation_requests'
            )->count()
        );

        /*
         * Solo request_created.
         * El doble submit no genera otro evento.
         */
        $this->assertSame(
            $eventBefore + 1,
            DB::table(
                'transformation_implementation_request_events'
            )->count()
        );

        /*
         * 4. El GET cambia de CTA a estado.
         */
        $this->actingAs($user)
            ->get(
                route(
                    'app.transformation.data_bi.show'
                )
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
                            $firstRequestId
                        )
                        ->where(
                            'implementation_request.status',
                            'requested'
                        )
                        ->where(
                            'implementation_request.status_label',
                            'Solicitud enviada'
                        )
                        ->where(
                            'implementation_request.can_request',
                            false
                        )
                        ->where(
                            'implementation_request.request_endpoint',
                            null
                        )
            );

        /*
         * 5. Boundary absoluto:
         * no activación, no Definition, no execution,
         * no go-live, no suscripción.
         */
        foreach ($guards as $table => $before) {
            $this->assertSame(
                $before,
                DB::table($table)->count(),
                'Side effect no permitido en '.$table
            );
        }
    }

    public function test_lauda_admin_cannot_submit_tenant_request(): void
    {
        $admin = User::factory()->create([
            'name' =>
                'F3B LAUDA Admin QA',

            'email' =>
                'f3b-lauda-admin-'
                .Str::lower(Str::random(12))
                .'@example.test',

            'role' =>
                'admin',
        ]);

        $before = DB::table(
            'transformation_implementation_requests'
        )->count();

        $this->actingAs($admin)
            ->post(
                route(
                    'app.transformation.data_bi.request'
                )
            )
            ->assertForbidden();

        $this->assertSame(
            $before,
            DB::table(
                'transformation_implementation_requests'
            )->count()
        );
    }

    public function test_regular_tenant_user_cannot_submit_request(): void
    {
        $context = $this->createTenantBiFixture(
            tenantRole: 'user'
        );

        /** @var User $user */
        $user = $context['user'];

        $before = DB::table(
            'transformation_implementation_requests'
        )->count();

        $this->actingAs($user)
            ->post(
                route(
                    'app.transformation.data_bi.request'
                )
            )
            ->assertForbidden();

        $this->assertSame(
            $before,
            DB::table(
                'transformation_implementation_requests'
            )->count()
        );
    }

    /**
     * Fixture completamente autónomo.
     *
     * Todo se crea dentro de DatabaseTransactions,
     * por lo que no queda ningún registro al finalizar.
     *
     * @return array{
     *   user:User,
     *   subscriber:Subscriber,
     *   company:Company,
     *   assessment:DiagnosisAssessment,
     *   plan:TransformationImplementationPlan,
     *   phase_capability:TransformationImplementationPhaseCapability
     * }
     */
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
