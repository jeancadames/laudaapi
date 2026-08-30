<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationTransformationOnlyPlanContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_plan_generator_uses_only_professional_capabilities(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        $this->assertStringContainsString(
            'TransformationProfessionalCapabilityCatalog::all()',
            $source
        );

        $this->assertStringContainsString(
            "'professional_service'",
            $source
        );

        $this->assertStringContainsString(
            "'subscription_candidate' =>",
            $source
        );

        $this->assertStringNotContainsString(
            'TransformationServiceCapabilityCatalog::all()',
            $source
        );

        $this->assertStringNotContainsString(
            'critical_or_high_linked_initiative',
            $source
        );

    }

    public function test_roadmap_phase_is_not_dropped_when_no_professional_service_exists(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        $this->assertStringNotContainsString(
            '$phaseCapabilities->isEmpty()',
            $source
        );

        $this->assertStringContainsString(
            "'allow_empty_capabilities' =>",
            $source
        );

        $phaseService = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPhaseService.php'
        );

        $this->assertStringContainsString(
            'allow_empty_capabilities',
            $phaseService
        );
    }

    public function test_normal_manual_phase_creation_still_requires_a_capability(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPhaseService.php'
        );

        $this->assertStringContainsString(
            '! $allowEmptyCapabilities',
            $source
        );
    }

    public function test_draft_regeneration_invalidates_structure_dependent_state(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        foreach ([
            'public function regenerate(',
            "'selected_modality' =>",
            "'selected_modality_label' =>",
            '$locked->phases()->delete()',
            'transformation_implementation_milestones',
            'transformation_implementation_phase_executions',
            'transformation_implementation_capability_executions',
            'transformation_implementation_capability_go_lives',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_regeneration_has_no_billing_or_subscription_creation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        $start = strpos(
            $source,
            'public function regenerate('
        );

        $end = strpos(
            $source,
            'private function recommendedCapabilities(',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $method = substr(
            $source,
            $start,
            $end - $start
        );

        foreach ([
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'invoice_reference',
            'payment_reference',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $method
            );
        }
    }

    public function test_admin_ui_explains_solution_separation_and_regeneration(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'ImplementationPlan.vue'
        );

        foreach ([
            'Regenerar estructura desde la fuente',
            'regeneratePlanStructure',
            'props.endpoints.regenerate',
            'Las soluciones',
            'Esta fase no requiere un servicio profesional adicional.',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_legacy_manual_admin_endpoint_only_accepts_professional_services(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationPlanController.php'
        );

        $this->assertStringContainsString(
            "=== 'professional_service'",
            $source
        );
    }
}
