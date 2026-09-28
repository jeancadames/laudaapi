<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiStructuralSemanticSignalsWorkspaceContractTest
    extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationWorkspaceReadModel.php'
            );
    }

    public function test_admin_workspace_exposes_safe_structural_semantic_signals(): void
    {
        self::assertStringContainsString(
            "'structural_semantic_signals' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            'DataTransformationBiStructuralSemanticSignalsReadModel',
            $this->workspace
        );

        self::assertStringContainsString(
            '$source->profiling_snapshot',
            $this->workspace
        );
    }

    public function test_workspace_does_not_expose_profile_snapshot_as_semantic_payload(): void
    {
        self::assertStringContainsString(
            'No raw values or source samples',
            $this->workspace
        );
    }
}
