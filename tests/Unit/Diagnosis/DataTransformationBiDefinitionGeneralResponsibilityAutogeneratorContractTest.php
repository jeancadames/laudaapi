<?php

namespace Tests\Unit\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Services\Diagnosis\TransformationImplementationDefinitionAutogenerator;
use App\Services\Diagnosis\TransformationImplementationDefinitionRequestScopeContract;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiDefinitionGeneralResponsibilityAutogeneratorContractTest
    extends TestCase
{
    private function definition(): TransformationImplementationDefinition
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
                            'Fuentes disponibles para evaluación.',
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
                    'Clientes normalizados.',
                    'Inventario preparado.',
                    'Ventas históricas.',
                    'Suplidores preparados.',
                ],

                'definition_scope_locked_to_request' =>
                    true,
            ],
        ]);

        return $definition;
    }

    public function test_data_bi_generates_exactly_two_general_assignments(): void
    {
        $generated =
            (
                new TransformationImplementationDefinitionAutogenerator()
            )->preview(
                $this->definition()
            );

        $assignments =
            data_get(
                $generated,
                'responsibility_model.assignments',
                []
            );

        self::assertCount(
            2,
            $assignments
        );

        self::assertSame(
            [
                'data_transformation_bi:client_source_delivery',
                'data_transformation_bi:lauda_transformation',
            ],
            array_column(
                $assignments,
                'initiative_id'
            )
        );

        self::assertSame(
            [
                'client',
                'lauda',
            ],
            array_column(
                $assignments,
                'responsible_party'
            )
        );

        self::assertSame(
            [
                'pending',
                'pending',
            ],
            array_column(
                $assignments,
                'confirmation_status'
            )
        );
    }

    public function test_assignments_do_not_scale_with_deliverables(): void
    {
        $generated =
            (
                new TransformationImplementationDefinitionAutogenerator()
            )->preview(
                $this->definition()
            );

        $assignments =
            data_get(
                $generated,
                'responsibility_model.assignments',
                []
            );

        self::assertCount(
            2,
            $assignments
        );

        foreach ($assignments as $assignment) {
            self::assertStringNotContainsString(
                ':deliverable:',
                (string) (
                    $assignment[
                        'initiative_id'
                    ] ?? ''
                )
            );
        }

        self::assertSame(
            [],
            data_get(
                $generated,
                'responsibility_model.unresolved'
            )
        );
    }

    public function test_human_confirmation_remains_required(): void
    {
        $generated =
            (
                new TransformationImplementationDefinitionAutogenerator()
            )->preview(
                $this->definition()
            );

        self::assertTrue(
            (bool) data_get(
                $generated,
                'responsibility_model.confirmation_required'
            )
        );

        self::assertSame(
            'to_be_defined',
            data_get(
                $generated,
                'responsibility_model.party_assignment_status'
            )
        );

        self::assertNull(
            data_get(
                $generated,
                'readiness.human_validation.responsibilities_confirmed'
            )
        );
    }
}
