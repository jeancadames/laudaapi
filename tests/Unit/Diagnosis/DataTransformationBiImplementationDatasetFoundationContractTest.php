<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiImplementationDataset;
use App\Models\DataTransformationBiImplementationRow;
use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationDatasetFoundationContractTest
    extends TestCase
{
    public function test_modern_dataset_contract_is_separate_from_legacy_processing(): void
    {
        $dataset =
            new DataTransformationBiImplementationDataset();

        $row =
            new DataTransformationBiImplementationRow();

        $this->assertSame(
            'data_transformation_bi_implementation_datasets',
            $dataset->getTable()
        );

        $this->assertSame(
            'data_transformation_bi_implementation_rows',
            $row->getTable()
        );

        $this->assertSame(
            'building',
            DataTransformationBiImplementationDataset
                ::STATUS_BUILDING
        );

        $this->assertSame(
            'ready',
            DataTransformationBiImplementationDataset
                ::STATUS_READY
        );

        $this->assertSame(
            'failed',
            DataTransformationBiImplementationDataset
                ::STATUS_FAILED
        );

        $root =
            dirname(
                __DIR__,
                3
            );

        $datasetSource =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiImplementationDataset.php'
            );

        $migrationSource =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_10_07_233500_'
                .'create_data_transformation_bi_'
                .'implementation_dataset_foundation.php'
            );

        $this->assertIsString(
            $datasetSource
        );

        $this->assertIsString(
            $migrationSource
        );

        $this->assertStringNotContainsString(
            'DataTransformationBiProcessingRun',
            $datasetSource
        );

        $this->assertStringNotContainsString(
            'DataTransformationBiIntakeBatch',
            $datasetSource
        );

        $this->assertStringNotContainsString(
            'data_transformation_bi_processing_run_id',
            $migrationSource
        );

        $this->assertStringNotContainsString(
            'data_transformation_bi_intake_batch_id',
            $migrationSource
        );
    }
}
