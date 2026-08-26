<?php

namespace Tests\Unit\Subscriber;

use PHPUnit\Framework\TestCase;

class LegacyPublicActivationHardeningContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_marketplace_request_form_posts_diagnosis_contact_contract(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Marketplace/Partials/RequestForm.vue'
        );

        foreach ([
            "const DIAGNOSIS_REQUEST_ENDPOINT = '/contact'",
            "'digital_diagnosis_access_request'",
            "'digital_transformation_360'",
            "'private_invitation'",
            'Solicitud de acceso al Diagnóstico LAUDA 360',
            'company_size',
            'main_challenge',
            'assistance_level',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'axios.post(props.postUrl',
            "actionUrl.value = '/subscriber/activation'",
            'Ir a iniciar trial',
            'Activar prueba gratis 30 días',
            'Activando prueba',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_marketplace_visible_cta_no_longer_promises_direct_activation(): void
    {
        $erp = file_get_contents(
            $this->root()
            .'/resources/js/pages/Marketplace/Partials/DetailErpModular.vue'
        );

        $this->assertStringNotContainsString(
            'Activar prueba gratis 30 días',
            $erp
        );

        $this->assertStringContainsString(
            'Solicitar Diagnóstico LAUDA 360',
            $erp
        );

        $apiPath = $this->root()
            .'/resources/js/pages/Marketplace/Partials/DetailApiMarketplace.vue';

        if (is_file($apiPath)) {
            $api = file_get_contents(
                $apiPath
            );

            $this->assertStringNotContainsString(
                'Solicitar y activar módulos',
                $api
            );
        }
    }

    public function test_public_activation_compatibility_no_longer_routes_accepted_to_trial(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/ActivationRequestController.php'
        );

        $accepted = explode(
            'if ($status === ActivationRequest::STATUS_ACCEPTED) {',
            $source,
            2
        )[1];

        $accepted = explode(
            'if ($status === ActivationRequest::STATUS_TRIALING',
            $accepted,
            2
        )[0];

        $this->assertStringContainsString(
            "\$nextUrl = '';",
            $accepted
        );

        $this->assertStringContainsString(
            "\$nextLabel = '';",
            $accepted
        );

        $this->assertStringNotContainsString(
            '/subscriber/activation',
            $accepted
        );

        $this->assertStringNotContainsString(
            'iniciar trial',
            $accepted
        );
    }

    public function test_backend_diagnosis_contract_already_recognizes_marketplace_payload(): void
    {
        $request = file_get_contents(
            $this->root()
            .'/app/Http/Requests/StoreContactRequest.php'
        );

        $service = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/DiagnosisAccessService.php'
        );

        foreach ([
            'digital_diagnosis_access_request',
            'Solicitud de acceso al Diagnóstico LAUDA 360',
            'digital_transformation_360',
            'private_invitation',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $request
            );
        }

        foreach ([
            'digital_diagnosis_access_request',
            'Solicitud de acceso al Diagnóstico LAUDA 360',
            'workflowFor(',
            'approve(',
            'sendInvitation(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $service
            );
        }
    }

    public function test_real_public_diagnosis_entry_is_invitation_then_resume(): void
    {
        $routes = file_get_contents(
            $this->root()
            .'/routes/web.php'
        );

        $this->assertStringContainsString(
            'diagnostico-invitacion/{access}',
            $routes
        );

        $this->assertStringContainsString(
            'mi-diagnostico',
            $routes
        );
    }
}
