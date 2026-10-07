<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiCanonicalAuthorizationGateContractTest
    extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            base_path(
                'app/Services/Diagnosis/DataTransformationBiCanonicalModelService.php'
            )
        );
    }

    public function test_canonical_service_injects_modern_implementation_authorization_gate(): void
    {
        $source =
            $this->source();

        $this->assertStringContainsString(
            'private readonly TransformationImplementationAuthorizationGate',
            $source
        );

        $this->assertStringContainsString(
            '$implementationAuthorizationGate',
            $source
        );
    }

    public function test_exactly_five_canonical_write_boundaries_require_active_authorization(): void
    {
        $source =
            $this->source();

        $needle =
            <<<'PHP_SOURCE'
$this->implementationAuthorizationGate
                    ->assertActiveForRequest(
                        $request,
                        true
                    );
PHP_SOURCE;

        $this->assertSame(
            5,
            substr_count(
                $source,
                $needle
            )
        );
    }

    public function test_all_expected_canonical_write_methods_remain_present(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'public function prepareDraft(',
                'public function createEntity(',
                'public function replaceFields(',
                'public function replaceRelationships(',
                'public function publish(',
            ]
            as $method
        ) {
            $this->assertStringContainsString(
                $method,
                $source
            );
        }
    }

    public function test_read_methods_remain_available_without_direct_implementation_gate_calls(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'public function workspace(',
                'public function publishedRegistry(',
                'public function publishedWorkspace(',
            ]
            as $method
        ) {
            $this->assertStringContainsString(
                $method,
                $source
            );
        }

        $workspaceStart =
            strpos(
                $source,
                'public function workspace('
            );

        $publishedRegistryStart =
            strpos(
                $source,
                'public function publishedRegistry('
            );

        $publishedWorkspaceStart =
            strpos(
                $source,
                'public function publishedWorkspace('
            );

        $prepareDraftStart =
            strpos(
                $source,
                'public function prepareDraft('
            );

        $this->assertNotFalse(
            $workspaceStart
        );

        $this->assertNotFalse(
            $publishedRegistryStart
        );

        $this->assertNotFalse(
            $publishedWorkspaceStart
        );

        $this->assertNotFalse(
            $prepareDraftStart
        );

        /*
         * Everything before prepareDraft() is the read-only public surface.
         * The modern implementation gate must not occur there.
         */
        $readSurface =
            substr(
                $source,
                0,
                $prepareDraftStart
            );

        $this->assertStringNotContainsString(
            '->assertActiveForRequest(',
            $readSurface
        );
    }

    public function test_canonical_registry_remains_company_owned_not_authorization_owned(): void
    {
        $source =
            $this->source();

        $this->assertStringContainsString(
            "'company_id' =>",
            $source
        );

        $this->assertStringContainsString(
            "'source_transformation_implementation_request_id' =>",
            $source
        );

        $this->assertStringNotContainsString(
            "'transformation_implementation_authorization_id' =>",
            $source
        );

        $this->assertStringNotContainsString(
            'implementation_authorization_id',
            $source
        );
    }

    public function test_canonical_gate_does_not_start_execution_or_mutate_commercial_lifecycle(): void
    {
        $source =
            $this->source();

        foreach (
            [
                'TransformationImplementationExecutionService',
                'TransformationImplementationCapabilityExecution',
                'TransformationImplementationPhaseExecution',
                "'ready_for_execution' => true",
                "'execution_started' => true",
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_canonical_service_remains_independent_from_mapping_service(): void
    {
        $source =
            $this->source();

        $this->assertStringNotContainsString(
            'DataTransformationBiSourceAssetMappingService',
            $source
        );

        $this->assertStringNotContainsString(
            'DataTransformationBiSourceAssetMapping',
            $source
        );

        $this->assertStringNotContainsString(
            'DataTransformationBiSourceAssetFieldMapping',
            $source
        );
    }
}
