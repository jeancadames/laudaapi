<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionTenantChangesAuthorizationContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationRequestDefinitionTenantDecisionService.php'
        );
    }

    public function test_domain_itself_validates_tenant_admin_identity(): void
    {
        $source =
            $this->source();

        foreach ([
            'assertTenantActor(',
            'SubscriberResolver',
            'CompanyContextResolver',
            'TenantAccessService',
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'tenant_admin'",
            "'subscriber'",
            'AuthorizationException',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_actor_company_must_equal_request_company(): void
    {
        $source =
            $this->source();

        foreach ([
            '$company->id',
            '$request->company_id',
            'La solicitud no pertenece a la empresa del usuario.',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_generic_transition_is_not_the_only_authorization_gate(): void
    {
        $source =
            $this->source();

        $assertPosition =
            strpos(
                $source,
                '$this->assertTenantActor('
            );

        $transitionPosition =
            strpos(
                $source,
                '->transitionByTenant('
            );

        $this->assertNotFalse(
            $assertPosition
        );

        $this->assertNotFalse(
            $transitionPosition
        );

        $this->assertLessThan(
            $transitionPosition,
            $assertPosition
        );
    }
}
