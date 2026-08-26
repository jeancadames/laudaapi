<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class TransformationImplementationPlanUiFoundationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_routes_exist(): void
    {
        $admin = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        $web = file_get_contents(
            $this->root().'/routes/web.php'
        );

        foreach ([
            'implementation_plan.show',
            'implementation_plan.create',
            'AdminTransformationImplementationPlanController',
        ] as $token) {
            $this->assertStringContainsString($token, $admin);
        }

        $this->assertStringContainsString(
            'implementation_plan.show',
            $web
        );

        $this->assertStringContainsString(
            'TransformationImplementationPlanController',
            $web
        );
    }

    public function test_admin_uses_published_roadmap_when_available_or_internal_assessment(): void
    {
        $source = file_get_contents(
            $this->root().'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php'
        );
        foreach (['publishedRoadmap(', 'createDraftFromPublishedRoadmap(', 'createDraftFromAssessment(', 'latestForAssessment(', 'DiagnosisAccessService'] as $token) {
            $this->assertStringContainsString($token, $source);
        }
        $this->assertStringNotContainsString(
            'Debe existir un Roadmap Detallado publicado antes de crear el Plan.',
            $source
        );
    }

    public function test_client_does_not_expose_draft_plan(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            "Gate::authorize('view',",
            'STATUS_PRESENTED',
            'STATUS_ACCEPTED',
            'STATUS_ACTIVE',
            'STATUS_COMPLETED',
            "whereNotNull('presented_at')",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            'STATUS_DRAFT,',
            $source
        );
    }

    public function test_navigation_is_connected(): void
    {
        $quick = file_get_contents(
            $this->root()
            .'/resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        $roadmap = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/DetailedRoadmap.vue'
        );

        $this->assertStringContainsString(
            'Gestionar Plan de Implementación',
            $quick
        );

        $this->assertStringContainsString(
            'Ver Plan de Implementación',
            $roadmap
        );
    }

    public function test_ui_does_not_activate_subscription(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php'
        )
        .file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/TransformationImplementationPlanController.php'
        );

        foreach ([
            'Subscription::create',
            'SubscriptionItem::create',
            'activateFromGoLive(',
        ] as $token) {
            $this->assertStringNotContainsString($token, $source);
        }
    }
}
