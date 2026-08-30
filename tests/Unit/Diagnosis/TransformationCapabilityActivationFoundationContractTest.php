<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationCapabilityActivationFoundationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_migration_is_free_professional_activation_foundation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_08_30_190000_create_transformation_capability_activations_table.php'
        );

        foreach ([
            "Schema::create(",
            "'transformation_capability_activations'",
            "'company_id'",
            "'diagnosis_assessment_id'",
            "'capability_key'",
            "'source_type'",
            "'source_id'",
            "'source_version'",
            "'source_snapshot'",
            "'status'",
            "'activated_by_user_id'",
            "'activated_at'",
            "'started_at'",
            "'ready_for_review_at'",
            "'validated_at'",
            "'completed_at'",
            "'cancelled_at'",
            "'tca_assessment_capability_uq'",
            "'tca_company_fk'",
            "'tca_assessment_fk'",
            "'tca_activated_user_fk'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            "'price'",
            "'currency'",
            "'modality'",
            "'order_id'",
            "'invoice_id'",
            "'payment_id'",
            "'subscription_id'",
            "'subscription_item_id'",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_model_has_generic_lifecycle_without_commercial_state(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Models/'
            .'TransformationCapabilityActivation.php'
        );

        foreach ([
            "SOURCE_DETAILED_ROADMAP = 'detailed_roadmap'",
            "STATUS_ACTIVATED = 'activated'",
            "STATUS_IN_PROGRESS = 'in_progress'",
            "STATUS_READY_FOR_REVIEW = 'ready_for_review'",
            "STATUS_VALIDATED = 'validated'",
            "STATUS_COMPLETED = 'completed'",
            "STATUS_CANCELLED = 'cancelled'",
            'function company()',
            'function assessment()',
            'function activatedBy()',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_service_only_activates_professional_non_subscription_capabilities(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        foreach ([
            'activateFromRoadmap(',
            'TransformationProfessionalCapabilityCatalog::get(',
            "'professional_service'",
            "'service_key'",
            "'subscription_candidate'",
            'assertAssessmentCompany(',
            'assertPublishedRoadmap(',
            "'recommended'",
            "'free_activation_contract'",
            "'free' => true",
            "'commercial_acceptance' =>",
            "'requires_modality' =>",
            "'requires_payment' =>",
            "'creates_order' =>",
            "'creates_invoice' =>",
            "'creates_payment' =>",
            "'creates_subscription' =>",
            "'creates_subscription_item' =>",
            "'creates_go_live' =>",
            'lockForUpdate()',
            'transformation_capability_activated_free',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_activation_is_idempotent_per_assessment_and_capability(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_08_30_190000_create_transformation_capability_activations_table.php'
        );

        $service = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        $this->assertStringContainsString(
            "'diagnosis_assessment_id',",
            $migration
        );

        $this->assertStringContainsString(
            "'capability_key',",
            $migration
        );

        $this->assertStringContainsString(
            "'tca_assessment_capability_uq'",
            $migration
        );

        $this->assertStringContainsString(
            'if ($existing)',
            $service
        );

        $this->assertStringContainsString(
            'return $existing->fresh();',
            $service
        );
    }

    public function test_existing_domain_models_expose_activation_relationships(): void
    {
        $files = [
            'app/Models/Company.php' =>
                'transformationCapabilityActivations',
            'app/Models/DiagnosisAssessment.php' =>
                'transformationCapabilityActivations',
            'app/Models/DiagnosisDetailedRoadmap.php' =>
                'transformationCapabilityActivations',
        ];

        foreach ($files as $file => $token) {
            $source = file_get_contents(
                $this->root().'/'.$file
            );

            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_no_subscription_activation_service_is_used(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        foreach ([
            'TransformationImplementationSubscriptionService',
            'TransformationImplementationCapabilitySubscriptionService',
            'TransformationImplementationPostGoLiveServiceActivationService',
            'TransformationImplementationSubscriptionActivation',
            'TransformationImplementationSubscriptionItemActivation',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }
}
