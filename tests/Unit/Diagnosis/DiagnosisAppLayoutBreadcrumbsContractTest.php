<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisAppLayoutBreadcrumbsContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return array<string, string>
     */
    private function privatePages(): array
    {
        return [
            'show' =>
                'resources/js/pages/Diagnosis/Show.vue',

            'expanded_report' =>
                'resources/js/pages/Diagnosis/ExpandedReport.vue',

            'detailed_roadmap' =>
                'resources/js/pages/Diagnosis/DetailedRoadmap.vue',

            'implementation_plan' =>
                'resources/js/pages/Diagnosis/ImplementationPlan.vue',
        ];
    }

    public function test_authenticated_diagnosis_pages_use_app_layout(): void
    {
        foreach ($this->privatePages() as $name => $relative) {
            $source = file_get_contents(
                $this->root().'/'.$relative
            );

            $this->assertStringContainsString(
                "import AppLayout from '@/layouts/AppLayout.vue';",
                $source,
                $name
            );

            $this->assertStringContainsString(
                '<AppLayout :breadcrumbs="breadcrumbs">',
                $source,
                $name
            );

            $this->assertStringContainsString(
                "title: 'Inicio'",
                $source,
                $name
            );

            $this->assertStringContainsString(
                "title: 'Diagnóstico 360'",
                $source,
                $name
            );
        }
    }

    public function test_show_owns_layout_and_wizard_does_not(): void
    {
        $show = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/Show.vue'
        );

        $wizard = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/'
            .'DigitalDiagnosisWizard.vue'
        );

        $this->assertStringContainsString(
            '<DigitalDiagnosisWizard',
            $show
        );

        $this->assertStringContainsString(
            '<AppLayout :breadcrumbs="breadcrumbs">',
            $show
        );

        $this->assertStringNotContainsString(
            "layouts/AppLayout",
            $wizard
        );
    }

    public function test_commercial_documents_have_specific_breadcrumbs(): void
    {
        $report = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/ExpandedReport.vue'
        );

        $roadmap = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/DetailedRoadmap.vue'
        );

        $plan = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            "title: 'Informe Ampliado'",
            $report
        );

        $this->assertStringContainsString(
            "title: 'Informe Ampliado'",
            $roadmap
        );

        $this->assertStringContainsString(
            "title: 'Roadmap Detallado'",
            $roadmap
        );

        $this->assertStringContainsString(
            "title: 'Plan de Implementación'",
            $plan
        );
    }

    public function test_plan_roadmap_breadcrumb_is_optional(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            '...(props.roadmap_url',
            $source
        );

        $this->assertStringContainsString(
            "title: 'Roadmap Detallado'",
            $source
        );
    }

    public function test_pre_authentication_pages_keep_their_existing_layout(): void
    {
        foreach ([
            'resources/js/pages/Diagnosis/SetPassword.vue',
            'resources/js/pages/Diagnosis/InvitationExpired.vue',
        ] as $relative) {
            $source = file_get_contents(
                $this->root().'/'.$relative
            );

            $this->assertStringNotContainsString(
                "layouts/AppLayout",
                $source,
                $relative
            );
        }
    }
}
