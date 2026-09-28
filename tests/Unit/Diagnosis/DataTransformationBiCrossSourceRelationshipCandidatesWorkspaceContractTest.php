<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiCrossSourceRelationshipCandidatesWorkspaceContractTest
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

    public function test_workspace_exposes_relationship_candidates_from_safe_source_payloads(): void
    {
        self::assertStringContainsString(
            "'cross_source_relationship_candidates' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            'DataTransformationBiCrossSourceRelationshipCandidatesReadModel',
            $this->workspace
        );

        self::assertStringContainsString(
            '$sourcePayloads',
            $this->workspace
        );
    }

    public function test_relationship_candidates_do_not_replace_domain_coverage_or_semantic_inventory(): void
    {
        self::assertStringContainsString(
            "'semantic_diagnostic' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            "'domain_coverage' =>",
            $this->workspace
        );

        self::assertStringContainsString(
            "'cross_source_relationship_candidates' =>",
            $this->workspace
        );
    }
}
