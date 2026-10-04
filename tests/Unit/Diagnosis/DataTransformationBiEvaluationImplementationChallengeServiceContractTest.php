<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiEvaluationImplementationChallengeServiceContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function serviceSource(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiEvaluationImplementationChallengeService.php'
        );
    }

    public function test_lifecycle_is_explicitly_human_authored(): void
    {
        $source =
            $this->serviceSource();

        foreach (
            [
                'public function create(',
                'public function update(',
                'public function reconfirm(',
                'public function delete(',
                'lockedDraftEvaluation(',
                'lockedChallenge(',
                'normalizeInput(',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach (
            [
                'deriveChallenge(',
                'generateChallenge(',
                'automaticChallenge(',
                'automatic_challenge',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_mutation_is_limited_to_draft_evaluation(): void
    {
        $source =
            $this->serviceSource();

        $this->assertStringContainsString(
            '! $locked->isDraft()',
            $source
        );

        $this->assertStringContainsString(
            'solo pueden gestionarse mientras la evaluación está en borrador',
            $source
        );

        $this->assertStringContainsString(
            'lockForUpdate()',
            $source
        );
    }

    public function test_finding_traceability_requires_same_current_evidence(): void
    {
        $source =
            $this->serviceSource();

        foreach (
            [
                'assertCurrentFindings(',
                "'data_transformation_bi_evaluation_id'",
                "'company_id'",
                "'evidence_version'",
                'DataTransformationBiEvaluationFinding',
                'Todos los hallazgos relacionados deben pertenecer a esta evaluación',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_linked_findings_are_never_reconfirmed_automatically(): void
    {
        $source =
            $this->serviceSource();

        foreach (
            [
                'DataTransformationBiEvaluationFindingService',
                'reconfirmFinding(',
                'finding->forceFill(',
                'finding->update(',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        $this->assertStringContainsString(
            'This service never reconfirms or mutates findings automatically.',
            $source
        );
    }

    public function test_challenge_reconfirmation_only_re_pins_challenge(): void
    {
        $source =
            $this->serviceSource();

        $this->assertStringContainsString(
            "'evidence_version' =>",
            $source
        );

        $this->assertStringContainsString(
            'data_transformation_bi_implementation_challenge_reconfirmed',
            $source
        );

        $this->assertStringContainsString(
            '$this->assertCurrentFindings(',
            $source
        );
    }

    public function test_priority_is_professional_and_controlled(): void
    {
        $source =
            $this->serviceSource();

        $this->assertStringContainsString(
            'DataTransformationBiEvaluationImplementationChallenge',
            $source
        );

        $this->assertStringContainsString(
            '::PRIORITIES',
            $source
        );

        $this->assertStringContainsString(
            'La prioridad profesional indicada no es válida.',
            $source
        );
    }

    public function test_lifecycle_is_audited_without_raw_professional_text(): void
    {
        $source =
            $this->serviceSource();

        foreach (
            [
                'data_transformation_bi_implementation_challenge_created',
                'data_transformation_bi_implementation_challenge_updated',
                'data_transformation_bi_implementation_challenge_reconfirmed',
                'data_transformation_bi_implementation_challenge_deleted',
                'AuditService::log(',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        /*
         * Audit payload contains metadata, not title/details/response text.
         */
        $auditStart =
            strpos(
                $source,
                'private function audit('
            );

        $this->assertNotFalse(
            $auditStart
        );

        $auditSource =
            substr(
                $source,
                (int) $auditStart
            );

        foreach (
            [
                "'title' =>",
                "'details' =>",
                "'recommended_response' =>",
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $auditSource
            );
        }
    }

    public function test_service_has_no_roadmap_plan_or_commercial_write(): void
    {
        $source =
            $this->serviceSource();

        foreach (
            [
                'DiagnosisDetailedRoadmap',
                'TransformationImplementationPlan',
                'TransformationImplementationPhase',
                'Subscription::',
                'SubscriptionItem::',
                'Invoice::',
                'Payment::',
                'Service::create(',
                'roadmap_id',
                'implementation_plan_id',
                'commercial_readiness',
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_service_does_not_use_raw_source_or_profiling_data(): void
    {
        $source =
            $this->serviceSource();

        foreach (
            [
                'profiling_snapshot',
                'source_snapshot',
                'raw_value',
                'sample_value',
                'source_object_name',
                'canonical_mapping',
                'confirmed_join',
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
