<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionAdminUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_admin_definition_routes_exist(): void
    {
        $routes =
            file_get_contents(
                $this->root()
                .'/routes/admin.php'
            );

        foreach ([
            'implementation_definition.show',
            'implementation_definition.create',
            'implementation_definition.regenerate',
            'implementation_definition.review',
            'implementation_definition.ready',
            '/implementation-plan/{plan}/definition',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $routes
            );
        }
    }

    public function test_controller_uses_definition_services_only(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationDefinitionController.php'
            );

        foreach ([
            'TransformationImplementationDefinitionService',
            'TransformationImplementationDefinitionAutogenerator',
            'TransformationImplementationDefinitionReviewService',
            'createOrGetDraftFromPresentedPlan(',
            'saveReview(',
            'markReady(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'CommercialRate',
            'CommercialCalculator',
            'PricingService',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'TransformationImplementationExecutionService',
            'TransformationImplementationGoLiveService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_ui_exposes_review_but_not_execution_or_commercial_actions(): void
    {
        $page =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationDefinition.vue'
            );

        foreach ([
            'Definición de Implementación',
            'Alcance de implementación',
            'Entregables',
            'Dependencias',
            'Responsabilidades',
            'Validación de readiness',
            'Guardar revisión',
            'Marcar como lista',
            'LAUDA',
            'Cliente',
            'Compartido',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        foreach ([
            'Iniciar ejecución',
            'Go-Live',
            'Precio',
            'Tarifa',
            'Facturar',
            'Cobrar',
            'SubscriptionItem',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $page
            );
        }
    }

    public function test_plan_links_to_definition_only_after_presented_state(): void
    {
        $page =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue'
            );

        $this->assertStringContainsString(
            "plan?.status === 'presented'",
            $page
        );

        $this->assertStringContainsString(
            'Definición de Implementación',
            $page
        );
    }
}
