<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisFreeDeliverablesAutogenerationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_diagnosis_publication_triggers_free_deliverables_orchestrator(): void
    {
        $publisher = file_get_contents(
            $this->root().'/app/Services/Diagnosis/DiagnosisResultPublisher.php'
        );

        foreach ([
            'DiagnosisFreeDeliverablesOrchestrator $deliverables',
            '$this->deliverables->generateAndPresent(',
            "'free_deliverables_generated' =>",
        ] as $token) {
            $this->assertStringContainsString($token, $publisher);
        }
    }

    public function test_orchestrator_generates_publishes_and_presents_in_sequence(): void
    {
        $source = file_get_contents(
            $this->root().'/app/Services/Diagnosis/DiagnosisFreeDeliverablesOrchestrator.php'
        );

        $tokens = [
            '$this->expandedReports->createOrGetDraft(',
            '$this->expandedReports->publish(',
            '$this->roadmaps->createOrGetDraft(',
            '$this->roadmaps->publish(',
            '$this->plans->createDraftFromPublishedRoadmap(',
            '$this->autogenerator->generate(',
            '$this->plans->markPresented(',
        ];

        $last = -1;

        foreach ($tokens as $token) {
            $position = strpos($source, $token);
            $this->assertNotFalse($position, $token);
            $this->assertGreaterThan($last, $position, $token);
            $last = $position;
        }
    }

    public function test_orchestrator_is_idempotent_and_has_no_commercial_side_effects(): void
    {
        $source = file_get_contents(
            $this->root().'/app/Services/Diagnosis/DiagnosisFreeDeliverablesOrchestrator.php'
        );

        foreach ([
            "DiagnosisExpandedReport::STATUS_PUBLISHED",
            "DiagnosisDetailedRoadmap::STATUS_PUBLISHED",
            '$this->plans->latestForAssessment(',
            "TransformationImplementationPlan::STATUS_DRAFT",
            "'purchase_required' => false",
            "'payment_required' => false",
            "'modality_required' => false",
            "'subscription_created' => false",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        foreach ([
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'CommercialService',
            'CommercialEngine',
            'ModalityService',
            'MilestoneBillingService',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
