<?php

namespace Tests\Unit\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Services\Diagnosis\TransformationImplementationDefinitionAutogenerator;
use App\Services\Diagnosis\TransformationImplementationDefinitionRequestScopeContract;
use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionRequestScopedAutogeneratorContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationDefinitionAutogenerator.php'
        );
    }

    public function test_preview_dispatches_request_scope_before_legacy_phases(): void
    {
        $source =
            $this->source();

        $dispatch =
            strpos(
                $source,
                '$this->isRequestScopedDefinition('
            );

        $legacy =
            strpos(
                $source,
                "\$source['phases']"
            );

        $this->assertNotFalse(
            $dispatch
        );

        $this->assertNotFalse(
            $legacy
        );

        $this->assertTrue(
            $dispatch < $legacy
        );
    }

    public function test_request_scoped_generator_has_explicit_single_capability_contract(): void
    {
        $source =
            $this->source();

        foreach ([
            'previewRequestScoped(',
            'isRequestScopedDefinition(',
            'TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE',
            "'implementation_request'",
            "'definition_scope_locked_to_request'",
            "'professional_service'",
            "'implementation_only'",
            "'phase_capability_id'",
            "'capability_key'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_request_scoped_path_does_not_read_plan_wide_phases(): void
    {
        $source =
            $this->source();

        $start =
            strpos(
                $source,
                'private function previewRequestScoped('
            );

        $end =
            strpos(
                $source,
                'private function requestScopedStringList(',
                $start
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $scoped =
            substr(
                $source,
                $start,
                $end - $start
            );

        $this->assertStringNotContainsString(
            "\$source['phases']",
            $scoped
        );

        $this->assertStringNotContainsString(
            'phases.capabilities',
            $scoped
        );

        $this->assertStringNotContainsString(
            'TransformationImplementationPlan::',
            $scoped
        );
    }

    public function test_runtime_preview_ignores_other_plan_capabilities(): void
    {
        $definition =
            new TransformationImplementationDefinition();

        $definition->forceFill([
            'transformation_implementation_plan_id' =>
                100,

            'diagnosis_assessment_id' =>
                101,

            'company_id' =>
                102,

            'transformation_implementation_request_id' =>
                200,

            'transformation_implementation_phase_capability_id' =>
                300,

            'capability_key' =>
                'data_transformation_bi',

            'version' =>
                1,

            'status' =>
                TransformationImplementationDefinition::STATUS_DRAFT,

            'source_snapshot' => [
                'source_type' =>
                    'implementation_request',

                'scope_mode' =>
                    TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                'phase' => [
                    'id' =>
                        30,

                    'sequence' =>
                        3,

                    'name' =>
                        'Conectar y medir',

                    'objective' =>
                        'Preparar la capa fundacional de datos.',
                ],

                'capability' => [
                    'id' =>
                        300,

                    'capability_key' =>
                        'data_transformation_bi',

                    'capability_label' =>
                        'Transformación e Inteligencia de Datos para BI',

                    'capability_summary' =>
                        'Capa fundacional de datos.',

                    'source_snapshot' => [
                        'dependencies' => [
                            'Acceso a fuentes de datos BI.',
                        ],
                    ],
                ],

                /*
                 * Datos deliberadamente ajenos.
                 *
                 * El nuevo preview debe ignorarlos por completo.
                 */
                'phases' => [
                    [
                        'id' =>
                            99,

                        'name' =>
                            'NO IMPORTAR ESTA FASE',

                        'source_snapshot' => [
                            'deliverables' => [
                                'NO IMPORTAR DELIVERABLE PLAN WIDE',
                            ],

                            'dependencies' => [
                                'NO IMPORTAR DEPENDENCY PLAN WIDE',
                            ],
                        ],

                        'capabilities' => [
                            [
                                'capability_key' =>
                                    'branding_identity',
                            ],

                            [
                                'capability_key' =>
                                    'procedures_guide',
                            ],
                        ],
                    ],
                ],
            ],

            'implementation_scope' => [
                'scope_mode' =>
                    TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,

                'request_id' =>
                    200,

                'phase_id' =>
                    30,

                'phase_sequence' =>
                    3,

                'phase_name' =>
                    'Conectar y medir',

                'phase_capability_id' =>
                    300,

                'capability_key' =>
                    'data_transformation_bi',

                'capability_label' =>
                    'Transformación e Inteligencia de Datos para BI',

                'purpose' =>
                    'Preparar una capa fundacional de datos.',

                'includes' => [
                    'Normalización de clientes.',
                    'Inteligencia de inventario.',
                ],

                'definition_scope_locked_to_request' =>
                    true,
            ],
        ]);

        $generated =
            (
                new TransformationImplementationDefinitionAutogenerator()
            )->preview(
                $definition
            );

        $this->assertSame(
            'implementation_request',
            data_get(
                $generated,
                'implementation_scope.source'
            )
        );

        $this->assertSame(
            TransformationImplementationDefinitionRequestScopeContract::SCOPE_MODE,
            data_get(
                $generated,
                'implementation_scope.scope_mode'
            )
        );

        $this->assertSame(
            200,
            data_get(
                $generated,
                'implementation_scope.request_id'
            )
        );

        $this->assertSame(
            300,
            data_get(
                $generated,
                'implementation_scope.phase_capability_id'
            )
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $generated,
                'implementation_scope.capability_key'
            )
        );

        $phases =
            data_get(
                $generated,
                'implementation_scope.phases',
                []
            );

        $this->assertCount(
            1,
            $phases
        );

        $capabilities =
            data_get(
                $generated,
                'implementation_scope.phases.0.capabilities',
                []
            );

        $this->assertCount(
            1,
            $capabilities
        );

        $this->assertSame(
            'data_transformation_bi',
            data_get(
                $generated,
                'implementation_scope.phases.0.capabilities.0.capability_key'
            )
        );

        foreach (
            $generated[
                'deliverables'
            ]
            as $deliverable
        ) {
            $this->assertSame(
                'data_transformation_bi',
                $deliverable[
                    'capability_key'
                ]
            );
        }

        foreach (
            $generated[
                'dependencies'
            ]
            as $dependency
        ) {
            $this->assertSame(
                'data_transformation_bi',
                $dependency[
                    'capability_key'
                ]
            );
        }

        $json =
            json_encode(
                $generated,
                JSON_THROW_ON_ERROR
            );

        foreach ([
            'branding_identity',
            'procedures_guide',
            'NO IMPORTAR ESTA FASE',
            'NO IMPORTAR DELIVERABLE PLAN WIDE',
            'NO IMPORTAR DEPENDENCY PLAN WIDE',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $json
            );
        }

        $this->assertFalse(
            data_get(
                $generated,
                'readiness.definition_ready'
            )
        );

        $this->assertTrue(
            data_get(
                $generated,
                'readiness.human_review_required'
            )
        );

        $this->assertFalse(
            data_get(
                $generated,
                'readiness.ready_for_execution'
            )
        );

        $this->assertFalse(
            data_get(
                $generated,
                'readiness.execution_started'
            )
        );

        $this->assertFalse(
            data_get(
                $generated,
                'readiness.commercial_stage_started'
            )
        );
    }

    public function test_scoped_generator_keeps_human_responsibility_confirmation(): void
    {
        $source =
            $this->source();

        foreach ([
            "'responsible_party' =>",
            "'confirmation_status' =>",
            "'pending'",
            "'confirmation_required' =>",
            "'party_assignment_status' =>",
            "'to_be_defined'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            "'responsible_party' => 'lauda'",
            "'responsible_party' => 'client'",
            "'responsible_party' => 'shared'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_scoped_generator_is_noncommercial_and_nonexecuting(): void
    {
        $source =
            $this->source();

        foreach ([
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
            'SubscriptionItem::create',
            'TransformationImplementationExecutionService',
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
