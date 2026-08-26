<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanFoundationContractTest extends TestCase
{
    public function test_migration_creates_only_plan_foundation(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/database/migrations/2026_08_24_150000_create_transformation_implementation_plans_table.php'
        );

        foreach ([
            "Schema::create('transformation_implementation_plans'",
            "'diagnosis_assessment_id'",
            "'diagnosis_detailed_roadmap_id'",
            "'recommended_modality'",
            "'selected_modality'",
            "'source_snapshot'",
            "'presented_at'",
            "'accepted_at'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            "'company_id'",
            "'subscriber_id'",
            "'subscription_id'",
            "'go_live_at'",
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_model_defines_three_lauda_360_modalities(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Models/TransformationImplementationPlan.php'
        );

        foreach ([
            "MODALITY_GUIDED = 'guided'",
            "MODALITY_ASSISTED = 'assisted'",
            "MODALITY_MANAGED = 'managed'",
            "'LAUDA 360 Guiado'",
            "'LAUDA 360 Asistido'",
            "'LAUDA 360 Gestionado'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_plan_can_be_created_from_official_assessment_or_published_roadmap(): void
    {
        // plan can be created from official assessment or published roadmap
        $service = file_get_contents(
            dirname(__DIR__, 3).'/app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );
        $this->assertStringContainsString('createDraftFromAssessment', $service);
        $this->assertStringContainsString('createDraftFromPublishedRoadmap', $service);
        $this->assertStringContainsString("'internal_assessment'", $service);
        $this->assertStringContainsString("'published_roadmap'", $service);
    }

    public function test_foundation_does_not_create_subscription_or_company(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );

        foreach ([
            'Subscription::create',
            'Subscriber::create',
            'Company::create',
            'SubscriptionItem::create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_recommended_and_selected_modalities_are_separate(): void
    {
        $root = dirname(__DIR__, 3);

        $model = file_get_contents(
            $root
            . '/app/Models/TransformationImplementationPlan.php'
        );

        foreach ([
            "'recommended_modality'",
            "'recommended_modality_label'",
            "'selected_modality'",
            "'selected_modality_label'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }
    }
}
