<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Services\Diagnosis\DataTransformationBiEvaluationService;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

final class DataTransformationBiSemanticEvidenceVersionCompatibilityTest
    extends TestCase
{
    public function test_historical_v1_evaluation_resolves_as_v1(): void
    {
        self::assertSame(
            1,
            $this->schemaVersion([
                'schema_version' => 1,
            ])
        );
    }

    public function test_new_v2_evaluation_resolves_as_v2(): void
    {
        self::assertSame(
            2,
            $this->schemaVersion([
                'schema_version' => 2,
            ])
        );
    }

    public function test_legacy_snapshot_without_version_defaults_to_v1(): void
    {
        self::assertSame(
            1,
            $this->schemaVersion([])
        );
    }

    public function test_unknown_future_evidence_schema_fails_closed(): void
    {
        $this->expectException(
            ValidationException::class
        );

        $this->schemaVersion([
            'schema_version' => 999,
        ]);
    }

    private function schemaVersion(
        array $snapshot
    ): int {
        $evaluation =
            new DataTransformationBiEvaluation();

        $evaluation->forceFill([
            'evidence_snapshot' =>
                $snapshot,
        ]);

        $method =
            new ReflectionMethod(
                DataTransformationBiEvaluationService::class,
                'evidenceSchemaVersion'
            );

        $method->setAccessible(
            true
        );

        return $method->invoke(
            app(
                DataTransformationBiEvaluationService::class
            ),
            $evaluation
        );
    }
}
