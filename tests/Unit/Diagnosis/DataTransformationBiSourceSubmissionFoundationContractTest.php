<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceSubmissionFoundationContractTest
    extends TestCase
{
    private string $model;
    private string $migration;
    private string $service;
    private string $sessionService;
    private string $state;
    private string $projection;
    private string $controller;
    private string $routes;
    private string $profiling;
    private string $profilingDispatch;
    private string $sourceService;
    private string $structureService;
    private string $uploadService;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->model =
            file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiIntakeSession.php'
            );

        $this->migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_26_210000_add_evaluation_submission_to_'
                .'data_transformation_bi_intake_sessions.php'
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceSubmissionService.php'
            );

        $this->sessionService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2SessionService.php'
            );

        $this->state =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        $this->projection =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantSourceWorkspaceProjection.php'
            );

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiSourceWorkspaceController.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/web.php'
            );

        $this->profiling =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetProfilingService.php'
            );

        $this->profilingDispatch =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetProfilingDispatchService.php'
            );

        $this->sourceService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetService.php'
            );

        $this->structureService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetStructureService.php'
            );

        $this->uploadService =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetDataUploadService.php'
            );

        foreach ([
            $this->model,
            $this->migration,
            $this->service,
            $this->sessionService,
            $this->state,
            $this->projection,
            $this->controller,
            $this->routes,
            $this->profiling,
            $this->profilingDispatch,
            $this->sourceService,
            $this->structureService,
            $this->uploadService,
        ] as $source) {
            self::assertIsString(
                $source
            );
        }
    }

    public function test_session_has_distinct_evaluation_submission_state(): void
    {
        foreach ([
            'STATUS_SUBMITTED_FOR_EVALUATION',
            "'submitted_for_evaluation'",
            "'submitted_at'",
            "'submitted_by_user_id'",
            "'submitted_manifest_sha256'",
            'submittedBy()',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->model
            );
        }
    }

    public function test_submission_evidence_is_persisted_separately_from_legacy_ready_state(): void
    {
        foreach ([
            "'submitted_at'",
            "'submitted_by_user_id'",
            "'submitted_manifest_sha256'",
            "'dtbi_intake_sessions_submitted_user_fk'",
            "->on('users')",
            '->nullOnDelete()',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->migration
            );
        }

        self::assertStringContainsString(
            "'ready_at'",
            $this->model
        );

        self::assertStringContainsString(
            "'finalized_at'",
            $this->model
        );
    }

    public function test_submission_uses_only_dynamic_source_delivery_evidence(): void
    {
        foreach ([
            'DataTransformationBiSourceAsset',
            'DataTransformationBiSourceAssetFile',
            "'source_sha256'",
            "'structure_text'",
            "'sort_order'",
            'lockForUpdate()',
            "hash(\n                            'sha256'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->service
            );
        }

        foreach ([
            'DataTransformationBiIntakeDomainDelivery',
            'DataTransformationBiStandardIntakeSchema',
            'DataTransformationBiIntakeBatch',
            'profiling_snapshot',
            'diagnostic_summary',
            'normalized_rows',
            'staging_rows',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->service
            );
        }
    }

    public function test_submission_requires_current_complete_csv_or_xlsx_sources(): void
    {
        foreach ([
            '::STATUS_ACTIVE',
            '::STATUS_READY',
            '::DATA_RECEIVED',
            '::DATA_ANALYZED',
            '::STATUS_UPLOADED',
            '::FORMAT_CSV',
            '::FORMAT_XLSX',
            'sources->isEmpty()',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->service
            );
        }
    }

    public function test_submission_is_idempotent_and_draft_only(): void
    {
        foreach ([
            '::STATUS_DRAFT',
            '::STATUS_SUBMITTED_FOR_EVALUATION',
            "'reused'",
            'submittedResult(',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->service
            );
        }

        self::assertStringContainsString(
            'Solo una entrega en borrador puede enviarse a evaluación.',
            $this->service
        );
    }

    public function test_prepare_reuses_submitted_delivery_without_mutating_it(): void
    {
        self::assertStringContainsString(
            '::STATUS_SUBMITTED_FOR_EVALUATION',
            $this->sessionService
        );

        self::assertStringContainsString(
            'A submitted source delivery is frozen.',
            $this->sessionService
        );
    }

    public function test_http_boundary_exposes_tenant_submission_only(): void
    {
        foreach ([
            "'submitForEvaluation'",
            "'submit_for_evaluation'",
            "'/sesiones/{sessionId}/enviar-evaluacion'",
            'DataTransformationBiSourceSubmissionService',
            'public function submitForEvaluation(',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->routes
                ."\n"
                .$this->controller
            );
        }
    }

    public function test_state_separates_manage_and_submit_actions(): void
    {
        foreach ([
            "'can_manage_sources'",
            "'can_submit_for_evaluation'",
            '::STATUS_SUBMITTED_FOR_EVALUATION',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->state
            );
        }
    }

    public function test_tenant_projection_exposes_timestamp_but_not_submission_hash_or_actor(): void
    {
        foreach ([
            "'submitted_at'",
            "'can_submit_for_evaluation'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->projection
            );
        }

        self::assertStringNotContainsString(
            "'submitted_manifest_sha256'",
            $this->projection
        );

        self::assertStringNotContainsString(
            "'submitted_by_user_id'",
            $this->projection
        );
    }

    public function test_tenant_mutation_services_do_not_accept_submitted_state(): void
    {
        foreach ([
            $this->sourceService,
            $this->structureService,
            $this->uploadService,
        ] as $source) {
            self::assertStringContainsString(
                'STATUS_DRAFT',
                $source
            );

            self::assertStringContainsString(
                'STATUS_READY',
                $source
            );

            self::assertStringNotContainsString(
                'STATUS_SUBMITTED_FOR_EVALUATION',
                $source
            );
        }
    }

    public function test_lauda_profiling_remains_available_after_submission(): void
    {
        foreach ([
            $this->profiling,
            $this->profilingDispatch,
        ] as $source) {
            self::assertStringContainsString(
                'STATUS_SUBMITTED_FOR_EVALUATION',
                $source
            );
        }
    }
}
