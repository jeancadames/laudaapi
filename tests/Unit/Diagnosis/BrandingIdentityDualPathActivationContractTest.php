<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class BrandingIdentityDualPathActivationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_company_owned_activation_migration_separates_optional_context(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_08_30_220000_make_capability_activation_company_owned.php'
        );

        foreach ([
            "dropUnique('tca_assessment_capability_uq')",
            "'company_id'",
            "'capability_key'",
            "'tca_company_capability_uq'",
            "'diagnosis_assessment_id'",
            "'source_type'",
            "'source_id'",
            '->nullable()',
            '->nullOnDelete()',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_decision_table_separates_recommendation_from_activation(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_08_30_220100_create_transformation_capability_decisions_table.php'
        );

        foreach ([
            "'transformation_capability_decisions'",
            "'company_id'",
            "'diagnosis_assessment_id'",
            "'capability_key'",
            "'recommendation_status'",
            "'decision'",
            "->default('pending')",
            "'source_type'",
            "'source_id'",
            "'source_version'",
            "'source_snapshot'",
            "'decided_by_user_id'",
            "'decided_at'",
            "'tcd_assessment_capability_uq'",
        ] as $token) {
            $this->assertStringContainsString($token, $migration);
        }

        $model = file_get_contents(
            $this->root()
            .'/app/Models/TransformationCapabilityDecision.php'
        );

        foreach ([
            "RECOMMENDATION_RECOMMENDED = 'recommended'",
            "RECOMMENDATION_NOT_RECOMMENDED = 'not_recommended'",
            "DECISION_PENDING = 'pending'",
            "DECISION_ACCEPTED = 'accepted'",
            "DECISION_DECLINED = 'declined'",
        ] as $token) {
            $this->assertStringContainsString($token, $model);
        }
    }

    public function test_decline_records_decision_without_creating_activation(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityDecisionService.php'
        );

        foreach ([
            'declineFromRoadmap(',
            'DECISION_DECLINED',
            "(\$definition['recommended'] ?? false) !== true",
            'TransformationCapabilityActivation::query()',
            "->where('company_id', \$company->id)",
            "->where('capability_key', trim(\$capabilityKey))",
            'TransformationCapabilityDecision::query()',
            "'decision' => \$decision",
            'transformation_capability_tenant_decision',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        foreach ([
            'TransformationCapabilityActivation::create(',
            'activateManually(',
            'activateFromRoadmap(',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }

    public function test_activation_has_recommendation_path_and_manual_optional_path(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Services/Diagnosis/'
            .'TransformationCapabilityActivationService.php'
        );

        foreach ([
            'activateFromRoadmap(',
            'activateManually(',
            'SOURCE_DETAILED_ROADMAP',
            'SOURCE_MANUAL',
            "->where('company_id', \$company->id)",
            "->where('capability_key', \$capabilityKey)",
            '$this->decisions->acceptFromRoadmap(',
            "'manual' =>",
            "'roadmap' => \$roadmapDefinition ?? []",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            'La capacidad debe estar recomendada en el Roadmap publicado antes de activarse.',
            $source
        );
    }

    public function test_transformacion_360_exposes_manual_selection_and_recommendation_context(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'SubscriberTransformation360DashboardService.php'
        );

        foreach ([
            "'optional_capabilities'",
            "'branding_identity'",
            "'optional' => true",
            "'recommended' => \$recommended",
            "'decision' => \$decision",
            "'can_activate' => ! \$activated",
            "'activation_endpoint'",
            "'decline_endpoint'",
            "'app.transformation.capabilities.branding_identity.activate'",
        ] as $token) {
            $this->assertStringContainsString($token, $service);
        }

        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/Transformation360.vue'
        );

        foreach ([
            'OptionalBrandingCapability',
            'optional_capabilities',
            'Recomendado por tu Diagnóstico 360',
            'Opcional',
            'Iniciar evaluación',
            'Ahora no',
            'puedes activar Branding cuando',
            'router.post(',
        ] as $token) {
            $this->assertStringContainsString($token, $page);
        }
    }

    public function test_manual_activation_does_not_require_diagnosis_or_commercial_objects(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/'
            .'AppHubBrandingActivationController.php'
        );

        foreach ([
            'TenantAccessService::SUBSCRIBER_ADMIN',
            '$companyResolver->resolve(',
            'activateFromRoadmap(',
            'activateManually(',
            "'branding_identity'",
        ] as $token) {
            $this->assertStringContainsString($token, $controller);
        }

        foreach ([
            'Order::',
            'Invoice::',
            'Payment::',
            'Subscription::',
            'SubscriptionItem::',
            'requires_payment',
            'modality',
        ] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }
    }

    public function test_manual_workspace_source_and_plan_context_are_nullable(): void
    {
        $workspace = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityWorkspaceService.php'
        );

        foreach ([
            '$assessmentId = $activation->diagnosis_assessment_id !== null',
            '$sourceId = $activation->source_id !== null',
            '$roadmapUrl = $assessmentId !== null',
            'SOURCE_DETAILED_ROADMAP',
            "'roadmap_url' =>",
        ] as $token) {
            $this->assertStringContainsString($token, $workspace);
        }

        $plan = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'BrandingIdentityPlanContextService.php'
        );

        foreach ([
            'if (! $activation->diagnosis_assessment_id)',
            'iniciada de forma opcional por el tenant',
            'no depende de un Plan consultivo de origen',
        ] as $token) {
            $this->assertStringContainsString($token, $plan);
        }
    }

    public function test_sidebar_remains_activation_driven_not_recommendation_driven(): void
    {
        $sidebar = file_get_contents(
            $this->root()
            .'/resources/js/components/AppSidebar.vue'
        );

        foreach ([
            'active_capabilities',
            "hasActiveCapability('branding_identity')",
            "lauda360Hrefs.push('/app/branding-identidad')",
        ] as $token) {
            $this->assertStringContainsString($token, $sidebar);
        }

        $middleware = file_get_contents(
            $this->root()
            .'/app/Http/Middleware/HandleInertiaRequests.php'
        );

        foreach ([
            'TransformationCapabilityActivation::query()',
            "'company_id'",
            'TransformationCapabilityActivation::STATUS_CANCELLED',
            "'active_capabilities'",
        ] as $token) {
            $this->assertStringContainsString($token, $middleware);
        }
    }
}
