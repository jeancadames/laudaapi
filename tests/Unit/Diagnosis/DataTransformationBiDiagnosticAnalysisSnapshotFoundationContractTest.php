<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticAnalysisSnapshotFoundationContractTest
    extends TestCase
{
    private string $migration;
    private string $model;
    private string $evaluationService;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->migration =
            (string) file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_28_225500_'
                .'add_diagnostic_analysis_snapshot_'
                .'to_data_transformation_bi_evaluations.php'
            );

        $this->model =
            (string) file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiEvaluation.php'
            );

        $this->evaluationService =
            (string) file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );
    }

    public function test_evaluation_has_separate_nullable_diagnostic_analysis_contract(): void
    {
        foreach ([
            'diagnostic_analysis_schema_version',
            'diagnostic_analysis_evidence_version',
            'diagnostic_analysis_sha256',
            'diagnostic_analysis_snapshot',
            'diagnostic_analysis_generated_at',
        ] as $field) {
            self::assertStringContainsString(
                $field,
                $this->migration
            );

            self::assertStringContainsString(
                "'".$field."'",
                $this->model
            );
        }

        self::assertStringContainsString(
            '->json(',
            $this->migration
        );

        self::assertStringContainsString(
            "'diagnostic_analysis_snapshot'",
            $this->migration
        );
    }

    public function test_historical_evaluations_remain_compatible_through_nullable_columns(): void
    {
        /*
         * Every new analysis column must remain nullable because
         * historical V1/V2 evaluations have no analysis snapshot.
         */
        self::assertGreaterThanOrEqual(
            5,
            substr_count(
                $this->migration,
                '->nullable()'
            )
        );

        self::assertStringNotContainsString(
            'default(',
            $this->migration
        );
    }

    public function test_analysis_is_explicitly_pinned_to_evidence_revision(): void
    {
        self::assertStringContainsString(
            "'diagnostic_analysis_evidence_version'",
            $this->model
        );

        self::assertStringContainsString(
            "'evidence_version'",
            $this->model
        );
    }

    public function test_analysis_storage_remains_separate_from_evidence_storage(): void
    {
        self::assertStringContainsString(
            "'evidence_snapshot'",
            $this->model
        );

        self::assertStringContainsString(
            "'diagnostic_analysis_snapshot'",
            $this->model
        );

        self::assertStringContainsString(
            "'diagnostic_analysis_evidence_version'",
            $this->model
        );
    }

    public function test_foundation_has_no_scoring_readiness_or_future_pipeline_dependency(): void
    {
        $combined =
            $this->migration
            .PHP_EOL
            .$this->model;

        foreach ([
            'DataTransformationBiCanonical',
            'DataTransformationBiStaging',
            'DataTransformationBiSourceAssetMapping',
            'DataTransformationBiPreparedDataset',
            'readiness_score',
            'risk_score',
            'opportunity_score',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $combined
            );
        }
    }

    public function test_existing_evaluation_lifecycle_remains_unchanged(): void
    {
        self::assertSame(
            [
                'draft',
                'ready_for_review',
                'published',
            ],
            DataTransformationBiEvaluation::STATUSES
        );
    }
}
