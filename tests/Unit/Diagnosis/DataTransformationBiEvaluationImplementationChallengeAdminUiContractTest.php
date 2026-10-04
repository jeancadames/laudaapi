<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationImplementationChallengeAdminUiContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function adminUi(): string
    {
        return file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/'
            .'Transformation360/ImplementationRequests/'
            .'Show.vue'
        );
    }

    public function test_ui_models_challenges_separately_from_findings(): void
    {
        $source =
            $this->adminUi();

        foreach (
            [
                'type DataBiEvaluationImplementationChallenge =',
                'type DataBiEvaluationImplementationChallengeForm =',
                'implementation_challenge_count: number;',
                'stale_implementation_challenge_count: number;',
                'implementation_challenges: DataBiEvaluationImplementationChallenge[];',
                'can_manage_implementation_challenges: boolean;',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        $this->assertStringContainsString(
            "finding_type: 'weakness' | 'opportunity' | 'observation';",
            $source
        );
    }

    public function test_admin_has_explicit_challenge_authoring_lifecycle(): void
    {
        $source =
            $this->adminUi();

        foreach (
            [
                'resetDataBiEvaluationImplementationChallengeForm',
                'editDataBiEvaluationImplementationChallenge',
                'saveDataBiEvaluationImplementationChallenge',
                'deleteDataBiEvaluationImplementationChallenge',
                'reconfirmDataBiEvaluationImplementationChallenge',
                '/implementation-challenges',
                'implementation-challenge:create',
                'implementation-challenge:update:',
                'implementation-challenge:delete:',
                'implementation-challenge:reconfirm:',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_form_contains_only_professional_fields_and_optional_finding_links(): void
    {
        $source =
            $this->adminUi();

        foreach (
            [
                'dataBiEvaluationImplementationChallengeForm',
                '.recommended_response',
                '.priority',
                '.finding_ids',
                'Hallazgos relacionados',
                'Vincular hallazgos es',
                'opcional',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_ui_states_that_challenges_are_not_automatic(): void
    {
        $source =
            $this->adminUi();

        foreach (
            [
                'Retos de implementación',
                'nunca se',
                'generan automáticamente',
                'independientes de los',
                'hallazgos diagnósticos',
                'no crea el reto',
                'copia automáticamente',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_stale_challenges_can_be_reconfirmed_explicitly(): void
    {
        $source =
            $this->adminUi();

        foreach (
            [
                'stale_implementation_challenge_count',
                '!challenge.evidence_current',
                'Requiere reconfirmación',
                'reconfirmDataBiEvaluationImplementationChallenge',
                '/reconfirm',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_linked_findings_remain_traceability_only(): void
    {
        $source =
            $this->adminUi();

        $this->assertStringContainsString(
            'challenge.finding_ids',
            $source
        );

        $this->assertStringContainsString(
            'dataBiEvaluationLinkedFindingLabel',
            $source
        );

        $this->assertStringContainsString(
            'finding.evidence_current',
            $source
        );
    }

    public function test_r4_does_not_touch_tenant_or_diagnostic_report_authority(): void
    {
        $tenantProjection =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiTenantPublishedEvaluationProjection.php'
            );

        $report =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiDiagnosticReportProjection.php'
            );

        $tenantUi =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/App/'
                .'DataTransformationBi.vue'
            );

        foreach (
            [
                $tenantProjection,
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

    public function test_r4_has_no_roadmap_plan_or_commercial_authority(): void
    {
        $source =
            $this->adminUi();

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
                $source
            );
        }
    }
}
