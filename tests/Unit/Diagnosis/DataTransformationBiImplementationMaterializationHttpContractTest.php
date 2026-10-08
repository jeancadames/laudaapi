<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiImplementationMaterializationHttpContractTest
    extends TestCase
{
    public function test_http_boundary_is_separate_from_legacy_staging_materialize(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        $this->assertIsString(
            $routes
        );

        $this->assertStringContainsString(
            '/implementation-datasets/materialize',
            $routes
        );

        $this->assertStringContainsString(
            '/implementation-datasets/materialization-runs/{runUuid}',
            $routes
        );

        $this->assertStringContainsString(
            'AdminDataTransformationBiImplementationMaterializationController',
            $routes
        );

        /*
         * Historical staging route must continue to exist independently.
         */
        $this->assertStringContainsString(
            "/standard-intake-v2/sessions/{sessionId}/materialize'",
            $routes
        );

        $this->assertStringContainsString(
            "'materializeSession'",
            $routes
        );
    }

    public function test_post_dispatches_and_returns_accepted_run_contract(): void
    {
        $source =
            $this->controllerSource();

        foreach (
            [
                'DataTransformationBiImplementationMaterializationDispatchService',
                '->dispatch(',
                "'materialization'",
                "'run_uuid'",
                "'status'",
                "'reused_run'",
                '202',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_status_endpoint_is_scoped_to_exact_run_uuid(): void
    {
        $source =
            $this->controllerSource();

        foreach (
            [
                "'company_id'",
                "'transformation_implementation_request_id'",
                "'data_transformation_bi_intake_session_id'",
                "'run_uuid'",
                '$runUuid',
                '->firstOrFail()',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_http_payload_does_not_expose_canonical_rows(): void
    {
        $source =
            $this->controllerSource();

        foreach (
            [
                'canonical_payload',
                'source_row_number',
                'source_row_sha256',
                'canonical_payload_sha256',
                'DataTransformationBiImplementationRow',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_http_boundary_does_not_mutate_execution_lifecycle(): void
    {
        $source =
            $this->controllerSource();

        foreach (
            [
                'ready_for_execution',
                'execution_started',
                'TransformationImplementationExecutionService',
                'DataTransformationBiIntakeV2StagingMaterializationService',
                'DataTransformationBiNormalizedRow',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function controllerSource(): string
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $source =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiImplementationMaterializationController.php'
            );

        $this->assertIsString(
            $source
        );

        return $source;
    }
}
