<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetMappingHttpContractTest
    extends TestCase
{
    private string $controller;
    private string $routes;
    private string $service;

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
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        $this->service =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetMappingService.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );

        self::assertIsString(
            $this->service
        );
    }

    public function test_admin_exposes_read_start_and_replace_mapping_routes(): void
    {
        foreach (
            [
                '/mapping-workspace',
                '/mappings',
                '/mappings/{mappingId}/fields',
                "'sourceAssetMappingWorkspace'",
                "'startSourceAssetMapping'",
                "'replaceSourceAssetMappingFields'",
                'source_assets.mapping.workspace',
                'source_assets.mappings.start',
                'source_assets.mappings.fields.replace',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->routes
            );
        }

        self::assertStringContainsString(
            '\\Illuminate\\Support\\Facades\\Route::get(',
            $this->routes
        );

        self::assertStringContainsString(
            '\\Illuminate\\Support\\Facades\\Route::post(',
            $this->routes
        );

        self::assertStringContainsString(
            '\\Illuminate\\Support\\Facades\\Route::put(',
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('mappingId')",
            $this->routes
        );
    }

    public function test_mapping_workspace_get_is_explicitly_read_only(): void
    {
        $controllerAction =
            $this->methodBlock(
                $this->controller,
                'public function sourceAssetMappingWorkspace(',
                'public function startSourceAssetMapping('
            );

        foreach (
            [
                '$this->actor(',
                '$this->assertRequest(',
                '$this->scopedSession(',
                '$this->scopedSourceAsset(',
                '$service->workspace(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $controllerAction
            );
        }

        self::assertStringNotContainsString(
            'startDraft(',
            $controllerAction
        );

        self::assertStringNotContainsString(
            'replaceFieldMappings(',
            $controllerAction
        );

        $workspace =
            $this->methodBlock(
                $this->service,
                'public function workspace(',
                'public function startDraft('
            );

        foreach (
            [
                'DB::transaction(',
                '->save(',
                '->create(',
                'startDraft(',
                'replaceFieldMappings(',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $workspace
            );
        }
    }

    public function test_workspace_returns_registry_profile_structure_and_existing_mappings_only(): void
    {
        $workspace =
            $this->methodBlock(
                $this->service,
                'public function workspace(',
                'public function startDraft('
            );

        foreach (
            [
                "'canonical_registry'",
                "'entities'",
                "'relationships'",
                "'source'",
                "'sheets'",
                "'columns'",
                "'mappings'",
                'currentProfileContext(',
                'DataTransformationBiSourceAssetMapping::query()',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $workspace
            );
        }

        foreach (
            [
                "'source_path'",
                "'source_disk'",
                "'source_sha256'",
                "'profiling_snapshot'",
                "'contains_raw_values'",
                "'contains_sample_values'",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $workspace
            );
        }
    }

    public function test_start_mapping_requires_explicit_entity_and_sheet(): void
    {
        $action =
            $this->methodBlock(
                $this->controller,
                'public function startSourceAssetMapping(',
                'public function replaceSourceAssetMappingFields('
            );

        foreach (
            [
                '$this->actor(',
                '$this->assertRequest(',
                '$this->scopedSession(',
                '$this->scopedSourceAsset(',
                "'canonical_entity_key'",
                "'source_sheet_index'",
                '$service->startDraft(',
                '$service->workspace(',
                "'mapping_id'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $action
            );
        }
    }

    public function test_replace_fields_is_scoped_to_mapping_and_uses_controlled_decisions(): void
    {
        $action =
            $this->methodBlock(
                $this->controller,
                'public function replaceSourceAssetMappingFields(',
                'public function updateSourceAssetStructure('
            );

        foreach (
            [
                '$this->actor(',
                '$this->assertRequest(',
                '$this->scopedSession(',
                '$this->scopedSourceAsset(',
                '$this->scopedSourceAssetMapping(',
                "'decisions'",
                "'decisions.*.canonical_field_key'",
                "'decisions.*.mapping_type'",
                'in:direct,default,transform,unmapped',
                "'decisions.*.source_column_key'",
                "'decisions.*.default_value'",
                "'decisions.*.transformation_key'",
                "'decisions.*.configuration_snapshot'",
                '$service->replaceFieldMappings(',
                '$service->workspace(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $action
            );
        }
    }

    public function test_mapping_scope_is_request_session_source_and_company_bound(): void
    {
        $helper =
            $this->methodBlock(
                $this->controller,
                'private function scopedSourceAssetMapping(',
                '/**'."\n"
                .'     * Safe HTTP representation of one dynamic source.'
            );

        foreach (
            [
                'DataTransformationBiSourceAssetMapping::query()',
                "'company_id'",
                "'data_transformation_bi_intake_session_id'",
                "'data_transformation_bi_source_asset_id'",
                'abort_unless(',
                '404',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $helper
            );
        }
    }

    public function test_new_mapping_http_actions_do_not_use_legacy_domain_contract(): void
    {
        $start =
            strpos(
                $this->controller,
                'public function sourceAssetMappingWorkspace('
            );

        $end =
            strpos(
                $this->controller,
                'public function updateSourceAssetStructure(',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $actions =
            substr(
                $this->controller,
                $start,
                $end - $start
            );

        foreach (
            [
                'domain_key',
                'DataTransformationBiSourceDomainRegistry',
                'DataTransformationBiIntakeDomainDelivery',
                'DataTransformationBiSourceDomainFile',
                'DataTransformationBiStandardIntakeSchema',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $actions
            );
        }
    }

    private function methodBlock(
        string $source,
        string $startNeedle,
        string $endNeedle
    ): string {
        $start =
            strpos(
                $source,
                $startNeedle
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $source,
                $endNeedle,
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $source,
            $start,
            $end - $start
        );
    }
}
