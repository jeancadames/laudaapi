<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionHumanReviewContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationDefinitionReviewService.php'
        );
    }

    public function test_review_can_confirm_functional_definition_content(): void
    {
        $source =
            $this->source();

        foreach ([
            'saveReview(',
            "'implementation_scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibility_model'",
            "'readiness'",
            "'reviewed_by_user_id'",
            "'reviewed_at'",
            'STATUS_UNDER_REVIEW',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_human_review_explicitly_validates_readiness(): void
    {
        $source =
            $this->source();

        foreach ([
            "'scope_confirmed'",
            "'deliverables_confirmed'",
            "'dependencies_confirmed'",
            "'inputs_validated'",
            "'accesses_validated'",
            "'responsibilities_confirmed'",
            "'human_validation'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_responsibility_party_requires_human_confirmation(): void
    {
        $source =
            $this->source();

        foreach ([
            "'lauda'",
            "'client'",
            "'shared'",
            "'responsible_party'",
            "'confirmation_status' =>",
            "'confirmed'",
            "'party_assignment_status' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_ready_definition_does_not_start_execution(): void
    {
        $source =
            $this->source();

        foreach ([
            'markReady(',
            'STATUS_READY',
            "'technical_readiness'",
            "'ready_for_execution'",
            'false',
            "'execution_started'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'TransformationImplementationPhaseExecution::create',
            'TransformationImplementationCapabilityExecution::create',
            'GoLive',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_review_layer_has_no_commercial_semantics(): void
    {
        $source =
            $this->source();

        foreach ([
            'price_amount',
            'subtotal_amount',
            'tax_amount',
            'total_amount',
            'selected_modality',
            'CommercialRate',
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
            'billing_amount',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
