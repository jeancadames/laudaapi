<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceWorkspaceHttpContractTest
    extends TestCase
{
    private string $controller;

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
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiSourceWorkspaceController.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/web.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_tenant_workspace_exposes_only_expected_actions(): void
    {
        foreach (
            [
                'public function prepareWorkspace(',
                'public function createSourceAsset(',
                'public function updateSourceAsset(',
                'public function reorderSourceAssets(',
                'public function archiveSourceAsset(',
                'public function updateSourceAssetStructure(',
                'public function uploadSourceAssetData(',
                'public function previewSourceAssetSqlServerExtraction(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_company_and_request_are_resolved_server_side(): void
    {
        foreach (
            [
                'SubscriberResolver',
                'CompanyContextResolver',
                'TenantAccessService::SUBSCRIBER_ADMIN',
                "'tenant_admin'",
                "'company_id'",
                "'capability_key'",
                "'data_transformation_bi'",
                'orderByDesc(',
                "'attempt'",
                'DataTransformationBiIntakeActorAuthorizationService',
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
                "\$request->input('subscriber_id'",
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

    public function test_controller_fails_closed_to_known_non_cancelled_request_states(): void
    {
        foreach (
            [
                'STATUS_REQUESTED',
                'STATUS_UNDER_LAUDA_REVIEW',
                'STATUS_DEFINITION_PREPARATION',
                'STATUS_AWAITING_TENANT_REVIEW',
                'STATUS_CHANGES_REQUESTED',
                'STATUS_DEFINITION_AGREED',
                'STATUS_READY_FOR_COMMERCIAL',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }

        self::assertStringNotContainsString(
            'STATUS_CANCELLED,',
            $this->controller
        );
    }

    public function test_session_and_source_are_cross_tenant_scoped(): void
    {
        foreach (
            [
                'private function scopedSession(',
                'transformation_implementation_request_id',
                'private function scopedSourceAsset(',
                'data_transformation_bi_intake_session_id',
                'implementationRequest->company_id',
                'session->getKey()',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_controller_reuses_existing_domain_services(): void
    {
        foreach (
            [
                'DataTransformationBiIntakeV2SessionService',
                'DataTransformationBiSourceAssetService',
                'DataTransformationBiSourceAssetStructureService',
                'DataTransformationBiSourceAssetDataUploadService',
                'DataTransformationBiSqlServerExtractionAssistant',
                'DataTransformationBiIntakeV2StateService',
                '->startOrReuse(',
                '->create(',
                '->update(',
                '->reorder(',
                '->archive(',
                '->save(',
                '->persist(',
                '->preview(',
                '->forRequest(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->controller
            );
        }
    }

    public function test_tenant_metadata_contract_does_not_accept_internal_fields(): void
    {
        $start =
            strpos(
                $this->controller,
                '$request->only(['
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->controller,
                ']);',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $this->controller,
                $start,
                $end - $start
            );

        foreach (
            [
                "'display_name'",
                "'source_object_name'",
                "'description'",
                "'origin_system'",
                "'delivery_format'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }

        foreach (
            [
                "'company_id'",
                "'domain_key'",
                "'status'",
                "'structure_status'",
                "'data_status'",
                "'structure_snapshot'",
                "'profiling_snapshot'",
                "'source_disk'",
                "'source_path'",
                "'created_by_user_id'",
                "'updated_by_user_id'",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_routes_are_auth_verified_and_do_not_include_request_id(): void
    {
        self::assertStringContainsString(
            "Route::middleware(['auth', 'verified'])",
            $this->routes
        );

        self::assertStringContainsString(
            "->prefix('/app/transformacion-360/datos-bi/fuentes')",
            $this->routes
        );

        self::assertStringContainsString(
            "->name('app.transformation.data_bi.sources.')",
            $this->routes
        );

        self::assertStringNotContainsString(
            '/datos-bi/fuentes/{implementationRequest}',
            $this->routes
        );

        self::assertStringNotContainsString(
            '/datos-bi/fuentes/{company}',
            $this->routes
        );
    }

    public function test_routes_are_session_and_source_number_constrained(): void
    {
        foreach (
            [
                "'prepareWorkspace'",
                "'createSourceAsset'",
                "'reorderSourceAssets'",
                "'updateSourceAsset'",
                "'archiveSourceAsset'",
                "'updateSourceAssetStructure'",
                "'uploadSourceAssetData'",
                "'previewSourceAssetSqlServerExtraction'",
                "->whereNumber('sessionId')",
                "->whereNumber('sourceAssetId')",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->routes
            );
        }
    }

    public function test_no_remote_credentials_are_collected(): void
    {
        $lower =
            strtolower(
                $this->controller
            );

        foreach (
            [
                'password',
                'connection_string',
                'access_token',
                'sqlsrv_connect',
                'odbc_connect',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $lower
            );
        }
    }

    public function test_source_mutations_return_safe_state_projection(): void
    {
        self::assertStringContainsString(
            'private function stateResponse(',
            $this->controller
        );

        self::assertStringContainsString(
            '$this->stateService->forRequest(',
            $this->controller
        );

        self::assertStringNotContainsString(
            "'source_path' =>",
            $this->controller
        );

        self::assertStringNotContainsString(
            "'source_disk' =>",
            $this->controller
        );
    }
}
