<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationDatasetMaterializerContractTest
    extends TestCase
{
    public function test_materializer_preserves_modern_boundaries(): void
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
                .'DataTransformationBiImplementationDatasetMaterializer.php'
            );

        $this->assertIsString(
            $source
        );

        $required = [
            'assertActiveForRequest(',
            '::STATUS_VALIDATED',
            'publishedRegistry(',
            'registryPayload(',
            'DataTransformationBiSourceAssetRowReader',
            'DataTransformationBiMappingProjector',
            'DataTransformationBiImplementationDataset',
            'DataTransformationBiImplementationRow',
            'DB::transaction(',
            'lockForUpdate()',
            'hash_init(',
            'hash_update(',
            'hash_final(',
            'canonicalJson(',
            'INSERT_CHUNK_SIZE',
        ];

        foreach ($required as $needle) {
            $this->assertStringContainsString(
                $needle,
                $source
            );
        }

        $forbidden = [
            'TransformationImplementationExecutionService',
            'DataTransformationBiProcessingRun',
            'DataTransformationBiIntakeBatch',
            'DataTransformationBiNormalizedRow',
            'ready_for_execution',
            'execution_started',
            '$lockedMapping->canonical_registry_version_id',
        ];

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $source
            );
        }
    }

    public function test_materializer_is_all_or_nothing_in_first_version(): void
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
                .'DataTransformationBiImplementationDatasetMaterializer.php'
            );

        $this->assertIsString(
            $source
        );

        /*
         * The whole materialization is returned directly from one DB
         * transaction. There must not be a secondary catch that persists
         * FAILED after partial row creation.
         */
        $this->assertStringContainsString(
            'return DB::transaction(',
            $source
        );

        $this->assertStringNotContainsString(
            'STATUS_FAILED',
            $source
        );

        $this->assertStringNotContainsString(
            "'failure_code' =>\n                                '",
            $source
        );
    }

    public function test_ready_dataset_is_idempotently_reused(): void
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
                .'DataTransformationBiImplementationDatasetMaterializer.php'
            );

        $this->assertStringContainsString(
            'assertReusableDataset(',
            $source
        );

        $this->assertStringContainsString(
            '::STATUS_READY',
            $source
        );

        $this->assertStringContainsString(
            "'data_transformation_bi_source_asset_mapping_id'",
            $source
        );
    }
}
