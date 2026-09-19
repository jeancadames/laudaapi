<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceReadModelContractTest
    extends TestCase
{
    private string $controller;

    private string $intakeState;

    private string $readiness;

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
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        $this->intakeState =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        $this->readiness =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceReadinessService.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->intakeState
        );

        self::assertIsString(
            $this->readiness
        );
    }

    public function test_preparation_and_source_workspace_use_different_read_models(): void
    {
        foreach (
            [
                'DataTransformationBiPreparationStatusReadModel',
                'DataTransformationBiIntakeV2StateService',
                'DataTransformationBiSourceReadinessService',
                '$preparationStatus',
                '$intakeState',
                '$sourceReadiness',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_dynamic_sources_do_not_come_from_preparation_status(): void
    {
        self::assertStringNotContainsString(
            "\$dataPreparation['source_assets']",
            $this->controller
        );

        self::assertStringContainsString(
            "\$sourceWorkspaceState[\n"
            ."                        'source_assets'\n"
            ."                    ]",
            $this->controller
        );

        self::assertStringContainsString(
            "'source_assets' =>",
            $this->controller
        );

        self::assertStringContainsString(
            '$sourceAssets',
            $this->controller
        );
    }

    public function test_workspace_request_is_resolved_server_side_and_company_scoped(): void
    {
        foreach (
            [
                'TransformationImplementationRequest::query()',
                '->whereKey(',
                "'company_id'",
                '(int) $company->id',
                "'capability_key'",
                "'data_transformation_bi'",
                '->first()',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }

        foreach (
            [
                "\$request->input('company_id'",
                "\$request->input('implementation_request_id'",
                "\$request->input('request_id'",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->controller
            );
        }
    }

    public function test_source_workspace_payload_is_deliberately_narrow(): void
    {
        foreach (
            [
                "'source_workspace' =>",
                "'session' =>",
                "'id' =>",
                "'status' =>",
                "'actions' =>",
                "'can_start_or_resume'",
                "'can_manage_sources'",
                "'readiness' =>",
                "'inputs_validated'",
                "'accesses_validated'",
                "'source_count'",
                "'complete_source_count'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }

        self::assertStringNotContainsString(
            "'source_workspace' =>\n"
            ."                    \$sourceWorkspaceState",
            $this->controller
        );
    }

    public function test_readiness_is_server_owned(): void
    {
        self::assertStringContainsString(
            '$sourceReadiness->forRequest(',
            $this->controller
        );

        self::assertStringContainsString(
            'DataTransformationBiSourceReadinessService',
            $this->controller
        );

        foreach (
            [
                "\$request->boolean('inputs_validated'",
                "\$request->boolean('accesses_validated'",
                "\$request->input('inputs_validated'",
                "\$request->input('accesses_validated'",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->controller
            );
        }
    }

    public function test_intake_state_service_is_actual_source_asset_projection(): void
    {
        foreach (
            [
                'public function forRequest(',
                "'session' =>",
                "'source_assets' =>",
                "'actions' =>",
                'private function sourceAssetsPayload(',
                "'data_transformation_bi_intake_session_id'",
                "'company_id'",
                "'archived_at'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->intakeState
            );
        }
    }

    public function test_intake_projection_remains_without_private_storage_paths_or_credentials(): void
    {
        $start =
            strpos(
                $this->intakeState,
                'private function sourceAssetsPayload('
            );

        self::assertNotFalse(
            $start
        );

        $block =
            strtolower(
                substr(
                    $this->intakeState,
                    $start
                )
            );

        foreach (
            [
                'source_path',
                'password',
                'connection_string',
                'access_token',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_readiness_service_owns_completion_rule(): void
    {
        foreach (
            [
                'inputs_validated',
                'accesses_validated',
                'source_count',
                'complete_source_count',
                'STATUS_ACTIVE',
                'STATUS_READY',
                'DATA_RECEIVED',
                'DATA_ANALYZED',
                'STATUS_UPLOADED',
                'FORMAT_CSV',
                'FORMAT_XLSX',
                'whereNull(',
                "'archived_at'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->readiness
            );
        }
    }

    public function test_zero_source_readiness_is_false_false(): void
    {
        self::assertStringContainsString(
            "'inputs_validated' => false",
            $this->readiness
        );

        self::assertStringContainsString(
            "'accesses_validated' => false",
            $this->readiness
        );
    }
}
