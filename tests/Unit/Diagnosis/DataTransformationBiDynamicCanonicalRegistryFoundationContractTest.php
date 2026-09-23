<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDynamicCanonicalRegistryFoundationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_registry_is_persisted_and_company_versioned(): void
    {
        $migration =
            file_get_contents(
                $this->root()
                .'/database/migrations/'
                .'2026_09_23_223000_create_dynamic_data_transformation_bi_canonical_registry.php'
            );

        self::assertIsString(
            $migration
        );

        foreach (
            [
                'data_transformation_bi_canonical_registry_versions',
                'data_transformation_bi_canonical_entities',
                'data_transformation_bi_canonical_fields',
                'data_transformation_bi_canonical_relationships',
                "'company_id'",
                "'version'",
                "'entity_key'",
                "'field_key'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $migration
            );
        }
    }

    public function test_registry_has_no_fixed_seven_entity_seed(): void
    {
        $migration =
            file_get_contents(
                $this->root()
                .'/database/migrations/'
                .'2026_09_23_223000_create_dynamic_data_transformation_bi_canonical_registry.php'
            );

        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $migration
        );

        self::assertIsString(
            $service
        );

        foreach (
            [
                'DataTransformationBiStandardIntakeSchema',
                'domain_count',
                'STANDARD_INTAKE_BASE_VERSION',
                'EXTENSION_ENTITIES',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $migration
            );

            self::assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }

    public function test_first_registry_version_can_start_empty(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $service
        );

        self::assertStringContainsString(
            'public function prepareDraft(',
            $service
        );

        self::assertStringNotContainsString(
            'customers',
            $service
        );

        self::assertStringNotContainsString(
            'products',
            $service
        );

        self::assertStringNotContainsString(
            'accounts_receivable',
            $service
        );
    }

    public function test_entity_and_field_keys_are_dynamic(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $service
        );

        self::assertStringContainsString(
            'public function createEntity(',
            $service
        );

        self::assertStringContainsString(
            'public function replaceFields(',
            $service
        );

        self::assertStringContainsString(
            "'entity_key'",
            $service
        );

        self::assertStringContainsString(
            "'field_key'",
            $service
        );
    }

    public function test_registry_supports_dynamic_relationships(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $service
        );

        self::assertStringContainsString(
            'public function replaceRelationships(',
            $service
        );

        foreach (
            [
                'from_entity_key',
                'from_field_key',
                'to_entity_key',
                'to_field_key',
                'relationship_type',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_published_versions_are_immutable_snapshots(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $service
        );

        foreach (
            [
                'STATUS_DRAFT',
                'STATUS_PUBLISHED',
                'STATUS_RETIRED',
                'clonePublishedIntoDraft',
                'public function publish(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_read_workspace_does_not_prepare_a_registry(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $service
        );

        $start =
            strpos(
                $service,
                'public function workspace('
            );

        $end =
            strpos(
                $service,
                'public function prepareDraft(',
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
                $service,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            '->create(',
            $method
        );

        self::assertStringNotContainsString(
            'prepareDraft(',
            $method
        );
    }

    public function test_canonical_model_is_admin_lauda_owned(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiCanonicalModelService.php'
            );

        self::assertIsString(
            $service
        );

        self::assertStringContainsString(
            'assertCanManage',
            $service
        );

        self::assertStringContainsString(
            "(string) \$actor->role !== 'admin'",
            $service
        );
    }
}
