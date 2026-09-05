<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantDefinitionAgreementHttpUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(
            __DIR__,
            3
        );
    }

    public function test_agreement_route_has_no_browser_identity_parameters(): void
    {
        $routes =
            file_get_contents(
                $this->root()
                .'/routes/web.php'
            );

        $uri =
            '/app/transformacion-360/datos-bi/definition/acordar';

        $position =
            strpos(
                $routes,
                $uri
            );

        $this->assertNotFalse(
            $position
        );

        $start =
            strrpos(
                substr(
                    $routes,
                    0,
                    $position
                ),
                'Route::post('
            );

        $end =
            strpos(
                $routes,
                ';',
                $position
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $statement =
            substr(
                $routes,
                $start,
                $end - $start + 1
            );

        foreach ([
            $uri,
            'AppHubDataTransformationBiDefinitionReviewController::class',
            "'agree'",
            'app.transformation.data_bi.definition.agree',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $statement
            );
        }

        foreach ([
            '{implementationRequest}',
            '{definition}',
            '{company}',
            '{capability}',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $statement
            );
        }
    }

    public function test_http_action_server_resolves_request_and_definition(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiDefinitionReviewController.php'
            );

        $start =
            strpos(
                $controller,
                'public function agree('
            );

        $this->assertNotFalse(
            $start
        );

        $agree =
            substr(
                $controller,
                $start
            );

        foreach ([
            'SubscriberResolver',
            'CompanyContextResolver',
            'TenantAccessService::SUBSCRIBER_ADMIN',
            "'company_id'",
            "'capability_key'",
            "'data_transformation_bi'",
            'STATUS_AWAITING_TENANT_REVIEW',
            "'transformation_implementation_request_id'",
            "'diagnosis_assessment_id'",
            "'transformation_implementation_plan_id'",
            "'transformation_implementation_phase_capability_id'",
            'orderByDesc(',
            "'version'",
            "'id'",
            '->agree(',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $agree
            );
        }

        foreach ([
            "\$request->input('request_id'",
            "\$request->input('definition_id'",
            "\$request->input('company_id'",
            "\$request->input('capability_key'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $agree
            );
        }
    }

    public function test_read_model_exposes_agreement_endpoint_only_for_review_stage(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        foreach ([
            "'agreement_endpoint' => null",
            "'agreement_endpoint' =>",
            'STATUS_AWAITING_TENANT_REVIEW',
            "'app.transformation.data_bi.definition.agree'",
            "'definition_review' =>",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }
    }

    public function test_ui_requires_exact_presented_completed_review(): void
    {
        $ui =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'agreement_endpoint: string | null;',
            'const agreementSubmitting = ref(false);',
            'const canAgreeDefinition = computed(',
            "=== 'awaiting_tenant_review'",
            'tenantDefinitionReview.value',
            '?.human_review',
            '?.completed',
            'function agreeDefinition(): void',
            'router.post(',
            'Acordar esta Definition',
            'Definition acordada',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_ui_preserves_noncommercial_boundary(): void
    {
        $ui =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach ([
            'no activa el',
            'no inicia implementación',
            'no constituye aceptación',
            'no crea una suscripción',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_http_ui_does_not_finalize_or_start_downstream(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiDefinitionReviewController.php'
            );

        $start =
            strpos(
                $controller,
                'public function agree('
            );

        $agree =
            substr(
                $controller,
                $start
            );

        foreach ([
            'markReady(',
            'STATUS_READY_FOR_COMMERCIAL',
            'TransformationCapabilityActivation',
            'TransformationImplementationExecution',
            'Subscription::',
            'Invoice::',
            'Payment::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $agree
            );
        }
    }
}
