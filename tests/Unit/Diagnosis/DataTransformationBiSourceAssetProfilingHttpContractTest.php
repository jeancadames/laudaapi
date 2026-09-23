<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetProfilingHttpContractTest
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
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_admin_exposes_source_asset_profile_route(): void
    {
        self::assertStringContainsString(
            '/source-assets/{sourceAssetId}/profile',
            $this->routes
        );

        self::assertStringContainsString(
            "'profileSourceAsset'",
            $this->routes
        );

        self::assertStringContainsString(
            'source_assets.profile',
            $this->routes
        );

        self::assertStringContainsString(
            '/source-assets/{sourceAssetId}/profile-status',
            $this->routes
        );

        self::assertStringContainsString(
            "'profileSourceAssetStatus'",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sessionId')",
            $this->routes
        );

        self::assertStringContainsString(
            "->whereNumber('sourceAssetId')",
            $this->routes
        );
    }

    public function test_profile_action_is_request_session_and_source_scoped(): void
    {
        $action =
            $this->profileAction();

        foreach (
            [
                '$this->actor(',
                '$this->assertRequest(',
                '$this->scopedSession(',
                '$this->scopedSourceAsset(',
                'DataTransformationBiSourceAssetProfilingDispatchService $service',
                '$service->dispatch(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $action
            );
        }
    }

    public function test_profile_action_returns_only_safe_source_payload(): void
    {
        $action =
            $this->profileAction();

        self::assertStringContainsString(
            "'source_asset' =>",
            $action
        );

        self::assertStringContainsString(
            '$this->sourceAssetPayload(',
            $action
        );

        self::assertStringNotContainsString(
            "'source_path'",
            $action
        );

        self::assertStringNotContainsString(
            "'source_disk'",
            $action
        );
    }

    public function test_profile_action_has_no_fixed_domain_dependency(): void
    {
        $action =
            $this->profileAction();

        foreach (
            [
                'domain_key',
                'DataTransformationBiStandardIntakeSchema',
                'DataTransformationBiIntakeDomainDelivery',
                'DataTransformationBiSourceDomainFile',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $action
            );
        }
    }

    private function profileAction(): string
    {
        $start =
            strpos(
                $this->controller,
                'public function profileSourceAsset('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->controller,
                'public function updateSourceAssetStructure(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        return substr(
            $this->controller,
            $start,
            $end - $start
        );
    }
}
