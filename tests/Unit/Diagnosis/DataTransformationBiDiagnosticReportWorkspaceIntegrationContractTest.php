<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticReportWorkspaceIntegrationContractTest
    extends TestCase
{
    private string $workspace;

    private string $report;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->workspace =
            (string) file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationWorkspaceReadModel.php'
            );

        $this->report =
            (string) file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiDiagnosticReportProjection.php'
            );
    }

    public function test_admin_workspace_exposes_diagnostic_report_projection(): void
    {
        self::assertStringContainsString(
            "'diagnostic_report' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            'DataTransformationBiDiagnosticReportProjection',
            $this->workspace
        );

        self::assertStringContainsString(
            "::fromEvaluation(\n"
            ."                        \$evaluation",
            $this->workspace
        );
    }

    public function test_report_receives_evaluation_not_live_source_payloads(): void
    {
        $start =
            strpos(
                $this->workspace,
                "'diagnostic_report' =>"
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
            'DataTransformationBiDiagnosticReportProjection',
            $block
        );

        self::assertStringContainsString(
            '$evaluation',
            $block
        );

        foreach ([
            '$sourcePayloads',
            'semantic_diagnostic',
            'domain_coverage',
            'cross_source_relationship_candidates',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }

    public function test_workspace_keeps_analysis_and_report_as_separate_layers(): void
    {
        $analysisPosition =
            strpos(
                $this->workspace,
                "'diagnostic_analysis' =>"
            );

        $reportPosition =
            strpos(
                $this->workspace,
                "'diagnostic_report' =>"
            );

        $evaluationPosition =
            strpos(
                $this->workspace,
                "'evaluation' =>"
            );

        self::assertNotFalse(
            $analysisPosition
        );

        self::assertNotFalse(
            $reportPosition
        );

        self::assertNotFalse(
            $evaluationPosition
        );

        self::assertLessThan(
            $reportPosition,
            $analysisPosition
        );

        self::assertLessThan(
            $evaluationPosition,
            $reportPosition
        );
    }

    public function test_workspace_preloads_relations_required_by_report(): void
    {
        self::assertStringContainsString(
            "'findings.sources'",
            $this->workspace
        );

        self::assertStringContainsString(
            "getRelation('findings')",
            $this->report
        );

        self::assertStringContainsString(
            "getRelation('sources')",
            $this->report
        );
    }

    public function test_workspace_integration_does_not_introduce_report_persistence(): void
    {
        foreach ([
            'report_snapshot',
            'report_sha',
            'report_schema_version',
            'readiness_score',
            'risk_score',
            'opportunity_score',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->workspace
            );
        }
    }


    public function test_admin_frontend_consumes_report_while_tenant_remains_unintegrated(): void
    {
        $root =
            dirname(
                __DIR__,
                3
            );

        $tenant =
            (string) file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantPublishedEvaluationProjection.php'
            );

        $adminUi =
            (string) file_get_contents(
                $root
                .'/resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        $tenantUi =
            (string) file_get_contents(
                $root
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        /*
         * R5 intentionally integrates the Admin presentation.
         */
        self::assertStringContainsString(
            'diagnostic_report: DataBiDiagnosticReport | null;',
            $adminUi
        );

        self::assertStringContainsString(
            'DATA_BI_DIAGNOSTIC_REPORT_ADMIN_UI',
            $adminUi
        );

        /*
         * Tenant remains on the separately sanitized published
         * projection and does not consume the Admin report contract.
         */
        self::assertStringNotContainsString(
            "'diagnostic_report' =>",
            $tenant
        );

        self::assertStringNotContainsString(
            'diagnostic_report:',
            $tenantUi
        );

        self::assertStringNotContainsString(
            'DATA_BI_DIAGNOSTIC_REPORT_ADMIN_UI',
            $tenantUi
        );
    }
}
