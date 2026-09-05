<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantDefinitionChangesHttpUiContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    private function changesRouteBlock(): string
    {
        $routes =
            $this->project(
                'routes/web.php'
            );

        $uri =
            '/app/transformacion-360/datos-bi/definition/solicitar-cambios';

        $uriPosition =
            strpos(
                $routes,
                $uri
            );

        $this->assertNotFalse(
            $uriPosition
        );

        $before =
            substr(
                $routes,
                0,
                $uriPosition
            );

        $start =
            strrpos(
                $before,
                "Route::middleware(['auth', 'verified'])"
            );

        $this->assertNotFalse(
            $start
        );

        $namePosition =
            strpos(
                $routes,
                'app.transformation.data_bi.definition.request_changes',
                $uriPosition
            );

        $this->assertNotFalse(
            $namePosition
        );

        $end =
            strpos(
                $routes,
                ';',
                $namePosition
            );

        $this->assertNotFalse(
            $end
        );

        return substr(
            $routes,
            $start,
            $end - $start + 1
        );
    }

    public function test_route_has_auth_and_verified_middleware(): void
    {
        $route =
            $this->changesRouteBlock();

        foreach ([
            "Route::middleware(['auth', 'verified'])",
            '/app/transformacion-360/datos-bi/definition/solicitar-cambios',
            'AppHubDataTransformationBiDefinitionReviewController',
            "'requestChanges'",
            'app.transformation.data_bi.definition.request_changes',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $route
            );
        }
    }

    public function test_action_is_tenant_admin_and_company_scoped(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiDefinitionReviewController.php'
            );

        foreach ([
            '$user->role',
            "'subscriber'",
            'SubscriberResolver',
            'CompanyContextResolver',
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            '$company->id',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_browser_only_supplies_reason(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiDefinitionReviewController.php'
            );

        foreach ([
            "'reason' =>",
            "'required'",
            "'string'",
            "'min:10'",
            "'max:4000'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }

        foreach ([
            "'implementation_request_id' =>",
            "'definition_id' =>",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }

    public function test_request_and_definition_are_resolved_server_side(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiDefinitionReviewController.php'
            );

        foreach ([
            'TransformationImplementationRequest::query()',
            'TransformationImplementationDefinition::query()',
            'STATUS_AWAITING_TENANT_REVIEW',
            "'company_id'",
            "'data_transformation_bi'",
            "'version'",
            '->requestChanges(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_read_model_exposes_endpoint_only_during_review(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        foreach ([
            "'changes_request_endpoint' => null",
            "'changes_request_endpoint' =>",
            'STATUS_AWAITING_TENANT_REVIEW',
            'app.transformation.data_bi.definition.request_changes',
            'tenantDefinitionReview(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_ui_has_explicit_changes_form(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'changes_request_endpoint',
            'changesRequestForm',
            'canRequestDefinitionChanges',
            '¿Qué debemos ajustar?',
            'Mínimo 10 caracteres.',
            'Solicitar cambios',
            'requestDefinitionChanges',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_ui_explains_version_preservation_and_boundary(): void
    {
        $ui =
            $this->project(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'conservará esta versión',
            'nueva versión',
            'no modifica esta versión',
            'no inicia contratación',
            'facturación',
            'activación ni ejecución',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_http_layer_does_not_create_v2_or_agreement(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiDefinitionReviewController.php'
            );

        foreach ([
            'createRevision(',
            'createNewVersion(',
            'STATUS_DEFINITION_AGREED',
            '->markReady(',
            'STATUS_READY_FOR_COMMERCIAL',
            'TransformationImplementationExecutionService',
            'TransformationImplementationPricingService',
            'TransformationCapabilityActivationService',
            'Subscription::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }
}
