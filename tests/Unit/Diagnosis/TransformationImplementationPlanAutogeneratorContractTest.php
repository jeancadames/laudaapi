<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanAutogeneratorContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_autogenerator_exists_and_supports_both_sources(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        foreach ([
            "published_roadmap",
            "internal_assessment",
            "published_roadmap'",
            "internal_roadmap'",
            'public function preview(',
            'public function generate(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_generator_builds_plan_before_commercial_modality(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        foreach ([
            "'phases'",
            "'initiative_ids'",
            "'initiatives'",
            "'dependencies'",
            "'deliverables'",
            "'capabilities'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'upsertEstimate(',
            'upsertMilestone(',
            'Subscription::create(',
            'SubscriptionItem::create(',
            'Invoice::create(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_saas_solutions_do_not_autogenerate_into_transformation_plan(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        $this->assertStringContainsString(
            'TransformationProfessionalCapabilityCatalog::all()',
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

    public function test_legacy_laudaone_is_excluded_because_all_saas_is_outside_transformation_plan(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'TransformationImplementationPlanAutogenerator.php'
        );

        $this->assertStringContainsString(
            'TransformationProfessionalCapabilityCatalog::all()',
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

    public function test_procedures_guide_is_professional_non_recurring(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationProfessionalCapabilityCatalog.php'
        );

        foreach ([
            "'procedures_guide'",
            "'professional_service'",
            "'subscription_candidate'",
            "'recurring'",
            "'creates_subscription'",
            "'creates_subscription_item'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_controller_autogenerates_new_and_empty_existing_drafts(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminTransformationImplementationPlanController.php'
        );

        foreach ([
            'TransformationImplementationPlanAutogenerator',
            '$autogenerator->generate(',
            'creado y autogenerado como borrador',
            'autogenerado desde su fuente',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
