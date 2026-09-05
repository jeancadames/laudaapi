<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationDefinitionAutogeneratorContractTest
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
            .'TransformationImplementationDefinitionAutogenerator.php'
        );
    }

    public function test_generator_uses_plan_and_professional_catalog_as_sources(): void
    {
        $source =
            $this->source();

        foreach ([
            'source_snapshot',
            "'phases'",
            "'initiatives'",
            "'actions'",
            "'dependencies'",
            "'deliverables'",
            'TransformationProfessionalCapabilityCatalog::get(',
            "'purpose'",
            "'scope_items'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_responsibility_model_preserves_suggestion_semantics(): void
    {
        $source =
            $this->source();

        foreach ([
            "'suggested_owner_role'",
            "'confirmation_status' =>",
            "'pending'",
            "'confirmation_required' =>",
            "'party_assignment_status' =>",
            "'to_be_defined'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            "'party' => 'lauda'",
            "'party' => 'client'",
            "'party' => 'shared'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_readiness_is_conservative_and_requires_human_validation(): void
    {
        $source =
            $this->source();

        foreach ([
            "'prepared_for_review'",
            "'ready_for_execution' =>",
            'false',
            "'human_review_required' =>",
            "'inputs_validated' =>",
            "'accesses_validated' =>",
            "'responsibilities_confirmed' =>",
            'null',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_generator_contains_no_commercial_semantics(): void
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
            'CommercialCalculator',
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

    public function test_generate_only_updates_definition_preparation_fields(): void
    {
        $source =
            $this->source();

        foreach ([
            'function preview(',
            'function generate(',
            "'implementation_scope' =>",
            "'deliverables' =>",
            "'dependencies' =>",
            "'responsibility_model' =>",
            "'readiness' =>",
            'isEditable()',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
