<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationRequestExpectedSourcesPlan;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransformationImplementationRequestExpectedSourcesPlanTest extends TestCase
{
    private function snapshot(): array
    {
        return [
            'business_needs' => [
                'improvements' => [
                    ['key' => 'operaciones_inventarios'],
                    ['key' => 'gestion_clientes'],
                ],
            ],
        ];
    }

    private function deliverables(): array
    {
        return [
            [
                'source' => 'professional_capability',
                'phase_id' => 12,
                'capability_key' => 'data_transformation_bi',
                'deliverable' => 'Datos de inventarios.',
            ],
            [
                'source' => 'professional_capability',
                'phase_id' => 12,
                'capability_key' => 'data_transformation_bi',
                'deliverable' => 'Datos de clientes.',
            ],
        ];
    }

    private function scope(): array
    {
        $ref = TransformationImplementationRequestExpectedSourcesPlan::deliverableRef(
            $this->deliverables()[0]
        );

        return [
            'purpose' => 'Definición BI',
            'expected_sources_plan' => [
                'schema_version' => 1,
                'sources' => [
                    [
                        'key' => 'src_inventarios_1',
                        'name' => 'Fuente declarada por LAUDA',
                        'source_type' => 'to_define',
                        'data_domains' => ['operaciones'],
                        'notes' => 'Pendiente identificar origen.',
                    ],
                ],
                'links' => [
                    [
                        'need_key' => 'operaciones_inventarios',
                        'source_keys' => ['src_inventarios_1'],
                        'deliverable_refs' => [$ref],
                        'notes' => '',
                    ],
                ],
            ],
        ];
    }

    public function test_absent_plan_keeps_existing_scope(): void
    {
        $scope = ['purpose' => 'Definición existente'];

        $this->assertSame(
            $scope,
            TransformationImplementationRequestExpectedSourcesPlan::normalize(
                $this->snapshot(),
                $scope,
                $this->deliverables()
            )
        );
    }

    public function test_valid_many_to_many_plan_is_preserved(): void
    {
        $scope = $this->scope();

        $scope['expected_sources_plan']['links'][] = [
            'need_key' => 'gestion_clientes',
            'source_keys' => ['src_inventarios_1'],
            'deliverable_refs' => [],
            'notes' => 'Fuente compartida por confirmar.',
        ];

        $result = TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );

        $this->assertCount(1, $result['expected_sources_plan']['sources']);
        $this->assertCount(2, $result['expected_sources_plan']['links']);
        $this->assertSame('Definición BI', $result['purpose']);
    }

    public function test_empty_partial_plan_is_valid(): void
    {
        $scope = [
            'expected_sources_plan' => [
                'schema_version' => 1,
                'sources' => [],
                'links' => [],
            ],
        ];

        $result = TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );

        $this->assertSame([], $result['expected_sources_plan']['sources']);
    }

    public function test_unknown_need_is_rejected(): void
    {
        $scope = $this->scope();
        $scope['expected_sources_plan']['links'][0]['need_key'] = 'otra_empresa';

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );
    }

    public function test_unknown_source_is_rejected(): void
    {
        $scope = $this->scope();
        $scope['expected_sources_plan']['links'][0]['source_keys'] = ['src_desconocida'];

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );
    }

    public function test_unknown_deliverable_is_rejected(): void
    {
        $scope = $this->scope();
        $scope['expected_sources_plan']['links'][0]['deliverable_refs'] = [
            'del_inexistente',
        ];

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );
    }

    public function test_excluded_need_cannot_keep_links(): void
    {
        $scope = $this->scope();

        $scope['business_needs_assessment'] = [
            'items' => [
                [
                    'key' => 'operaciones_inventarios',
                    'status' => 'excluded',
                    'rationale' => 'Fuera del alcance.',
                ],
            ],
        ];

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );
    }

    public function test_duplicate_source_key_is_rejected(): void
    {
        $scope = $this->scope();
        $scope['expected_sources_plan']['sources'][] =
            $scope['expected_sources_plan']['sources'][0];

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestExpectedSourcesPlan::normalize(
            $this->snapshot(),
            $scope,
            $this->deliverables()
        );
    }

    public function test_deliverable_reference_does_not_depend_on_order(): void
    {
        $items = $this->deliverables();

        $ref = TransformationImplementationRequestExpectedSourcesPlan::deliverableRef(
            $items[0]
        );

        $reversed = array_reverse($items);

        $this->assertSame(
            $ref,
            TransformationImplementationRequestExpectedSourcesPlan::deliverableRef(
                $reversed[1]
            )
        );

        $items[0]['deliverable'] = 'Contenido modificado.';

        $this->assertNotSame(
            $ref,
            TransformationImplementationRequestExpectedSourcesPlan::deliverableRef(
                $items[0]
            )
        );
    }
}
