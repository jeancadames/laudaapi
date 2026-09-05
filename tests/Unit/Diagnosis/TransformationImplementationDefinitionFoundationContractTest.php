<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionFoundationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_definition_is_non_commercial(): void
    {
        $model =
            file_get_contents(
                $this->root()
                .'/app/Models/'
                .'TransformationImplementationDefinition.php'
            );

        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationDefinitionService.php'
            );

        foreach ([
            "'implementation_scope'",
            "'deliverables'",
            "'dependencies'",
            "'responsibility_model'",
            "'readiness'",
            "STATUS_DRAFT",
            "STATUS_UNDER_REVIEW",
            "STATUS_READY",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }

        foreach ([
            'price_amount',
            'subtotal_amount',
            'tax_amount',
            'total_amount',
            'currency',
            'selected_modality',
            'commercial_terms',
            'accepted_at',
            'declined_at',
            'billing_amount',
            'invoice_id',
            'payment_id',
            'subscription_id',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $model
            );
        }

        foreach ([
            'CommercialRate',
            'CommercialCalculator',
            'PricingService',
            'price_amount',
            'selected_modality',
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $service
            );
        }
    }

    public function test_definition_snapshots_presented_plan(): void
    {
        $service =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationDefinitionService.php'
            );

        foreach ([
            'createOrGetDraftFromPresentedPlan(',
            'STATUS_PRESENTED',
            "'presented_implementation_plan'",
            "'plan_id'",
            "'plan_version'",
            "'phases'",
            "'capabilities'",
            "'commercial_context_attached' =>",
            "'pricing_attached' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }

    public function test_old_proposal_layer_is_removed(): void
    {
        foreach ([
            'TransformationImplementationProposal.php',
            'TransformationImplementationProposalPhaseEstimate.php',
            'TransformationImplementationProposalMilestone.php',
        ] as $file) {
            $this->assertFileDoesNotExist(
                $this->root()
                .'/app/Models/'
                .$file
            );
        }

        $this->assertFileDoesNotExist(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationProposalPricingService.php'
        );
    }
}
