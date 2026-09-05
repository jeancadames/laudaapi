<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionRequestScopeFoundationContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    public function test_migration_adds_request_scoped_identity(): void
    {
        $source =
            $this->project(
                'database/migrations/'
                .'2026_09_05_134500_add_request_scope_to_'
                .'transformation_implementation_definitions.php'
            );

        foreach ([
            'transformation_implementation_request_id',
            'transformation_implementation_phase_capability_id',
            'capability_key',
            'tid_request_fk',
            'tid_phase_capability_fk',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_historical_plan_wide_definitions_remain_compatible(): void
    {
        $source =
            $this->project(
                'database/migrations/'
                .'2026_09_05_134500_add_request_scope_to_'
                .'transformation_implementation_definitions.php'
            );

        /*
         * Las nuevas columnas deben ser nullable:
         * no se reescriben Definitions históricas.
         */
        $this->assertGreaterThanOrEqual(
            3,
            substr_count(
                $source,
                '->nullable()'
            )
        );
    }

    public function test_request_definition_versions_are_unique_per_request(): void
    {
        $source =
            $this->project(
                'database/migrations/'
                .'2026_09_05_134500_add_request_scope_to_'
                .'transformation_implementation_definitions.php'
            );

        $this->assertStringContainsString(
            "'tid_request_version_uq'",
            $source
        );

        $this->assertStringContainsString(
            "'version'",
            $source
        );
    }

    public function test_definition_model_knows_request_and_phase_capability(): void
    {
        $source =
            $this->project(
                'app/Models/'
                .'TransformationImplementationDefinition.php'
            );

        foreach ([
            "'transformation_implementation_request_id'",
            "'transformation_implementation_phase_capability_id'",
            "'capability_key'",
            'implementationRequest(): BelongsTo',
            'phaseCapability(): BelongsTo',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_request_model_exposes_definition_versions(): void
    {
        $source =
            $this->project(
                'app/Models/'
                .'TransformationImplementationRequest.php'
            );

        $this->assertStringContainsString(
            'definitions(): HasMany',
            $source
        );

        $this->assertStringContainsString(
            'TransformationImplementationDefinition::class',
            $source
        );
    }

    public function test_new_contract_requires_definition_preparation(): void
    {
        $source =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationDefinitionRequestScopeContract.php'
            );

        $this->assertStringContainsString(
            'STATUS_DEFINITION_PREPARATION',
            $source
        );

        $this->assertStringContainsString(
            "'single_capability'",
            $source
        );

        $this->assertStringContainsString(
            "'plan_wide_definition'",
            $source
        );

        $this->assertStringContainsString(
            "'auto_definition'",
            $source
        );
    }

    public function test_foundation_has_no_commercial_execution_or_activation_service(): void
    {
        $sources =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationDefinitionRequestScopeContract.php'
            )
            ."\n"
            .$this->project(
                'database/migrations/'
                .'2026_09_05_134500_add_request_scope_to_'
                .'transformation_implementation_definitions.php'
            );

        foreach ([
            'TransformationImplementationCommercialEngine',
            'TransformationImplementationExecutionService',
            'TransformationCapabilityActivationService',
            'TransformationImplementationSubscriptionService',
            'CentralEntitlementActivationService',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $sources
            );
        }
    }
}
