<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationAdminHttpContractTest
    extends TestCase
{
    private string $controller;

    private string $readModel;

    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiEvaluationController.php'
            );

        $this->readModel =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationWorkspaceReadModel.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->readModel
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_workspace_get_is_read_only(): void
    {
        self::assertStringContainsString(
            'public function workspace(',
            $this->controller
        );

        $start =
            strpos(
                $this->controller,
                'public function workspace('
            );

        $end =
            strpos(
                $this->controller,
                'public function prepare(',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $workspaceMethod =
            substr(
                $this->controller,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            'createOrRefreshDraft(',
            $workspaceMethod
        );

        self::assertStringNotContainsString(
            'markReadyForReview(',
            $workspaceMethod
        );

        self::assertStringNotContainsString(
            'publish(',
            $workspaceMethod
        );
    }

    public function test_all_mutations_are_explicit_admin_endpoints(): void
    {
        foreach ([
            'evaluation/workspace',
            'evaluation/prepare',
            'evaluation/findings',
            'evaluation/ready-for-review',
            'evaluation/publish',
            'findings/{findingId}/reconfirm',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->routes
            );
        }

        foreach ([
            "'prepare'",
            "'createFinding'",
            "'updateFinding'",
            "'reconfirmFinding'",
            "'deleteFinding'",
            "'readyForReview'",
            "'publish'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->routes
            );
        }
    }

    public function test_controller_enforces_admin_and_data_bi_request_boundary(): void
    {
        foreach ([
            '(string) $actor->role === \'admin\'',
            "'data_transformation_bi'",
            'DataTransformationBiTenantSourceWorkspaceGate::class',
            '->assertCanManage(',
            '$this->assertRequest(',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->controller
            );
        }
    }

    public function test_controller_scopes_session_evaluation_and_findings(): void
    {
        foreach ([
            "'transformation_implementation_request_id'",
            "'company_id'",
            "'data_transformation_bi_intake_session_id'",
            "'data_transformation_bi_evaluation_id'",
            'firstOrFail()',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->controller
            );
        }
    }

    public function test_workspace_exposes_professional_state_without_private_hashes(): void
    {
        foreach ([
            "'diagnostic_summary'",
            "'evaluation'",
            "'findings'",
            "'evidence_version'",
            "'evidence_current'",
            "'finding_count'",
            "'stale_finding_count'",
            "'actions'",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->readModel
            );
        }

        foreach ([
            'submission_manifest_sha256',
            'evidence_sha256',
            'profiling_snapshot'." =>",
            'source_path',
            'source_disk',
            'reader_configuration',
            'raw_values',
            'sample_values',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->readModel
            );
        }
    }

    public function test_workspace_is_session_level_not_source_level(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiIntakeSession $session',
            $this->readModel
        );

        self::assertStringNotContainsString(
            'int $sourceAssetId',
            $this->controller
        );

        self::assertStringNotContainsString(
            'source-assets/{sourceAssetId}/evaluation',
            $this->routes
        );
    }

    public function test_http_layer_does_not_start_future_implementation_pipeline(): void
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
                $this->controller
            );

            self::assertStringNotContainsString(
                $forbidden,
                $this->readModel
            );
        }
    }
}
