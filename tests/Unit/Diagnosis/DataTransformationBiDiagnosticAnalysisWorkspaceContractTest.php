<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticAnalysisWorkspaceContractTest
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

    public function test_admin_workspace_exposes_diagnostic_analysis_projection(): void
    {
        self::assertStringContainsString(
            "'diagnostic_analysis' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            'DataTransformationBiDiagnosticAnalysisWorkspaceProjection',
            $this->workspace
        );

        self::assertStringContainsString(
            '::fromEvaluation(',
            $this->workspace
        );
    }

    public function test_workspace_passes_evaluation_not_live_source_payloads_to_analysis_projection(): void
    {
        $start =
            strpos(
                $this->workspace,
                "'diagnostic_analysis' =>"
            );

        self::assertNotFalse(
            $start
        );

        $tail =
            substr(
                $this->workspace,
                $start
            );

        $end =
            strpos(
                $tail,
                "'evaluation' =>"
            );

        self::assertNotFalse(
            $end
        );

        $block =
            substr(
                $tail,
                0,
                $end
            );

        self::assertStringContainsString(
            '$evaluation',
            $block
        );

        self::assertStringNotContainsString(
            '$sourcePayloads',
            $block
        );
    }

    public function test_existing_descriptive_semantic_layers_remain_available(): void
    {
        foreach ([
            "'semantic_diagnostic' =>",
            "'domain_coverage' =>",
            "'cross_source_relationship_candidates' =>",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->workspace
            );
        }
    }

    public function test_workspace_does_not_expose_analysis_to_tenant_projection_in_this_checkpoint(): void
    {
        $tenant =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantSourceWorkspaceProjection.php'
            );

        self::assertStringNotContainsString(
            "'diagnostic_analysis' =>",
            $tenant
        );
    }
}
