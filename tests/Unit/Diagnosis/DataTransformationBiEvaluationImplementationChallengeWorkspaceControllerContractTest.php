<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationImplementationChallengeWorkspaceControllerContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(
        string $relative
    ): string {
        return file_get_contents(
            $this->root()
            .'/'
            .$relative
        );
    }

    public function test_evaluation_owns_separate_implementation_challenges(): void
    {
        $source =
            $this->source(
                'app/Models/'
                .'DataTransformationBiEvaluation.php'
            );

        $this->assertStringContainsString(
            'public function implementationChallenges(): HasMany',
            $source
        );

        $this->assertStringContainsString(
            'DataTransformationBiEvaluationImplementationChallenge::class',
            $source
        );

        $this->assertStringContainsString(
            "'data_transformation_bi_evaluation_id'",
            $source
        );
    }

    public function test_workspace_preloads_and_projects_challenges(): void
    {
        $source =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationWorkspaceReadModel.php'
            );

        foreach (
            [
                "'implementationChallenges.findings'",
                "'implementation_challenge_count' =>",
                "'stale_implementation_challenge_count' =>",
                "'implementation_challenges' =>",
                "'recommended_response' =>",
                "'finding_ids' =>",
                "'evidence_current' =>",
                "'can_manage_implementation_challenges' =>",
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_findings_remain_a_separate_authority(): void
    {
        $source =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationWorkspaceReadModel.php'
            );

        foreach (
            [
                "'finding_count' =>",
                "'stale_finding_count' =>",
                "'findings' =>",
                "'can_manage_findings' =>",
                "'diagnostic_report' =>",
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_controller_has_explicit_challenge_lifecycle(): void
    {
        $source =
            $this->source(
                'app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiEvaluationController.php'
            );

        foreach (
            [
                'createImplementationChallenge(',
                'updateImplementationChallenge(',
                'reconfirmImplementationChallenge(',
                'deleteImplementationChallenge(',
                'scopedImplementationChallenge(',
                'implementationChallengeInput(',
                'DataTransformationBiEvaluationImplementationChallengeService',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_input_contract_is_explicit_and_professional(): void
    {
        $source =
            $this->source(
                'app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiEvaluationController.php'
            );

        foreach (
            [
                "'title' =>",
                "'details' =>",
                "'recommended_response' =>",
                "'priority' =>",
                "'finding_ids' =>",
                "'finding_ids.*' =>",
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_admin_routes_register_four_challenge_endpoints(): void
    {
        $source =
            $this->source(
                'routes/admin.php'
            );

        foreach (
            [
                '/evaluation/implementation-challenges',
                'createImplementationChallenge',
                'updateImplementationChallenge',
                'reconfirmImplementationChallenge',
                'deleteImplementationChallenge',
                '.evaluation.implementation_challenges.create',
                '.evaluation.implementation_challenges.update',
                '.evaluation.implementation_challenges.reconfirm',
                '.evaluation.implementation_challenges.delete',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_challenge_specific_routes_constrain_challenge_id_as_numeric(): void
    {
        $source =
            $this->source(
                'routes/admin.php'
            );

        $startMarker =
            '/evaluation/implementation-challenges';

        $endMarker =
            '/evaluation/ready-for-review';

        $start =
            strpos(
                $source,
                $startMarker
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $source,
                $endMarker,
                (int) $start
            );

        self::assertNotFalse(
            $end
        );

        $challengeRoutes =
            substr(
                $source,
                (int) $start,
                (int) $end
                    - (int) $start
            );

        self::assertSame(
            3,
            substr_count(
                $challengeRoutes,
                '{challengeId}'
            )
        );

        self::assertSame(
            3,
            substr_count(
                $challengeRoutes,
                "->whereNumber('challengeId')"
            )
        );

        self::assertSame(
            0,
            substr_count(
                $challengeRoutes,
                "->whereNumber('findingId')"
            )
        );

        self::assertSame(
            4,
            substr_count(
                $challengeRoutes,
                "->whereNumber('sessionId')"
            )
        );
    }


    public function test_existing_finding_routes_remain_present(): void
    {
        $source =
            $this->source(
                'routes/admin.php'
            );

        foreach (
            [
                '/evaluation/findings',
                'createFinding',
                'updateFinding',
                'reconfirmFinding',
                'deleteFinding',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_r3_does_not_expose_challenges_to_tenant_or_report_yet(): void
    {
        $tenant =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiTenantPublishedEvaluationProjection.php'
            );

        $report =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiDiagnosticReportProjection.php'
            );

        $tenantUi =
            $this->source(
                'resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach (
            [
                $tenant,
                $report,
                $tenantUi,
            ]
            as $source
        ) {
            $this->assertStringNotContainsString(
                'implementation_challenges',
                $source
            );
        }
    }

    public function test_r3_has_no_roadmap_plan_or_commercial_write(): void
    {
        $controller =
            $this->source(
                'app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiEvaluationController.php'
            );

        foreach (
            [
                'DiagnosisDetailedRoadmap::',
                'TransformationImplementationPlan::',
                'Subscription::',
                'Invoice::',
                'Payment::',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $controller
            );
        }
    }
}
