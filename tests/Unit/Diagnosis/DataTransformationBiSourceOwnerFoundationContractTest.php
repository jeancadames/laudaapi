<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceOwnerFoundationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_owner_is_persisted_on_dynamic_source_asset(): void
    {
        $root = $this->root();

        $migration = file_get_contents(
            $root
            .'/database/migrations/'
            .'2026_09_21_003000_add_owner_to_data_transformation_bi_source_assets.php'
        );

        $model = file_get_contents(
            $root
            .'/app/Models/DataTransformationBiSourceAsset.php'
        );

        $service = file_get_contents(
            $root
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiSourceAssetService.php'
        );

        self::assertStringContainsString(
            "'owner'",
            $migration
        );

        self::assertStringContainsString(
            "'owner'",
            $model
        );

        self::assertStringContainsString(
            'MAX_OWNER_LENGTH',
            $service
        );

        self::assertStringContainsString(
            "\$payload['owner']",
            $service
        );
    }

    public function test_owner_is_exposed_by_state_projection(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiIntakeV2StateService.php'
        );

        self::assertStringContainsString(
            "'owner'",
            $source
        );

        self::assertStringContainsString(
            "'owner' =>",
            $source
        );

        self::assertStringContainsString(
            '$asset->owner',
            $source
        );
    }

    public function test_admin_and_tenant_accept_owner_as_source_metadata(): void
    {
        $root = $this->root();

        $admin = file_get_contents(
            $root
            .'/app/Http/Controllers/Admin/'
            .'AdminDataTransformationBiIntakeV2Controller.php'
        );

        $tenant = file_get_contents(
            $root
            .'/app/Http/Controllers/'
            .'AppHubDataTransformationBiSourceWorkspaceController.php'
        );

        self::assertGreaterThanOrEqual(
            2,
            substr_count(
                $admin,
                "'owner' => ["
            )
        );

        self::assertGreaterThanOrEqual(
            2,
            substr_count(
                $tenant,
                "'owner',"
            )
        );
    }

    public function test_owner_is_not_a_canonical_domain_or_credential(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_09_21_003000_add_owner_to_data_transformation_bi_source_assets.php'
        );

        self::assertStringNotContainsString(
            'canonical_domain',
            $migration
        );

        self::assertStringNotContainsString(
            'password',
            strtolower($migration)
        );

        self::assertStringNotContainsString(
            'connection_string',
            strtolower($migration)
        );
    }
}
