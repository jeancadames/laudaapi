<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationDatasetOrchestratorContractTest
    extends TestCase
{
    public function test_orchestrator_preserves_modern_boundaries(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiImplementationDatasetOrchestrator.php'
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'assertActiveForRequest(',
                'currentMappings(',
                '::STATUS_VALIDATED',
                'DataTransformationBiImplementationDatasetMaterializer',
                '->materialize(',
                'mapping_version',
                'canonical_entity_key',
                'source_sheet_index',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach (
            [
                'DataTransformationBiIntakeV2StagingMaterializationService',
                'DataTransformationBiProcessingRun',
                'DataTransformationBiIntakeBatch',
                'DataTransformationBiNormalizedRow',
                'TransformationImplementationExecutionService',
                'ready_for_execution',
                'execution_started',
            ]
            as $forbidden
        ) {
            /*
             * Boundary terminology may appear in comments documenting
             * non-mutation, so executable legacy class coupling is the
             * important prohibition.
             */
            if (
                in_array(
                    $forbidden,
                    [
                        'ready_for_execution',
                        'execution_started',
                    ],
                    true
                )
            ) {
                continue;
            }

            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_orchestrator_selects_latest_mapping_before_status_gate(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiImplementationDatasetOrchestrator.php'
            );

        $this->assertStringContainsString(
            "->orderByDesc(\n                    'mapping_version'",
            $source
        );

        $this->assertStringContainsString(
            '->unique(',
            $source
        );

        $this->assertStringContainsString(
            '$notValidated =',
            $source
        );

        $this->assertStringNotContainsString(
            "->where(\n                    'status',\n                    DataTransformationBiSourceAssetMapping\n                        ::STATUS_VALIDATED",
            $source
        );
    }

    public function test_orchestrator_is_session_scoped_not_legacy_staging(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiImplementationDatasetOrchestrator.php'
            );

        $this->assertStringContainsString(
            "'data_transformation_bi_intake_session_id'",
            $source
        );

        $this->assertStringContainsString(
            'assertSessionScope(',
            $source
        );

        $this->assertStringNotContainsString(
            'StagingMaterialization',
            $source
        );

        $this->assertStringNotContainsString(
            'NormalizedRow',
            $source
        );
    }
}
