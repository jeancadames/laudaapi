<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSemanticDiagnosticWorkspaceContractTest
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

    public function test_admin_workspace_exposes_dynamic_domain_classification(): void
    {
        self::assertStringContainsString(
            "'business_domains' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            "'semantic_diagnostic' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            'DataTransformationBiSemanticDiagnosticReadModel',
            $this->workspace
        );
    }

    public function test_workspace_reuses_safe_source_payload_for_semantic_inventory(): void
    {
        self::assertStringContainsString(
            '$sourcePayloads =',
            $this->workspace
        );

        self::assertStringContainsString(
            '::fromSources(',
            $this->workspace
        );

        self::assertStringContainsString(
            '$sourcePayloads',
            $this->workspace
        );
    }

    public function test_b1_does_not_modify_evaluation_findings_or_future_pipeline(): void
    {
        self::assertStringNotContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->workspace
        );

        self::assertStringNotContainsString(
            'DataTransformationBiStaging',
            $this->workspace
        );
    }
}
