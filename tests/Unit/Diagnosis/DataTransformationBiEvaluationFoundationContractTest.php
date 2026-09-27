<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationFoundationContractTest
    extends TestCase
{
    private string $migration;

    private string $model;

    private string $service;

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
                .'2026_09_27_024500_create_data_transformation_bi_evaluations.php'
            );

        $this->model =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiEvaluation.php'
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );

        self::assertIsString(
            $this->migration
        );

        self::assertIsString(
            $this->model
        );

        self::assertIsString(
            $this->service
        );
    }

    public function test_evaluation_is_scoped_to_one_submitted_session(): void
    {
        foreach ([
            "'data_transformation_bi_evaluations'",
            "'data_transformation_bi_intake_session_id'",
            "'submission_manifest_sha256'",
            "'evidence_sha256'",
            "'evidence_snapshot'",
            "'evidence_captured_at'",
            "'dtbi_eval_session_uq'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->migration
            );
        }
    }

    public function test_evaluation_lifecycle_is_explicit(): void
    {
        self::assertSame(
            [
                'draft',
                'ready_for_review',
                'published',
            ],
            DataTransformationBiEvaluation::STATUSES
        );

        foreach ([
            'STATUS_DRAFT',
            'STATUS_READY_FOR_REVIEW',
            'STATUS_PUBLISHED',
            'ready_for_review_at',
            'published_at',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->model
            );
        }
    }

    public function test_draft_evidence_is_aggregate_and_profile_pinned(): void
    {
        foreach ([
            'createOrRefreshDraft(',
            'submitted_manifest_sha256',
            'profiling_job_uuid',
            'profiled_at',
            'diagnostic_summary',
            'DataTransformationBiSourceDiagnosticReadModel',
            "'completed'",
            "'analyzed'",
            "'evidence_version'",
            "'evidence_sha256'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }

        self::assertStringContainsString(
            'No raw client values, samples',
            $this->service
        );
    }

    public function test_review_and_publication_fail_closed_when_evidence_changes(): void
    {
        foreach ([
            'markReadyForReview(',
            'publish(',
            'assertEvidenceStillCurrent(',
            'hash_equals(',
            'STATUS_READY_FOR_REVIEW',
            'STATUS_PUBLISHED',
            'El profiling cambió después de capturar la evidencia',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }
    }

    public function test_foundation_does_not_depend_on_future_implementation_pipeline(): void
    {
        foreach ([
            'DataTransformationBiCanonical',
            'DataTransformationBiStaging',
            'DataTransformationBiNormalizedRow',
            'DataTransformationBiProcessingRun',
            'DataTransformationBiIntakeBatch',
            'normalized_payload',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->service
            );
        }

        self::assertStringNotContainsString(
            'TransformationCapabilityNeedEvaluation',
            $this->model
        );

        self::assertStringNotContainsString(
            'TransformationCapabilityNeedEvaluation',
            $this->service
        );
    }
}
