<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class TransformationImplementationCommercialHttpBoundaryContractTest
    extends TestCase
{
    private function adminController(): string
    {
        return file_get_contents(
            base_path(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationCommercialEngagementController.php'
            )
        );
    }

    private function tenantController(): string
    {
        return file_get_contents(
            base_path(
                'app/Http/Controllers/'
                .'AppHubDataTransformationBiCommercialEngagementController.php'
            )
        );
    }

    public function test_admin_boundary_exposes_exact_modern_lifecycle(): void
    {
        $source =
            $this->adminController();

        foreach (
            [
                'TransformationImplementationCommercialEngagementService',
                'TransformationImplementationAuthorizationService',
                'public function store(',
                'public function present(',
                'public function authorizeImplementation(',
                '->createDraft(',
                '->present(',
                '->authorize(',
                "'currency'",
                "'price_amount'",
                "'duration_days'",
                "'commercial_terms_snapshot'",
                "'data_transformation_bi'",
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_boundary_does_not_start_historical_execution(): void
    {
        $source =
            $this->adminController();

        foreach (
            [
                'TransformationImplementationExecutionService',
                'acceptPlan(',
                'DataTransformationBiCanonicalModelService',
                'ready_for_execution',
                'execution_started',
                'Subscription::',
                'Invoice::',
                'Payment::',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_tenant_acceptance_resolves_context_server_side(): void
    {
        $source =
            $this->tenantController();

        foreach (
            [
                'SubscriberResolver',
                'CompanyContextResolver',
                'TenantAccessService',
                'TenantAccessService::SUBSCRIBER_ADMIN',
                "'tenant_admin'",
                "'data_transformation_bi'",
                'STATUS_READY_FOR_COMMERCIAL',
                'STATUS_PRESENTED',
                '->accept(',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            '$request->input(',
            $source
        );

        $this->assertStringNotContainsString(
            '$request->route(',
            $source
        );
    }

    public function test_routes_keep_tenant_engagement_id_out_of_browser_contract(): void
    {
        $adminRoutes =
            file_get_contents(
                base_path(
                    'routes/admin.php'
                )
            );

        $webRoutes =
            file_get_contents(
                base_path(
                    'routes/web.php'
                )
            );

        foreach (
            [
                'commercial_engagements.store',
                'commercial_engagements.present',
                'commercial_engagements.authorize',
            ]
            as $token
        ) {
            $this->assertStringContainsString(
                $token,
                $adminRoutes
            );
        }

        $this->assertStringContainsString(
            'app.transformation.data_bi.commercial_engagement.accept',
            $webRoutes
        );

        $tenantRouteStart =
            strpos(
                $webRoutes,
                '/app/transformacion-360/datos-bi/propuesta-comercial/aceptar'
            );

        $this->assertNotFalse(
            $tenantRouteStart
        );

        $tenantRouteSection =
            substr(
                $webRoutes,
                $tenantRouteStart,
                700
            );

        $this->assertStringNotContainsString(
            '{implementationRequest}',
            $tenantRouteSection
        );

        $this->assertStringNotContainsString(
            '{engagement}',
            $tenantRouteSection
        );
    }
}
