<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationRequestBusinessNeedsAssessment;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TransformationImplementationRequestBusinessNeedsAssessmentTest extends TestCase
{
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
