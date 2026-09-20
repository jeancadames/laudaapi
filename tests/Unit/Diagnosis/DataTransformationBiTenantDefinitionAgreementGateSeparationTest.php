<?php

namespace Tests\Unit\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionTenantDecisionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiTenantDefinitionAgreementGateSeparationTest
    extends TestCase
{
    public function test_data_bi_agreement_requires_only_functional_confirmations(): void
    {
        $reflection =
            new ReflectionClass(
                TransformationImplementationRequestDefinitionTenantDecisionService::class
            );

        $service =
            $reflection
                ->newInstanceWithoutConstructor();

        $method =
            $reflection
                ->getMethod(
                    'requiredAgreementConfirmations'
                );

        $definition =
            new TransformationImplementationDefinition();

        $definition->capability_key =
            'data_transformation_bi';

        $required =
            $method->invoke(
                $service,
                $definition
            );

        self::assertSame(
            [
                'scope_confirmed',
                'deliverables_confirmed',
                'dependencies_confirmed',
                'responsibilities_confirmed',
            ],
            $required
        );

        self::assertNotContains(
            'inputs_validated',
            $required
        );

        self::assertNotContains(
            'accesses_validated',
            $required
        );
    }

    public function test_other_capabilities_keep_historical_six_confirmation_agreement_contract(): void
    {
        $reflection =
            new ReflectionClass(
                TransformationImplementationRequestDefinitionTenantDecisionService::class
            );

        $service =
            $reflection
                ->newInstanceWithoutConstructor();

        $method =
            $reflection
                ->getMethod(
                    'requiredAgreementConfirmations'
                );

        $definition =
            new TransformationImplementationDefinition();

        $definition->capability_key =
            'other_professional_service';

        $required =
            $method->invoke(
                $service,
                $definition
            );

        self::assertSame(
            [
                'scope_confirmed',
                'deliverables_confirmed',
                'dependencies_confirmed',
                'inputs_validated',
                'accesses_validated',
                'responsibilities_confirmed',
            ],
            $required
        );
    }

    public function test_data_bi_tenant_read_model_uses_functional_confirmation_subset(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/'
                .'AppHubDataTransformationBiController.php'
            );

        self::assertIsString(
            $controller
        );

        self::assertStringContainsString(
            '$functionalConfirmationKeys = [',
            $controller
        );

        foreach ([
            "'scope_confirmed'",
            "'deliverables_confirmed'",
            "'dependencies_confirmed'",
            "'responsibilities_confirmed'",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $controller
            );
        }

        self::assertStringContainsString(
            '$confirmations[$key]',
            $controller
        );

        self::assertStringContainsString(
            "'inputs_validated' =>",
            $controller
        );

        self::assertStringContainsString(
            "'accesses_validated' =>",
            $controller
        );
    }

    public function test_tenant_ui_human_review_shows_only_functional_checks(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $ui =
            file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        self::assertIsString(
            $ui
        );

        $start =
            strpos(
                $ui,
                'const humanReviewChecks = computed'
            );

        $end =
            strpos(
                $ui,
                'const definitionItemTitle',
                $start
            );

        self::assertNotFalse(
            $start
        );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $ui,
                $start,
                $end - $start
            );

        foreach ([
            'Alcance confirmado',
            'Entregables confirmados',
            'Dependencias confirmadas',
            'Responsabilidades confirmadas',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $block
            );
        }

        self::assertStringNotContainsString(
            'Insumos validados',
            $block
        );

        self::assertStringNotContainsString(
            'Accesos validados',
            $block
        );

        self::assertStringContainsString(
            'tenantDefinitionReview.value',
            $ui
        );

        self::assertStringContainsString(
            '?.human_review',
            $ui
        );

        self::assertStringContainsString(
            '?.completed',
            $ui
        );

        self::assertStringContainsString(
            'Acordar esta Definition',
            $ui
        );
    }

    public function test_source_readiness_remains_server_owned(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceReadinessService.php'
            );

        self::assertIsString(
            $source
        );

        self::assertStringContainsString(
            "'inputs_validated'",
            $source
        );

        self::assertStringContainsString(
            "'accesses_validated'",
            $source
        );

        self::assertStringContainsString(
            '$sourceCount === 0',
            $source
        );
    }
}
