<?php

namespace Tests\Unit\Diagnosis;

use App\Models\TransformationImplementationDefinition;
use App\Services\Diagnosis\TransformationImplementationRequestDefinitionTenantReviewService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataTransformationBiDefinitionTenantReviewGateSeparationTest
    extends TestCase
{
    public function test_data_bi_definition_review_does_not_require_source_delivery_readiness(): void
    {
        $serviceReflection =
            new ReflectionClass(
                TransformationImplementationRequestDefinitionTenantReviewService::class
            );

        $service =
            $serviceReflection
                ->newInstanceWithoutConstructor();

        $method =
            $serviceReflection
                ->getMethod(
                    'requiredConfirmations'
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

    public function test_other_capabilities_keep_historical_six_confirmation_contract(): void
    {
        $serviceReflection =
            new ReflectionClass(
                TransformationImplementationRequestDefinitionTenantReviewService::class
            );

        $service =
            $serviceReflection
                ->newInstanceWithoutConstructor();

        $method =
            $serviceReflection
                ->getMethod(
                    'requiredConfirmations'
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

    public function test_admin_read_model_uses_same_data_bi_exception_without_forcing_machine_readiness(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        self::assertIsString(
            $controller
        );

        self::assertStringContainsString(
            "=== 'data_transformation_bi'",
            $controller
        );

        self::assertStringContainsString(
            "'human_validation.inputs_validated'",
            $controller
        );

        self::assertStringContainsString(
            "'human_validation.accesses_validated'",
            $controller
        );

        self::assertStringContainsString(
            '$definitionReadyForTenantReview',
            $controller
        );
    }

    public function test_source_readiness_remains_server_owned_and_separate(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $sourceReadiness =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiSourceReadinessService.php'
            );

        $workspaceGate =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantSourceWorkspaceGate.php'
            );

        self::assertIsString(
            $sourceReadiness
        );

        self::assertIsString(
            $workspaceGate
        );

        self::assertStringContainsString(
            "'inputs_validated'",
            $sourceReadiness
        );

        self::assertStringContainsString(
            "'accesses_validated'",
            $sourceReadiness
        );

        self::assertStringContainsString(
            'STATUS_DEFINITION_AGREED',
            $workspaceGate
        );

        self::assertStringContainsString(
            "'can_manage' => true",
            $workspaceGate
        );
    }
}
