<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluationFinding;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationFindingFoundationContractTest
    extends TestCase
{
    private string $migration;

    private string $findingModel;

    private string $findingService;

    private string $evaluationModel;

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
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_27_130000_create_data_transformation_bi_evaluation_findings.php'
            );

        $this->findingModel =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiEvaluationFinding.php'
            );

        $this->findingService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationFindingService.php'
            );

        $this->evaluationModel =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiEvaluation.php'
            );

        $this->evaluationService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );

        foreach ([
            $this->migration,
            $this->findingModel,
            $this->findingService,
            $this->evaluationModel,
            $this->evaluationService,
        ] as $source) {
            self::assertIsString(
                $source
            );
        }
    }

    public function test_findings_have_a_relational_source_evidence_contract(): void
    {
        foreach ([
            "'data_transformation_bi_evaluation_findings'",
            "'data_transformation_bi_evaluation_finding_sources'",
            "'data_transformation_bi_evaluation_id'",
            "'data_transformation_bi_source_asset_id'",
            "'evidence_version'",
            "'dtbi_eval_finding_source_pk'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->migration
            );
        }
    }

    public function test_finding_types_and_priorities_are_explicit(): void
    {
        self::assertSame(
            [
                'weakness',
                'opportunity',
                'observation',
            ],
            DataTransformationBiEvaluationFinding::TYPES
        );

        self::assertSame(
            [
                'high',
                'medium',
                'low',
            ],
            DataTransformationBiEvaluationFinding::PRIORITIES
        );
    }

    public function test_findings_are_human_draft_objects_pinned_to_evidence(): void
    {
        foreach ([
            'lockedDraftEvaluation(',
            'evidence_version',
            'assertEvidenceSources(',
            'source_ids',
            'reconfirm(',
            'Los hallazgos solo pueden modificarse mientras la evaluación está en borrador.',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->findingService
            );
        }
    }

    public function test_evaluation_cannot_reach_review_without_current_findings(): void
    {
        foreach ([
            'assertReviewableFindings(',
            'Registra al menos un hallazgo profesional',
            'Hay hallazgos que no han sido confirmados contra la versión actual',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->evaluationService
            );
        }

        self::assertSame(
            3,
            substr_count(
                $this->evaluationService,
                'assertReviewableFindings('
            )
        );
    }

    public function test_evaluation_exposes_findings_relation(): void
    {
        self::assertStringContainsString(
            'public function findings(): HasMany',
            $this->evaluationModel
        );

        self::assertStringContainsString(
            'DataTransformationBiEvaluationFinding::class',
            $this->evaluationModel
        );
    }

    public function test_finding_foundation_does_not_start_implementation_pipeline(): void
    {
        foreach ([
            'DataTransformationBiCanonical',
            'DataTransformationBiStaging',
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiProcessingRun',
            'DataTransformationBiIntakeBatch',
            'normalized_payload',
            'automatic_score(',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->findingService
            );
        }

        self::assertStringContainsString(
            "'automatic_score' =>",
            $this->findingService
        );

        self::assertStringContainsString(
            'false',
            $this->findingService
        );
    }
}
