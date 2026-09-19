<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceServiceAuthorizationContractTest
    extends TestCase
{
    /**
     * @return array<string,string>
     */
    private function services(): array
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        return [
            'session' =>
                file_get_contents(
                    $root
                    .'/app/Services/Diagnosis/'
                    .'DataTransformationBiIntakeV2SessionService.php'
                ),

            'source' =>
                file_get_contents(
                    $root
                    .'/app/Services/Diagnosis/'
                    .'DataTransformationBiSourceAssetService.php'
                ),

            'structure' =>
                file_get_contents(
                    $root
                    .'/app/Services/Diagnosis/'
                    .'DataTransformationBiSourceAssetStructureService.php'
                ),

            'upload' =>
                file_get_contents(
                    $root
                    .'/app/Services/Diagnosis/'
                    .'DataTransformationBiSourceAssetDataUploadService.php'
                ),
        ];
    }

    public function test_all_target_services_use_shared_actor_authorization(): void
    {
        foreach (
            $this->services()
            as $name => $source
        ) {
            self::assertIsString(
                $source,
                $name
            );

            self::assertStringContainsString(
                'private function assertCanManage(',
                $source,
                $name
            );

            self::assertStringContainsString(
                'DataTransformationBiIntakeActorAuthorizationService::class',
                $source,
                $name
            );

            self::assertStringContainsString(
                '->assertCanManage(',
                $source,
                $name
            );

            self::assertStringNotContainsString(
                'private function assertAdmin(',
                $source,
                $name
            );

            self::assertStringNotContainsString(
                '$this->assertAdmin(',
                $source,
                $name
            );
        }
    }

    public function test_domain_services_keep_request_and_session_guards(): void
    {
        $services =
            $this->services();

        foreach (
            [
                'source',
                'structure',
                'upload',
            ]
            as $name
        ) {
            self::assertStringContainsString(
                'assertRequestAndSession',
                $services[$name],
                $name
            );

            self::assertStringContainsString(
                'assertEditableSession',
                $services[$name],
                $name
            );

            self::assertStringContainsString(
                'data_transformation_bi_intake_session_id',
                $services[$name],
                $name
            );

            self::assertStringContainsString(
                'company_id',
                $services[$name],
                $name
            );
        }
    }

    public function test_asset_mutation_services_keep_asset_scope_and_archive_guards(): void
    {
        $services =
            $this->services();

        foreach (
            [
                'source',
                'structure',
                'upload',
            ]
            as $name
        ) {
            self::assertStringContainsString(
                'assertAssetBelongsToSession',
                $services[$name],
                $name
            );
        }

        foreach (
            [
                'source',
                'structure',
                'upload',
            ]
            as $name
        ) {
            self::assertStringContainsString(
                'STATUS_ARCHIVED',
                $services[$name],
                $name
            );
        }
    }

    public function test_session_service_keeps_locking_and_reuse_contract(): void
    {
        $session =
            $this->services()['session'];

        foreach (
            [
                'DB::transaction(',
                'lockForUpdate()',
                'STATUS_DRAFT',
                'STATUS_READY',
                'STATUS_FINALIZING',
                'ensureCanonicalSlots(',
                'latestDefinitionFor(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $session
            );
        }
    }

    public function test_services_do_not_duplicate_tenant_resolution_logic(): void
    {
        foreach (
            $this->services()
            as $name => $source
        ) {
            self::assertStringNotContainsString(
                'SubscriberResolver',
                $source,
                $name
            );

            self::assertStringNotContainsString(
                'CompanyContextResolver',
                $source,
                $name
            );

            self::assertStringNotContainsString(
                'TenantAccessService::SUBSCRIBER_ADMIN',
                $source,
                $name
            );
        }
    }
}
