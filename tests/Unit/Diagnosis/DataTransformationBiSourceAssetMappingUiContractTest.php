<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceAssetMappingUiContractTest
    extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source =
            file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        self::assertIsString(
            $this->source
        );
    }

    public function test_ui_exposes_explicit_ready_and_validate_actions(): void
    {
        foreach (
            [
                'function markDynamicSourceMappingReady(',
                'function validateDynamicSourceMapping(',
                'Marcar listo para validar',
                'Validar mapeo',
                "/ready`",
                "/validate`",
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_ready_action_is_only_available_for_editable_prevalidation_states(): void
    {
        self::assertStringContainsString(
            'function dynamicSourceMappingCanMarkReady(',
            $this->source
        );

        foreach (
            [
                "'draft'",
                "'blocked'",
            ]
            as $state
        ) {
            self::assertStringContainsString(
                $state,
                $this->source
            );
        }
    }

    public function test_validate_action_is_only_available_for_ready_mapping(): void
    {
        self::assertStringContainsString(
            'function dynamicSourceMappingCanValidate(',
            $this->source
        );

        self::assertStringContainsString(
            "mapping?.status === 'ready'",
            $this->source
        );
    }

    public function test_ui_uses_server_workspace_after_each_transition(): void
    {
        foreach (
            [
                'payload.workspace',
                'dynamicSourceMappingWorkspace.value =',
                'hydrateDynamicSourceMappingDecisions(',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_ui_does_not_mutate_mapping_status_locally(): void
    {
        foreach (
            [
                "mapping.status = 'ready'",
                "mapping.status = 'validated'",
                "mapping.status = \"ready\"",
                "mapping.status = \"validated\"",
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->source
            );
        }
    }

    public function test_ui_does_not_start_execution_or_materialization(): void
    {
        foreach (
            [
                'TransformationImplementationExecutionService',
                'ready_for_execution',
                'execution_started',
                'DataTransformationBiNormalizedRow',
                'materialize',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->actionSurface()
            );
        }
    }

    private function actionSurface(): string
    {
        $start =
            strpos(
                $this->source,
                'function dynamicSourceMappingCanMarkReady('
            );

        $end =
            strpos(
                $this->source,
                'async function loadDynamicSourceMappingWorkspace(',
                $start
            );

        self::assertNotFalse($start);
        self::assertNotFalse($end);

        return substr(
            $this->source,
            $start,
            $end - $start
        );
    }
}
