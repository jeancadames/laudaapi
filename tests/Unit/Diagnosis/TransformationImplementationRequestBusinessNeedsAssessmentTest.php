<?php

namespace Tests\Unit\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Models\TransformationImplementationRequest;
use App\Services\Diagnosis\TransformationImplementationRequestBusinessNeedsAssessment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TransformationImplementationRequestBusinessNeedsAssessmentTest extends TestCase
{

    // BI11_T1 · Validación adicional en puntos críticos.

    private function transitionModels(
        array $snapshot,
        array $scope
    ): array {
        $request = new TransformationImplementationRequest();

        $request->forceFill([
            'capability_key' => 'data_transformation_bi',
            'source_snapshot' => $snapshot,
        ]);

        $definition = new TransformationImplementationDefinition();

        $definition->forceFill([
            'implementation_scope' => $scope,
        ]);

        return [$request, $definition];
    }

    public function test_transition_rejects_missing_assessment(): void
    {
        [$request, $definition] = $this->transitionModels(
            $this->snapshot(),
            ['purpose' => 'Alcance BI']
        );

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestBusinessNeedsAssessment::assertTransitionReady(
            $request,
            $definition
        );
    }

    public function test_transition_rejects_pending_assessment(): void
    {
        [$request, $definition] = $this->transitionModels(
            $this->snapshot(),
            $this->scope([
                [
                    'key' => 'operaciones:uno',
                    'status' => 'included',
                    'rationale' => '',
                ],
                [
                    'key' => 'finanzas:dos',
                    'status' => 'pending',
                    'rationale' => '',
                ],
            ])
        );

        $this->expectException(ValidationException::class);

        TransformationImplementationRequestBusinessNeedsAssessment::assertTransitionReady(
            $request,
            $definition
        );
    }

    public function test_transition_accepts_complete_assessment(): void
    {
        [$request, $definition] = $this->transitionModels(
            $this->snapshot(),
            $this->scope([
                [
                    'key' => 'operaciones:uno',
                    'status' => 'included',
                    'rationale' => '',
                ],
                [
                    'key' => 'finanzas:dos',
                    'status' => 'excluded',
                    'rationale' => 'Exclusión revisada por LAUDA.',
                ],
            ])
        );

        TransformationImplementationRequestBusinessNeedsAssessment::assertTransitionReady(
            $request,
            $definition
        );

        $this->assertTrue(true);
    }

    public function test_legacy_transition_without_business_needs_is_compatible(): void
    {
        [$request, $definition] = $this->transitionModels(
            [],
            ['purpose' => 'Alcance histórico']
        );

        TransformationImplementationRequestBusinessNeedsAssessment::assertTransitionReady(
            $request,
            $definition
        );

        $this->assertTrue(true);
    }

    public function test_transition_gates_precede_state_changes(): void
    {
        $tenant = file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationRequestDefinitionTenantReviewService.php'
            )
        );

        $closure = file_get_contents(
            base_path(
                'app/Services/Diagnosis/TransformationImplementationRequestDefinitionFunctionalClosureService.php'
            )
        );

        $this->assertIsString($tenant);
        $this->assertIsString($closure);

        $needle =
            'TransformationImplementationRequestBusinessNeedsAssessment::assertTransitionReady(';

        $tenantGate = strpos($tenant, $needle);
        $tenantTransition = strpos($tenant, '->transitionByLauda(');

        $closureGate = strpos($closure, $needle);
        $closureSync = strpos(
            $closure,
            '$this->synchronizeDataBiSourceReadiness('
        );

        $this->assertNotFalse($tenantGate);
        $this->assertNotFalse($tenantTransition);
        $this->assertNotFalse($closureGate);
        $this->assertNotFalse($closureSync);

        $this->assertLessThan($tenantTransition, $tenantGate);
        $this->assertLessThan($closureSync, $closureGate);
    }

    private function snapshot(): array
    {
        return [
            'business_needs' => [
                'improvements' => [
                    ['key' => 'operaciones:uno'],
                    ['key' => 'finanzas:dos'],
                ],
            ],
        ];
    }

    private function scope(array $items): array
    {
        return [
            'business_needs_assessment' => [
                'schema_version' => 1,
                'items' => $items,
            ],
        ];
    }

    public function test_initial_assessments_remain_pending(): void
    {
        $result = TransformationImplementationRequestBusinessNeedsAssessment::normalize(
            $this->snapshot(),
            [],
            ['scope_confirmed' => false]
        );

        $this->assertSame(
            ['pending', 'pending'],
            array_column(
                $result['business_needs_assessment']['items'],
                'status'
            )
        );
    }

    public function test_unknown_key_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        TransformationImplementationRequestBusinessNeedsAssessment::normalize(
            $this->snapshot(),
            $this->scope([
                [
                    'key' => 'operaciones:uno',
                    'status' => 'included',
                    'rationale' => '',
                ],
                [
                    'key' => 'inventada',
                    'status' => 'included',
                    'rationale' => '',
                ],
            ])
        );
    }

    public function test_missing_improvement_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        TransformationImplementationRequestBusinessNeedsAssessment::normalize(
            $this->snapshot(),
            $this->scope([
                [
                    'key' => 'operaciones:uno',
                    'status' => 'included',
                    'rationale' => '',
                ],
            ])
        );
    }

    public function test_exclusion_requires_reason(): void
    {
        $this->expectException(ValidationException::class);

        TransformationImplementationRequestBusinessNeedsAssessment::normalize(
            $this->snapshot(),
            $this->scope([
                [
                    'key' => 'operaciones:uno',
                    'status' => 'excluded',
                    'rationale' => '',
                ],
                [
                    'key' => 'finanzas:dos',
                    'status' => 'included',
                    'rationale' => '',
                ],
            ])
        );
    }

    public function test_pending_evaluation_blocks_scope_confirmation(): void
    {
        $this->expectException(ValidationException::class);

        TransformationImplementationRequestBusinessNeedsAssessment::normalize(
            $this->snapshot(),
            [],
            ['scope_confirmed' => true]
        );
    }

    public function test_valid_completed_assessment_is_preserved(): void
    {
        $result = TransformationImplementationRequestBusinessNeedsAssessment::normalize(
            $this->snapshot(),
            $this->scope([
                [
                    'key' => 'finanzas:dos',
                    'status' => 'excluded',
                    'rationale' => 'Fuera del alcance de esta definición.',
                ],
                [
                    'key' => 'operaciones:uno',
                    'status' => 'included',
                    'rationale' => '',
                ],
            ]),
            ['scope_confirmed' => true]
        );

        $items = $result['business_needs_assessment']['items'];

        $this->assertSame('operaciones:uno', $items[0]['key']);
        $this->assertSame('included', $items[0]['status']);
        $this->assertSame('finanzas:dos', $items[1]['key']);
        $this->assertSame('excluded', $items[1]['status']);
    }

    public function test_legacy_request_without_needs_is_unchanged(): void
    {
        $scope = ['purpose' => 'Servicio funcional'];

        $this->assertSame(
            $scope,
            TransformationImplementationRequestBusinessNeedsAssessment::normalize(
                [],
                $scope
            )
        );
    }
}
