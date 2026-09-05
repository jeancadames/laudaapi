<?php

namespace Tests\Feature\Diagnosis;

use App\Models\ContactRequest;
use App\Models\DiagnosisAccessRequest;
use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class TransformationImplementationDefinitionAdminFlowTest
    extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private ContactRequest $contact;

    private int $companyId;

    private int $assessmentId;

    private TransformationImplementationPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * El QA HTTP valida rutas, controller e Inertia props.
         * No depende del bundle Vite ni requiere npm run build.
         */
        $this->withoutVite();

        $this->assertSame(
            'mysql',
            config('database.default')
        );

        $this->assertSame(
            'mysql',
            DB::connection()
                ->getPdo()
                ->getAttribute(
                    \PDO::ATTR_DRIVER_NAME
                )
        );

        $this->admin =
            User::factory()->create([
                'role' =>
                    'admin',

                'email_verified_at' =>
                    now(),
            ]);

        $this->companyId =
            DB::table('companies')
                ->insertGetId([
                    'name' =>
                        'S14 C3C Company',

                    'slug' =>
                        's14-c3c-'
                        .Str::lower(
                            Str::random(10)
                        ),

                    'currency' =>
                        'DOP',

                    'timezone' =>
                        'America/Santo_Domingo',

                    'active' =>
                        true,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

        $this->contact =
            ContactRequest::create([
                'name' =>
                    'S14 C3C Contact',

                'email' =>
                    's14-c3c-'
                    .Str::lower(
                        Str::random(10)
                    )
                    .'@example.test',

                'company' =>
                    'S14 C3C Company',

                'topic' =>
                    'diagnosis',

                'message' =>
                    'Fixture HTTP S14-C3C.',

                'terms' =>
                    true,
            ]);

        $this->assessmentId =
            DB::table(
                'diagnosis_assessments'
            )->insertGetId([
                'user_id' =>
                    $this->admin->id,

                'organization_id' =>
                    $this->companyId,

                'organization_name' =>
                    'S14 C3C Company',

                'methodology_version' =>
                    '1.0',

                'status' =>
                    'reviewed',

                'is_active' =>
                    true,

                'current_step' =>
                    1,

                'review_required' =>
                    false,

                'published_at' =>
                    now(),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        DiagnosisAccessRequest::create([
            'public_id' =>
                (string) Str::ulid(),

            'contact_request_id' =>
                $this->contact->id,

            'user_id' =>
                $this->admin->id,

            'diagnosis_assessment_id' =>
                $this->assessmentId,

            'reviewed_by_user_id' =>
                $this->admin->id,

            'status' =>
                'active',

            'approved_at' =>
                now(),
        ]);

        $planId =
            DB::table(
                'transformation_implementation_plans'
            )->insertGetId([
                'diagnosis_assessment_id' =>
                    $this->assessmentId,

                'diagnosis_detailed_roadmap_id' =>
                    null,

                'version' =>
                    1,

                'status' =>
                    TransformationImplementationPlan::STATUS_PRESENTED,

                'recommended_modality' =>
                    null,

                'recommended_modality_label' =>
                    null,

                'selected_modality' =>
                    null,

                'selected_modality_label' =>
                    null,

                'source_snapshot' =>
                    json_encode([
                        'source_type' =>
                            's14_c3cb4_http_fixture',
                    ]),

                'created_by_user_id' =>
                    $this->admin->id,

                'updated_by_user_id' =>
                    $this->admin->id,

                'presented_at' =>
                    now(),

                'accepted_at' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        /*
         * IMPORTANTE:
         * horizon NO es columna física.
         * Vive dentro de source_snapshot.
         */
        $phaseId =
            DB::table(
                'transformation_implementation_phases'
            )->insertGetId([
                'transformation_implementation_plan_id' =>
                    $planId,

                'sequence' =>
                    3,

                'name' =>
                    'Fase 3 · Conectar y medir',

                'objective' =>
                    'Preparar una base analítica confiable.',

                'source_snapshot' =>
                    json_encode([
                        'horizon' =>
                            '91-180 días',

                        'initiative_ids' => [
                            'DAT-01',
                        ],

                        'initiatives' => [
                            [
                                'id' =>
                                    'DAT-01',

                                'title' =>
                                    'Preparar la capa fundacional de datos',

                                'objective' =>
                                    'Organizar y relacionar las fuentes requeridas.',

                                'priority' =>
                                    'critical',

                                'effort' =>
                                    'high',

                                'actions' => [
                                    'Identificar fuentes.',
                                    'Perfilar calidad.',
                                    'Definir relaciones.',
                                ],

                                'dependencies' => [
                                    'OPS-01',
                                    'TEC-01',
                                ],

                                'owner_role' =>
                                    'Líder de Datos / Operaciones',

                                'success_metrics' => [
                                    'Fuentes identificadas.',
                                    'Modelo analítico definido.',
                                ],
                            ],
                        ],

                        'dependencies' => [
                            'OPS-01',
                            'TEC-01',
                        ],

                        'deliverables' => [
                            'Mapa de fuentes y calidad de datos.',
                            'Modelo analítico base.',
                        ],
                    ]),

                'created_by_user_id' =>
                    $this->admin->id,

                'updated_by_user_id' =>
                    $this->admin->id,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        DB::table(
            'transformation_implementation_phase_capabilities'
        )->insert([
            'transformation_implementation_phase_id' =>
                $phaseId,

            'sequence' =>
                1,

            'capability_key' =>
                'data_transformation_bi',

            'capability_label' =>
                'Transformación e Inteligencia de Datos para BI',

            'source_snapshot' =>
                json_encode([
                    'kind' =>
                        'professional_service',

                    'activation_policy' =>
                        'implementation_only',

                    'subscription_candidate' =>
                        false,
                ]),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $this->plan =
            TransformationImplementationPlan::query()
                ->findOrFail(
                    $planId
                );
    }

    public function test_complete_admin_definition_http_flow(): void
    {
        $downstreamBefore =
            $this->downstreamCounts();

        /*
         * GET inicial
         */
        $this
            ->actingAs(
                $this->admin
            )
            ->get(
                $this->showUrl()
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/DiagnosisRequests/ImplementationDefinition'
                        )
                        ->where(
                            'contact.id',
                            $this->contact->id
                        )
                        ->where(
                            'assessment.id',
                            $this->assessmentId
                        )
                        ->where(
                            'plan.id',
                            $this->plan->id
                        )
                        ->where(
                            'plan.status',
                            'presented'
                        )
                        ->where(
                            'definition',
                            null
                        )
            );

        /*
         * Crear Definition
         */
        $this
            ->actingAs(
                $this->admin
            )
            ->post(
                route(
                    'admin.diagnosis_requests.implementation_definition.create',
                    [
                        'contact' =>
                            $this->contact,

                        'plan' =>
                            $this->plan,
                    ]
                )
            )
            ->assertRedirect(
                $this->showUrl()
            );

        $definition =
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_plan_id',
                    $this->plan->id
                )
                ->sole();

        $this->assertSame(
            'draft',
            $definition->status
        );

        $this->assertSame(
            'DAT-01',
            data_get(
                $definition
                    ->implementation_scope,
                'phases.0.initiatives.0.id'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $definition
                    ->implementation_scope,
                'phases.0.capabilities.0.capability_key'
            )
        );

        $this->assertSame(
            '91-180 días',
            data_get(
                $definition
                    ->implementation_scope,
                'phases.0.horizon'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $definition
                    ->implementation_scope,
                'phases.0.capabilities.0.scope_items',
                []
            )
        );

        $this->assertFalse(
            data_get(
                $definition->readiness,
                'ready_for_execution'
            )
        );

        /*
         * GET después de crear
         */
        $this
            ->actingAs(
                $this->admin
            )
            ->get(
                $this->showUrl()
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/DiagnosisRequests/ImplementationDefinition'
                        )
                        ->where(
                            'definition.id',
                            $definition->id
                        )
                        ->where(
                            'definition.status',
                            'draft'
                        )
                        ->where(
                            'definition.editable',
                            true
                        )
                        ->where(
                            'definition.is_ready',
                            false
                        )
            );

        /*
         * Regenerar
         */
        $this
            ->actingAs(
                $this->admin
            )
            ->post(
                route(
                    'admin.diagnosis_requests.implementation_definition.regenerate',
                    [
                        'contact' =>
                            $this->contact,

                        'plan' =>
                            $this->plan,

                        'definition' =>
                            $definition,
                    ]
                )
            )
            ->assertRedirect();

        $this->assertSame(
            1,
            TransformationImplementationDefinition::query()
                ->where(
                    'transformation_implementation_plan_id',
                    $this->plan->id
                )
                ->count()
        );

        $definition->refresh();

        /*
         * Revisión humana
         */
        $assignment =
            data_get(
                $definition
                    ->responsibility_model,
                'assignments.0'
            );

        $this->assertIsArray(
            $assignment
        );

        $assignment[
            'responsible_party'
        ] = 'shared';

        $this
            ->actingAs(
                $this->admin
            )
            ->patch(
                route(
                    'admin.diagnosis_requests.implementation_definition.review',
                    [
                        'contact' =>
                            $this->contact,

                        'plan' =>
                            $this->plan,

                        'definition' =>
                            $definition,
                    ]
                ),
                [
                    'responsibility_model' => [
                        'assignments' => [
                            $assignment,
                        ],
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
                ]
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $definition->refresh();

        $this->assertSame(
            'under_review',
            $definition->status
        );

        $this->assertSame(
            'shared',
            data_get(
                $definition
                    ->responsibility_model,
                'assignments.0.responsible_party'
            )
        );

        $this->assertSame(
            'confirmed',
            data_get(
                $definition
                    ->responsibility_model,
                'party_assignment_status'
            )
        );

        $this->assertTrue(
            data_get(
                $definition->readiness,
                'human_validation.scope_confirmed'
            )
        );

        $this->assertFalse(
            data_get(
                $definition->readiness,
                'ready_for_execution'
            )
        );

        /*
         * Marcar Definition lista
         */
        $this
            ->actingAs(
                $this->admin
            )
            ->post(
                route(
                    'admin.diagnosis_requests.implementation_definition.ready',
                    [
                        'contact' =>
                            $this->contact,

                        'plan' =>
                            $this->plan,

                        'definition' =>
                            $definition,
                    ]
                )
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $definition->refresh();

        $this->assertSame(
            'ready',
            $definition->status
        );

        $this->assertNotNull(
            $definition->ready_at
        );

        $this->assertTrue(
            data_get(
                $definition->readiness,
                'technical_readiness'
            )
        );

        $this->assertTrue(
            data_get(
                $definition->readiness,
                'definition_ready'
            )
        );

        $this->assertFalse(
            data_get(
                $definition->readiness,
                'ready_for_execution'
            )
        );

        $this->assertFalse(
            data_get(
                $definition->readiness,
                'execution_started'
            )
        );

        /*
         * GET final readonly
         */
        $this
            ->actingAs(
                $this->admin
            )
            ->get(
                $this->showUrl()
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/DiagnosisRequests/ImplementationDefinition'
                        )
                        ->where(
                            'definition.status',
                            'ready'
                        )
                        ->where(
                            'definition.editable',
                            false
                        )
                        ->where(
                            'definition.is_ready',
                            true
                        )
                        ->where(
                            'definition.readiness.ready_for_execution',
                            false
                        )
            );

        /*
         * Plan consultivo sin mutaciones comerciales.
         */
        $this->plan->refresh();

        $this->assertSame(
            'presented',
            $this->plan->status
        );

        $this->assertNull(
            $this->plan
                ->selected_modality
        );

        $this->assertNull(
            $this->plan
                ->accepted_at
        );

        /*
         * Sin efectos downstream.
         */
        $this->assertSame(
            $downstreamBefore,
            $this->downstreamCounts()
        );
    }

    public function test_cross_contact_access_to_plan_is_not_found(): void
    {
        $otherContact =
            ContactRequest::create([
                'name' =>
                    'Otro contacto',

                'email' =>
                    'other-contact-'
                    .Str::lower(
                        Str::random(10)
                    )
                    .'@example.test',

                'company' =>
                    'Otra Empresa',

                'topic' =>
                    'diagnosis',

                'message' =>
                    'Cross-context QA.',

                'terms' =>
                    true,
            ]);

        $this
            ->actingAs(
                $this->admin
            )
            ->get(
                route(
                    'admin.diagnosis_requests.implementation_definition.show',
                    [
                        'contact' =>
                            $otherContact,

                        'plan' =>
                            $this->plan,
                    ]
                )
            )
            ->assertNotFound();
    }

    public function test_plan_from_another_assessment_is_not_found_for_contact(): void
    {
        $otherAssessmentId =
            DB::table(
                'diagnosis_assessments'
            )->insertGetId([
                'user_id' =>
                    $this->admin->id,

                'organization_id' =>
                    $this->companyId,

                'organization_name' =>
                    'Another Assessment',

                'methodology_version' =>
                    '1.0',

                'status' =>
                    'reviewed',

                'is_active' =>
                    false,

                'current_step' =>
                    1,

                'review_required' =>
                    false,

                'published_at' =>
                    now(),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        $otherPlanId =
            DB::table(
                'transformation_implementation_plans'
            )->insertGetId([
                'diagnosis_assessment_id' =>
                    $otherAssessmentId,

                'diagnosis_detailed_roadmap_id' =>
                    null,

                'version' =>
                    1,

                'status' =>
                    'presented',

                'recommended_modality' =>
                    null,

                'recommended_modality_label' =>
                    null,

                'selected_modality' =>
                    null,

                'selected_modality_label' =>
                    null,

                'source_snapshot' =>
                    json_encode([
                        'source_type' =>
                            'cross_context',
                    ]),

                'created_by_user_id' =>
                    $this->admin->id,

                'updated_by_user_id' =>
                    $this->admin->id,

                'presented_at' =>
                    now(),

                'accepted_at' =>
                    null,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);

        $this
            ->actingAs(
                $this->admin
            )
            ->get(
                route(
                    'admin.diagnosis_requests.implementation_definition.show',
                    [
                        'contact' =>
                            $this->contact,

                        'plan' =>
                            $otherPlanId,
                    ]
                )
            )
            ->assertNotFound();
    }

    public function test_non_admin_cannot_access_definition_admin_route(): void
    {
        $user =
            User::factory()->create([
                'role' =>
                    'user',

                'email_verified_at' =>
                    now(),
            ]);

        $this
            ->actingAs(
                $user
            )
            ->get(
                $this->showUrl()
            )
            ->assertForbidden();
    }

    private function showUrl(): string
    {
        return route(
            'admin.diagnosis_requests.implementation_definition.show',
            [
                'contact' =>
                    $this->contact,

                'plan' =>
                    $this->plan,
            ]
        );
    }

    private function downstreamCounts(): array
    {
        $tables = [
            'transformation_implementation_phase_estimates',
            'transformation_implementation_milestones',

            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',

            'subscriptions',
            'subscription_items',
            'invoices',
            'payments',
        ];

        $counts = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] =
                DB::table($table)->count();
        }

        return $counts;
    }
}
