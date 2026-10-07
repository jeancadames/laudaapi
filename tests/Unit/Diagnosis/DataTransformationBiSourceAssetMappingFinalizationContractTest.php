<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetMappingFinalizationContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source =
            file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetMappingService.php'
            );

        self::assertIsString(
            $this->source
        );
    }

    public function test_mapping_has_explicit_ready_then_validate_lifecycle(): void
    {
        foreach (
            [
                'public function markReady(',
                'public function validate(',
                '::STATUS_READY',
                '::STATUS_VALIDATED',
                "'validated_by_user_id'",
                "'validated_at'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_both_finalization_writes_require_active_authorization(): void
    {
        foreach (
            [
                'public function markReady(',
                'public function validate(',
            ]
            as $method
        ) {
            $start =
                strpos(
                    $this->source,
                    $method
                );

            self::assertNotFalse(
                $start
            );

            $next =
                strpos(
                    $this->source,
                    "\n    /**",
                    $start + strlen($method)
                );

            self::assertNotFalse(
                $next
            );

            $body =
                substr(
                    $this->source,
                    $start,
                    $next - $start
                );

            self::assertStringContainsString(
                'DB::transaction(',
                $body
            );

            self::assertStringContainsString(
                '->assertActiveForRequest(',
                $body
            );

            self::assertStringContainsString(
                'true',
                $body
            );

            self::assertStringContainsString(
                'lockForUpdate()',
                $body
            );
        }
    }

    public function test_ready_requires_complete_exact_canonical_decision_set(): void
    {
        foreach (
            [
                'assertFinalizationCompleteness(',
                "'entities'",
                "'fields'",
                'columnsByKey(',
                'count($fieldsByKey)',
                'count($canonicalFields)',
                'Debes registrar una decisión explícita para cada',
                'campo que ya no pertenece',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_required_canonical_field_cannot_be_unmapped(): void
    {
        self::assertStringContainsString(
            '::TYPE_UNMAPPED',
            $this->source
        );

        self::assertStringContainsString(
            "'required'",
            $this->source
        );

        self::assertStringContainsString(
            'Un campo canónico obligatorio no puede',
            $this->source
        );
    }

    public function test_transform_cannot_finalize_before_controlled_catalog_exists(): void
    {
        self::assertStringContainsString(
            '::TYPE_TRANSFORM',
            $this->source
        );

        self::assertStringContainsString(
            'falta el catálogo controlado de',
            $this->source
        );
    }

    public function test_ready_does_not_stamp_validation_metadata(): void
    {
        $start =
            strpos(
                $this->source,
                'public function markReady('
            );

        $end =
            strpos(
                $this->source,
                'public function validate(',
                $start
            );

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        $method =
            substr(
                $this->source,
                $start,
                $end - $start
            );

        self::assertStringContainsString(
            "'validated_by_user_id' =>\n                            null",
            $method
        );

        self::assertStringContainsString(
            "'validated_at' =>\n                            null",
            $method
        );

        self::assertStringNotContainsString(
            "'validated_by_user_id' =>\n                            (int) \$actor->getKey()",
            $method
        );
    }

    public function test_validation_requires_ready_parent_and_ready_fields(): void
    {
        $start =
            strpos(
                $this->source,
                'public function validate('
            );

        self::assertNotFalse(
            $start
        );

        $method =
            substr(
                $this->source,
                $start
            );

        self::assertStringContainsString(
            '::STATUS_READY',
            $method
        );

        self::assertStringContainsString(
            'Solo un mapeo listo puede validarse.',
            $method
        );

        self::assertStringContainsString(
            '::STATUS_VALIDATED',
            $method
        );

        self::assertStringContainsString(
            "'validated_by_user_id' =>\n                            (int) \$actor->getKey()",
            $method
        );

        self::assertStringContainsString(
            "'validated_at' =>\n                            \$now",
            $method
        );
    }

    public function test_finalization_does_not_start_execution_or_materialize_data(): void
    {
        foreach (
            [
                'TransformationImplementationExecutionService',
                'TransformationImplementationCapabilityExecution',
                'TransformationImplementationPhaseExecution',
                'DataTransformationBiNormalizedRow',
                'DataTransformationBiProcessingRun',
                "'ready_for_execution' => true",
                "'execution_started' => true",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->source
            );
        }
    }
}
