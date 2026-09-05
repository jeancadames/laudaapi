<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestReadyForCommercialAdminHttpUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(
            __DIR__,
            3
        );
    }

    public function test_route_is_request_only(): void
    {
        $routes =
            file_get_contents(
                $this->root()
                .'/routes/admin.php'
            );

        $uri =
            '/transformation-360/implementation-requests/{implementationRequest}/ready-for-commercial';

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
            'AdminTransformationImplementationRequestDefinitionActionController::class',
            "'readyForCommercial'",
            'implementation_requests.ready_for_commercial',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $statement
            );
        }

        foreach ([
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

    public function test_http_action_uses_only_hardened_domain_gate(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $start =
            strpos(
                $controller,
                'public function readyForCommercial('
            );

        $this->assertNotFalse(
            $start
        );

        $end =
            strpos(
                $controller,
                'private function authorizeAdmin(',
                $start
            );

        $this->assertNotFalse(
            $end
        );

        $method =
            substr(
                $controller,
                $start,
                $end - $start
            );

        foreach ([
            '$this->authorizeAdmin(',
            'TransformationImplementationRequestReadyForCommercialService',
            '->markReadyForCommercial(',
            '$implementationRequest',
            '$request->user()',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $method
            );
        }

        foreach ([
            'TransformationImplementationDefinition $definition',
            "input('definition_id'",
            'transitionByLauda(',
            'Subscription::',
            'Invoice::',
            'Payment::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $method
            );
        }
    }

    public function test_read_model_exposes_dedicated_gate_action(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            '$readyForCommercialContext',
            '$readyForCommercialAvailable',
            "'definition_agreed_by_tenant'",
            "'definition_functionally_finalized_by_lauda'",
            "'ready_for_commercial_context'",
            "'can_mark_ready_for_commercial'",
            "'ready_for_commercial_endpoint'",
            'implementation_requests.ready_for_commercial',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }

        $this->assertSame(
            1,
            substr_count(
                $controller,
                "'ready_for_commercial_endpoint' =>"
            )
        );
    }

    public function test_ui_exposes_explicit_noncommercial_gate(): void
    {
        $ui =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        foreach ([
            'ready_for_commercial_context: {',
            'can_mark_ready_for_commercial: boolean;',
            'ready_for_commercial_endpoint: string | null;',
            'const readyForCommercialForm = useForm({});',
            'function markRequestReadyForCommercial(): void',
            'Dejar listo para etapa comercial',
            'Ciclo funcional completado',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }

        $normalized =
            strtolower(
                (string) preg_replace(
                    '/\s+/u',
                    ' ',
                    $ui
                )
            );

        foreach ([
            'no crea propuesta, precio, contrato',
            'factura, pago, suscripción, activación ni ejecución',
            'tampoco constituye aceptación comercial',
            'proceso comercial separado',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $normalized
            );
        }
    }
}
