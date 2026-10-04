<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationImplementationChallengeWorkflowGateContractTest
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

    public function test_ready_and_publish_both_validate_implementation_challenges(): void
    {
        $source =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );

        self::assertSame(
            2,
            substr_count(
                $source,
                '$this->assertReviewableImplementationChallenges('
            )
        );

        self::assertSame(
            1,
            substr_count(
                $source,
                'private function assertReviewableImplementationChallenges('
            )
        );
    }

    public function test_challenges_are_optional_but_stale_challenges_are_blocked(): void
    {
        $source =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );

        foreach (
            [
                'DataTransformationBiEvaluationImplementationChallenge',
                '$challenges->isEmpty()',
                '$hasStaleChallenges',
                '(int) $challenge->evidence_version',
                '!== $currentEvidenceVersion',
                'Hay retos de implementación que no han sido confirmados contra la versión actual de la evidencia diagnóstica.',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_existing_finding_requirement_remains_unchanged(): void
    {
        $source =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );

        foreach (
            [
                'private function assertReviewableFindings(',
                '$findings->isEmpty()',
                'Registra al menos un hallazgo profesional antes de enviar la evaluación a revisión.',
                'Hay hallazgos que no han sido confirmados contra la versión actual de la evidencia diagnóstica.',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_ui_blocks_ready_only_for_stale_challenges_not_for_zero_challenges(): void
    {
        $source =
            $this->source(
                'resources/js/pages/Admin/'
                .'Transformation360/ImplementationRequests/'
                .'Show.vue'
            );

        self::assertStringContainsString(
            'evaluation.finding_count > 0',
            $source
        );

        self::assertStringContainsString(
            'evaluation.stale_finding_count === 0',
            $source
        );

        self::assertStringContainsString(
            'evaluation.stale_implementation_challenge_count === 0',
            $source
        );

        self::assertStringNotContainsString(
            'evaluation.implementation_challenge_count > 0',
            $source
        );

        self::assertStringContainsString(
            'Los hallazgos y retos de implementación '
            .'quedarán bloqueados para edición.',
            $source
        );
    }

    public function test_challenge_mutations_remain_draft_only(): void
    {
        $source =
            $this->source(
                'app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationImplementationChallengeService.php'
            );

        foreach (
            [
                'lockedDraftEvaluation(',
                'if (! $locked->isDraft())',
                'Los retos de implementación solo pueden gestionarse mientras la evaluación está en borrador.',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_no_duplicate_publication_snapshot_is_introduced(): void
    {
        $evaluation =
            $this->source(
                'app/Models/'
                .'DataTransformationBiEvaluation.php'
            );

        $migration =
            $this->source(
                'database/migrations/'
                .'2026_10_03_231500_create_data_transformation_bi_evaluation_implementation_challenges.php'
            );

        foreach (
            [
                'implementation_challenge_snapshot',
                'implementation_challenges_snapshot',
                'published_challenge_snapshot',
                'challenge_snapshot_sha256',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $evaluation
            );

            self::assertStringNotContainsString(
                $forbidden,
                $migration
            );
        }
    }

    public function test_r5_does_not_change_tenant_or_report_exposure(): void
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
            self::assertStringNotContainsString(
                'implementation_challenges',
                $source
            );
        }
    }
}
