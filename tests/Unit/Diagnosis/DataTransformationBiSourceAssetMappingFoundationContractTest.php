<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiSourceAssetFieldMapping;
use App\Models\DataTransformationBiSourceAssetMapping;
use App\Services\Diagnosis\DataTransformationBiSourceAssetMappingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiSourceAssetMappingFoundationContractTest
    extends TestCase
{
    private function sourceFor(
        string $class
    ): string {
        $reflection =
            new ReflectionClass(
                $class
            );

        $path =
            $reflection->getFileName();

        self::assertIsString(
            $path
        );

        $source =
            file_get_contents(
                $path
            );

        self::assertIsString(
            $source
        );

        return $source;
    }

    public function test_mapping_models_use_new_source_asset_tables(): void
    {
        self::assertSame(
            'data_transformation_bi_source_asset_mappings',
            (new DataTransformationBiSourceAssetMapping())
                ->getTable()
        );

        self::assertSame(
            'data_transformation_bi_source_asset_field_mappings',
            (new DataTransformationBiSourceAssetFieldMapping())
                ->getTable()
        );
    }

    public function test_mapping_supports_expected_lifecycle(): void
    {
        self::assertSame(
            'draft',
            DataTransformationBiSourceAssetMapping::STATUS_DRAFT
        );

        self::assertSame(
            'ready',
            DataTransformationBiSourceAssetMapping::STATUS_READY
        );

        self::assertSame(
            'validated',
            DataTransformationBiSourceAssetMapping::STATUS_VALIDATED
        );

        self::assertSame(
            'blocked',
            DataTransformationBiSourceAssetMapping::STATUS_BLOCKED
        );

        self::assertSame(
            'stale',
            DataTransformationBiSourceAssetMapping::STATUS_STALE
        );
    }

    public function test_field_mapping_reuses_only_controlled_mapping_vocabulary(): void
    {
        self::assertSame(
            'direct',
            DataTransformationBiSourceAssetFieldMapping::TYPE_DIRECT
        );

        self::assertSame(
            'default',
            DataTransformationBiSourceAssetFieldMapping::TYPE_DEFAULT
        );

        self::assertSame(
            'transform',
            DataTransformationBiSourceAssetFieldMapping::TYPE_TRANSFORM
        );

        self::assertSame(
            'unmapped',
            DataTransformationBiSourceAssetFieldMapping::TYPE_UNMAPPED
        );
    }

    public function test_service_requires_completed_profile_and_current_artifact(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        foreach (
            [
                '::DATA_ANALYZED',
                '::PROFILING_COMPLETED',
                'profiling_snapshot',
                "'source_file_id'",
                "'source_sha256'",
                'hash_equals',
                '::STATUS_UPLOADED',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_mapping_is_sheet_aware_and_source_column_aware(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        foreach (
            [
                "'source_sheet_index'",
                "'source_sheet_name'",
                "'source_column_key'",
                "'source_column_index'",
                "'source_header'",
                'columnsByKey',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_mapping_is_owned_by_admin_lauda(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        self::assertStringContainsString(
            'assertCanManage',
            $source
        );

        self::assertStringContainsString(
            'El mapeo canónico de fuentes corresponde a Admin LAUDA.',
            $source
        );
    }

    public function test_new_mapping_layer_has_no_legacy_domain_dependency(): void
    {
        $files = [
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            ),
            $this->sourceFor(
                DataTransformationBiSourceAssetMapping::class
            ),
            $this->sourceFor(
                DataTransformationBiSourceAssetFieldMapping::class
            ),
        ];

        foreach ($files as $source) {
            foreach (
                [
                    'domain_key',
                    'DataTransformationBiSourceDomainFile',
                    'DataTransformationBiIntakeDomainDelivery',
                    'DataTransformationBiStandardIntakeSchema',
                    'DataTransformationBiSourceDomainRegistry',
                ]
                as $forbidden
            ) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source
                );
            }
        }
    }

    public function test_mapping_layer_does_not_normalize_or_materialize_data(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        foreach (
            [
                'DataTransformationBiNormalizedRow',
                'DataTransformationBiIntakeRow',
                'DataTransformationBiProcessingRun',
                'StagingMaterialization',
                'CanonicalNormalizer',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_migration_is_source_asset_centric(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_23_104500_create_data_transformation_bi_source_asset_mapping_tables.php'
            );

        self::assertIsString(
            $migration
        );

        foreach (
            [
                'data_transformation_bi_source_asset_mappings',
                'data_transformation_bi_source_asset_field_mappings',
                "'canonical_entity_key'",
                "'canonical_field_key'",
                "'source_sha256'",
                "'source_sheet_index'",
                "'mapping_version'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $migration
            );
        }

        foreach (
            [
                "'domain_key'",
                'data_transformation_bi_source_domain_files',
                'data_transformation_bi_intake_domain_deliveries',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $migration
            );
        }
    }

    public function test_same_artifact_can_have_multiple_mapping_versions(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $migration =
            file_get_contents(
                $root
                .'/database/migrations/'
                .'2026_09_23_104500_create_data_transformation_bi_source_asset_mapping_tables.php'
            );

        self::assertIsString(
            $migration
        );

        self::assertStringContainsString(
            'dtbi_sa_map_entity_sheet_sha_idx',
            $migration
        );

        self::assertStringNotContainsString(
            'dtbi_sa_map_entity_sheet_sha_uq',
            $migration
        );

        self::assertStringContainsString(
            'dtbi_sa_map_entity_sheet_ver_uq',
            $migration
        );
    }

    public function test_start_draft_reuses_editable_mapping_but_versions_validated_mapping(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        self::assertStringContainsString(
            "->orderByDesc(\n                            'mapping_version'",
            $source
        );

        foreach (
            [
                '::STATUS_DRAFT',
                '::STATUS_READY',
                '::STATUS_BLOCKED',
            ]
            as $editable
        ) {
            self::assertStringContainsString(
                $editable,
                $source
            );
        }

        self::assertStringContainsString(
            "'mapping_version'",
            $source
        );

        self::assertStringContainsString(
            "->max(\n                                    'mapping_version'",
            $source
        );
    }


    public function test_uploading_new_artifact_stales_old_sha_mappings(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $upload =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceAssetDataUploadService.php'
            );

        self::assertIsString(
            $upload
        );

        foreach (
            [
                'DataTransformationBiSourceAssetMapping::query()',
                "'source_sha256'",
                "'<>'",
                '::STATUS_STALE',
                "'validated_at'",
                "'validated_by_user_id'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $upload
            );
        }
    }

    public function test_field_replacement_revalidates_current_artifact_under_lock(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        $start =
            strpos(
                $source,
                'public function replaceFieldMappings('
            );

        $end =
            strpos(
                $source,
                'private function assertAdminScope(',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $method =
            substr(
                $source,
                $start,
                $end - $start
            );

        foreach (
            [
                '$lockedAsset',
                'DataTransformationBiSourceAsset::query()',
                'lockForUpdate()',
                '$lockedContext',
                '$lockedArtifact',
                '$this->currentProfileContext(',
                'hash_equals',
                'data_transformation_bi_source_asset_file_id',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $method
            );
        }

        self::assertStringContainsString(
            'true',
            $method
        );
    }


    public function test_mapping_is_pinned_to_profile_version_as_well_as_artifact(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        self::assertStringContainsString(
            'source_profile_version',
            $source
        );

        self::assertStringContainsString(
            '$context[\'profile\'][\'version\']',
            $source
        );

        self::assertStringContainsString(
            '$lockedContext[\'profile\'][\'version\']',
            $source
        );
    }

}
