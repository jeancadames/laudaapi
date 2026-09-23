<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiSourceAssetMapping;
use App\Services\Diagnosis\DataTransformationBiCanonicalRegistry;
use App\Services\Diagnosis\DataTransformationBiSourceAssetMappingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiCanonicalRegistryContractTest
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

    public function test_registry_v1_exposes_current_base_entities_without_seven_entity_gate(): void
    {
        $registry =
            new DataTransformationBiCanonicalRegistry();

        self::assertSame(
            1,
            $registry->version()
        );

        $keys =
            $registry->entityKeys();

        foreach (
            [
                'customers',
                'products',
                'inventory',
                'sales',
                'accounts_receivable',
                'suppliers',
                'accounts_payable',
            ]
            as $required
        ) {
            self::assertContains(
                $required,
                $keys
            );
        }

        /*
         * Deliberately do not assert count($keys) === 7.
         *
         * The new registry may grow independently from the legacy
         * Standard Intake source/domain workflow.
         */
    }

    public function test_registry_exposes_normalized_entity_and_field_metadata(): void
    {
        $registry =
            new DataTransformationBiCanonicalRegistry();

        $customers =
            $registry->entity(
                'customers'
            );

        self::assertSame(
            'Clientes',
            $customers['label']
        );

        self::assertSame(
            'standard_intake_schema_v1',
            $customers['source']
        );

        self::assertTrue(
            $registry->supportsField(
                'customers',
                'customer_id'
            )
        );

        $field =
            $registry->field(
                'customers',
                'customer_id'
            );

        self::assertSame(
            'customer_id',
            $field['key']
        );

        self::assertTrue(
            $field['required']
        );

        self::assertSame(
            'text',
            $field['type']
        );

        self::assertNotSame(
            '',
            $field['description']
        );
    }

    public function test_registry_exposes_canonical_identity_and_relationship_metadata(): void
    {
        $registry =
            new DataTransformationBiCanonicalRegistry();

        self::assertSame(
            [
                'customer_id',
            ],
            $registry->identityKeys(
                'customers'
            )
        );

        $relationships =
            $registry->relationships();

        self::assertContains(
            [
                'from_entity_key' =>
                    'sales',

                'from_field_key' =>
                    'customer_id',

                'to_entity_key' =>
                    'customers',

                'to_field_key' =>
                    'customer_id',

                'source' =>
                    'standard_intake_schema_v1',
            ],
            $relationships
        );
    }

    public function test_registry_is_code_versioned_and_has_explicit_extension_points(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiCanonicalRegistry::class
            );

        self::assertStringContainsString(
            'public const VERSION = 1',
            $source
        );

        self::assertStringContainsString(
            'EXTENSION_ENTITIES',
            $source
        );

        self::assertStringContainsString(
            'EXTENSION_RELATIONSHIPS',
            $source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $source
        );

        self::assertStringNotContainsString(
            'DB::',
            $source
        );
    }

    public function test_mapping_service_depends_on_registry_not_directly_on_legacy_schema(): void
    {
        $source =
            $this->sourceFor(
                DataTransformationBiSourceAssetMappingService::class
            );

        self::assertStringContainsString(
            'DataTransformationBiCanonicalRegistry',
            $source
        );

        self::assertStringContainsString(
            'supportsEntity',
            $source
        );

        self::assertStringContainsString(
            'supportsField',
            $source
        );

        self::assertStringContainsString(
            'canonical_registry_version',
            $source
        );

        self::assertStringContainsString(
            'assertCurrentCanonicalRegistry',
            $source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiStandardIntakeSchema',
            $source
        );

        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $source
        );
    }

    public function test_mapping_model_persists_registry_version(): void
    {
        $mapping =
            new DataTransformationBiSourceAssetMapping();

        self::assertContains(
            'canonical_registry_version',
            $mapping->getFillable()
        );

        self::assertSame(
            'integer',
            $mapping->getCasts()[
                'canonical_registry_version'
            ]
        );
    }

    public function test_additive_migration_pins_existing_mapping_table_to_registry_version(): void
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
                .'2026_09_23_112000_add_canonical_registry_version_to_data_transformation_bi_source_asset_mappings.php'
            );

        self::assertIsString(
            $migration
        );

        self::assertStringContainsString(
            'canonical_registry_version',
            $migration
        );

        self::assertStringContainsString(
            'data_transformation_bi_source_asset_mappings',
            $migration
        );

        self::assertStringContainsString(
            'dtbi_sa_map_asset_registry_status_idx',
            $migration
        );

        self::assertStringNotContainsString(
            'data_transformation_bi_source_domain_files',
            $migration
        );

        self::assertStringNotContainsString(
            "'domain_key'",
            $migration
        );
    }
}
