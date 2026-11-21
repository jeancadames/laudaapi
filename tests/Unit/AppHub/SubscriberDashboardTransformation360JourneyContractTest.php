<?php

namespace Tests\Unit\AppHub;

use PHPUnit\Framework\TestCase;

final class SubscriberDashboardTransformation360JourneyContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function service(): string
    {
        return file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'SubscriberTransformation360DashboardService.php'
        );
    }

    public function test_dashboard_has_company_scoped_t360_read_model(): void
    {
        $source = $this->service();

        foreach ([
            'diagnosis_access_requests',
            'diagnosis_assessments',
            'diagnosis_expanded_reports',
            'diagnosis_detailed_roadmaps',
            'transformation_implementation_plans',
            "'$.company_id'",
            "'$.subscriber_id'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_only_tenant_owner_or_admin_can_see_t360(): void
    {
        $source = $this->service();

        foreach ([
            '$company->owner_user_id',
            "'subscriber_user'",
            "['owner', 'admin']",
            "->where('active', 1)",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_draft_plan_is_only_reported_as_preparation(): void
    {
        $source = preg_replace('/\s+/', ' ', $this->service());

        $this->assertStringContainsString('&& $plan->presented_at !== null', $source);
        $this->assertStringContainsString(": (\$plan ? 'En preparación' : 'Pendiente')", $source);
        $this->assertStringContainsString(
            "'url' => \$planIsPublic ? route('diagnosis.implementation_plan.show', \$assessment->id, false) : null",
            $source
        );
    }

    public function test_only_presented_plan_states_receive_client_url(): void
    {
        $source = $this->service();

        foreach ([
            "'presented'",
            "'accepted'",
            "'active'",
            "'completed'",
            '$plan->presented_at !== null',
            "'diagnosis.implementation_plan.show'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_dashboard_distinguishes_free_deliverables_from_paid_execution(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Subscriber/'
            .'Dashboard.vue'
        );

        $normalized = preg_replace(
            '/\s+/u',
            ' ',
            $page
        );

        $this->assertIsString($normalized);

        $this->assertStringContainsString(
            'Informe Ampliado, Roadmap Detallado y Plan de Implementación son entregables gratuitos.',
            $normalized
        );
        $this->assertStringContainsString(
            'La contratación de apoyo para ejecutar el Plan se gestiona en un proceso separado.',
            $normalized
        );
    }

    public function test_dashboard_renders_the_four_consultive_stages(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/Subscriber/'
            .'Dashboard.vue'
        );

        foreach ([
            'Transformación Digital 360',
            'transformation360.stages',
            'Borrador privado de LAUDA',
            'v-for="(stage, index) in transformation360.stages"',
            'xl:grid-cols-4',
            '{{ stage.label }}',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }
    }

    public function test_t360_state_is_loaded_outside_dashboard_cache(): void
    {
        $controller = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Subscriber/'
            .'SubscriberDashboardController.php'
        );

        $cachePosition = strpos(
            $controller,
            'Cache::remember('
        );

        $t360Position = strpos(
            $controller,
            "\$stats['transformation360']"
        );

        $this->assertNotFalse(
            $cachePosition
        );

        $this->assertNotFalse(
            $t360Position
        );

        $this->assertGreaterThan(
            $cachePosition,
            $t360Position
        );
    }

    public function test_read_model_has_no_economic_writes(): void
    {
        $source = $this->service();

        foreach ([
            '->insert(',
            '->update(',
            '->delete(',
            '::create(',
            '->save(',
            'Subscription::',
            'SubscriptionItem::',
            'Invoice::',
            'Payment::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
