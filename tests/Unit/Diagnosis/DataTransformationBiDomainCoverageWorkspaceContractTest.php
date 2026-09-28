<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDomainCoverageWorkspaceContractTest
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

    public function test_admin_workspace_exposes_domain_coverage_from_safe_source_payloads(): void
    {
        self::assertStringContainsString(
            "'domain_coverage' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            'DataTransformationBiDomainCoverageReadModel',
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

    public function test_domain_coverage_does_not_replace_semantic_inventory(): void
    {
        self::assertStringContainsString(
            "'semantic_diagnostic' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            "'domain_coverage' =>",
            $this->workspace
        );
    }
}
