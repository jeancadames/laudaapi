<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicSourceAssetStateContractTest
    extends TestCase
{
    private string $source;
    private string $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $path =
            $root
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiIntakeV2StateService.php';

        $this->source =
            file_get_contents(
                $path
            );

        self::assertIsString(
            $this->source
        );

        $start =
            strpos(
                $this->source,
                'private function sourceAssetsPayload('
            );

        self::assertNotFalse(
            $start
        );

        $this->helper =
            substr(
                $this->source,
                $start
            );
    }

    public function test_state_exposes_dynamic_source_assets_at_root(): void
    {
        self::assertStringContainsString(
            '$sourceAssets =',
            $this->source
        );

        self::assertStringContainsString(
            "'source_assets' =>",
            $this->source
        );

        self::assertStringContainsString(
            'sourceAssetsPayload(',
            $this->source
        );
    }

    public function test_projection_is_safe_before_migration_runs(): void
    {
        self::assertStringContainsString(
            'Schema::hasTable(',
            $this->helper
        );

        self::assertStringContainsString(
            "'data_transformation_bi_source_assets'",
            $this->helper
        );

        self::assertStringContainsString(
            'return [];',
            $this->helper
        );
    }

    public function test_projection_is_scoped_to_session_and_company(): void
    {
        self::assertStringContainsString(
            "'data_transformation_bi_intake_session_id'",
            $this->helper
        );

        self::assertStringContainsString(
            "'company_id'",
            $this->helper
        );

        self::assertStringContainsString(
            'session->getKey()',
            $this->helper
        );

        self::assertStringContainsString(
            'session->company_id',
            $this->helper
        );
    }

    public function test_archived_sources_are_excluded_from_active_workspace(): void
    {
        self::assertStringContainsString(
            "->whereNull(\n                    'archived_at'",
            $this->helper
        );
    }

    public function test_sources_are_ordered_for_horizontal_workspace(): void
    {
        self::assertStringContainsString(
            "->orderBy(\n                    'sort_order'",
            $this->helper
        );

        self::assertStringContainsString(
            "->orderBy(\n                    'id'",
            $this->helper
        );
    }

    public function test_projection_contains_business_and_progress_metadata(): void
    {
        foreach (
            [
                "'display_name'",
                "'source_object_name'",
                "'description'",
                "'origin_system'",
                "'delivery_format'",
                "'structure_status'",
                "'data_status'",
                "'structure_snapshot'",
                "'profiling_snapshot'",
                "'sort_order'",
            ]
            as $token
        ) {
            self::assertStringContainsString(
                $token,
                $this->helper
            );
        }
    }

    public function test_dynamic_projection_has_no_canonical_domain_dependency(): void
    {
        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->helper
        );

        self::assertStringNotContainsString(
            'DataTransformationBiIntakeDomainDelivery',
            $this->helper
        );

        self::assertStringNotContainsString(
            "'domain_key'",
            $this->helper
        );
    }

    public function test_projection_exposes_no_private_path_or_credentials(): void
    {
        $lower =
            strtolower(
                $this->helper
            );

        self::assertStringNotContainsString(
            'source_path',
            $lower
        );

        self::assertStringNotContainsString(
            'password',
            $lower
        );

        self::assertStringNotContainsString(
            'connection_string',
            $lower
        );

        self::assertStringNotContainsString(
            'access_token',
            $lower
        );
    }

    public function test_projection_is_read_only(): void
    {
        self::assertStringNotContainsString(
            '->save(',
            $this->helper
        );

        self::assertStringNotContainsString(
            '->create(',
            $this->helper
        );

        self::assertStringNotContainsString(
            '->update(',
            $this->helper
        );

        self::assertStringNotContainsString(
            '->delete(',
            $this->helper
        );
    }
}
