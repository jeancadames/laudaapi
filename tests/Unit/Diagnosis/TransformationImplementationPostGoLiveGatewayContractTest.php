<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPostGoLiveGatewayContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents($this->root().'/'.$path);
    }

    public function test_company_context_never_silently_selects_between_multiple_companies(): void
    {
        $source = $this->read('app/Services/Subscribers/CompanyContextResolver.php');

        foreach ([
            'preferredCompanyId',
            'userCompanyId',
            '$owned->count() > 1',
            '$subscriberCompanies->count() === 1',
            '->limit(2)',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_gateway_sends_entitled_subscriber_to_erp_and_otherwise_to_diagnosis(): void
    {
        $source = $this->read('app/Http/Controllers/AppGatewayController.php');

        foreach ([
            'SubscriberResolver',
            'CompanyContextResolver',
            'SubscriberEntitlements',
            'erpServicesForSubscriber(',
            "'erp.dashboard'",
            'DiagnosisAccessRequest::query()',
            "'diagnosis.show'",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $routes = $this->read('routes/web.php');
        $this->assertStringContainsString("->name('app.gateway')", $routes);
    }

    public function test_erp_pipeline_reuses_one_company_context(): void
    {
        foreach ([
            'app/Http/Middleware/EnsureErpAccess.php',
            'app/Http/Middleware/HandleInertiaRequests.php',
            'app/Http/Controllers/LaudaErp/ServiceLaunchController.php',
            'app/Services/Diagnosis/TransformationImplementationClientSolutionAccessService.php',
        ] as $path) {
            $this->assertStringContainsString(
                'CompanyContextResolver',
                $this->read($path)
            );
        }

        $erp = $this->read('app/Http/Middleware/EnsureErpAccess.php');
        $this->assertStringContainsString("'resolved_company_id'", $erp);
        $this->assertStringContainsString("'currentCompany'", $erp);
    }

    public function test_lauda360_portal_cta_requires_entitlement(): void
    {
        $controller = $this->read('app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php');
        $page = $this->read('resources/js/pages/Diagnosis/ImplementationPlan.vue');

        $this->assertStringContainsString("\$solutionAccessSummary['portal_url']", $controller);
        $this->assertStringContainsString("'entitled_count'", $controller);
        $this->assertStringContainsString("'app.gateway'", $controller);
        $this->assertStringContainsString('Ir a mi portal', $page);
        $this->assertStringContainsString('.portal_url', $page);
    }

    public function test_foundation_f_does_not_activate_commercial_state(): void
    {
        $combined = $this->read('app/Http/Controllers/AppGatewayController.php')
            ."\n".$this->read('app/Services/Subscribers/CompanyContextResolver.php');

        foreach ([
            'activateSubscriptionForGoLive(',
            'activateServiceForGoLive(',
            'activateFromGoLive(',
            'Subscription::create(',
            'SubscriptionItem::create(',
        ] as $token) {
            $this->assertStringNotContainsString($token, $combined);
        }
    }
}
