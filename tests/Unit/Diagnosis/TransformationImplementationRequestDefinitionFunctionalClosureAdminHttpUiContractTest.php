<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionFunctionalClosureAdminHttpUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(
            __DIR__,
            3
        );
    }

    public function test_admin_route_is_request_only(): void
    {
        $routes =
            file_get_contents(
                $this->root()
                .'/routes/admin.php'
            );

        $uri =
            '/transformation-360/implementation-requests/{implementationRequest}/definition/finalize-functional';

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
            "'finalizeFunctional'",
            'definition.functional_finalize',
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

    public function test_http_action_delegates_to_exact_agreement_domain_without_definition_input(): void
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
                'public function finalizeFunctional('
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
            'TransformationImplementationRequestDefinitionFunctionalClosureService',
            '->finalize(',
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
            'markReady(',
            'transitionByLauda(',
            'STATUS_READY_FOR_COMMERCIAL',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $method
            );
        }
    }

    public function test_read_model_uses_agreement_event_not_latest_for_functional_closure(): void
    {
        $controller =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            '$functionalClosureContext',
            '$definitionFunctionalClosureAvailable',
            "'definition_agreed_by_tenant'",
            "'definition_id'",
            "'definition_version'",
            "'functional_closure_context'",
            "'can_finalize_definition_functionally'",
            "'definition_functional_finalize_endpoint'",
            'definition.functional_finalize',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $controller
            );
        }

        $start =
            strpos(
                $controller,
                '$functionalClosureContext ='
            );

        $end =
            strpos(
                $controller,
                '$definitionContentPrepared =',
                $start
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $closureReadModel =
            substr(
                $controller,
                $start,
                $end - $start
            );

        $this->assertStringNotContainsString(
            "orderByDesc(\n                                'version'",
            $closureReadModel
        );
    }

    public function test_ui_shows_exact_agreed_version_and_explicit_functional_closure(): void
    {
        $ui =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        foreach ([
            'functional_closure_context: {',
            'definition_version: number;',
            'can_finalize_definition_functionally: boolean;',
            'definition_functional_finalize_endpoint: string | null;',
            'const functionalClosureForm = useForm({});',
            'function finalizeFunctionalDefinition(): void',
            'Finalizar Definition funcional',
            'Definition funcional finalizada',
            'versión exacta fijada por el acuerdo del tenant',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $ui
            );
        }
    }

    public function test_ui_keeps_functional_and_commercial_stages_separate(): void
    {
        $ui =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/Show.vue'
            );

        $this->assertIsString(
            $ui
        );

        /*
         * El contrato es semántico, no tipográfico:
         * - no depende de mayúscula/minúscula;
         * - no depende de saltos de línea o indentación Vue.
         */
        $normalizedUi =
            strtolower(
                (string) preg_replace(
                    '/\s+/u',
                    ' ',
                    $ui
                )
            );

        foreach ([
            'no activa el servicio',
            'no inicia ejecución',
            'no crea una suscripción',
            'no mueve la solicitud a etapa comercial',
            'por separado, el gate hacia etapa comercial',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $normalizedUi
            );
        }
    }
}
