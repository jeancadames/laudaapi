<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantSourceAuthorizationFoundationTest
    extends TestCase
{
    private string $authorizer;

    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->authorizer =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeActorAuthorizationService.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/web.php'
            );

        self::assertIsString(
            $this->authorizer
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_authorizer_preserves_lauda_admin_path(): void
    {
        self::assertStringContainsString(
            "=== 'admin'",
            $this->authorizer
        );
    }

    public function test_tenant_path_requires_exact_subscriber_admin(): void
    {
        foreach (
            [
                "!== 'subscriber'",
                'SubscriberResolver',
                'CompanyContextResolver',
                'TenantAccessService',
                'TenantAccessService::SUBSCRIBER_ADMIN',
                "'tenant_admin'",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->authorizer
            );
        }
    }

    public function test_tenant_path_is_exact_company_and_request_scoped(): void
    {
        foreach (
            [
                "'data_transformation_bi'",
                'implementationRequest->company_id',
                'company->getKey()',
                'company->subscriber_id',
                '$subscriberId',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->authorizer
            );
        }
    }

    public function test_authorizer_does_not_accept_browser_identity_context(): void
    {
        foreach (
            [
                "\$request->input('company_id'",
                "\$request->input('subscriber_id'",
                "\$request->input('implementation_request_id'",
                "\$request->input('tenant_admin'",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->authorizer
            );
        }
    }

    public function test_definition_agreement_route_is_auth_and_verified(): void
    {
        $pattern =
            "~Route::middleware\\(\\['auth', 'verified'\\]\\)"
            ."\\s*->group\\(function \\(\\): void \\{"
            ."\\s*Route::post\\("
            ."\\s*'/app/transformacion-360/datos-bi/definition/acordar'~s";

        self::assertSame(
            1,
            preg_match(
                $pattern,
                $this->routes
            )
        );
    }

    public function test_definition_agreement_keeps_server_resolved_uri_contract(): void
    {
        $uri =
            '/app/transformacion-360/datos-bi/definition/acordar';

        $position =
            strpos(
                $this->routes,
                $uri
            );

        self::assertNotFalse(
            $position
        );

        $window =
            substr(
                $this->routes,
                max(
                    0,
                    $position - 1200
                ),
                1800
            );

        foreach (
            [
                $uri,
                'AppHubDataTransformationBiDefinitionReviewController::class',
                "'agree'",
                'app.transformation.data_bi.definition.agree',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $window
            );
        }

        foreach (
            [
                '{implementationRequest}',
                '{definition}',
                '{company}',
                '{subscriber}',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $window
            );
        }
    }
}
