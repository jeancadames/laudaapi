<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisTransformationQuickActionsContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function source(): string
    {
        return file_get_contents(
            $this->root()
            .'/resources/js/components/diagnosis/'
            .'TransformationQuickActions.vue'
        );
    }

    public function test_quick_actions_component_has_client_and_admin_modes(): void
    {
        $source = $this->source();

        foreach ([
            "mode: 'client' | 'admin'",
            'expanded_report_published',
            'roadmap_published',
            'Ver Informe Ampliado',
            'Ver Roadmap Detallado',
            'Gestionar Informe Ampliado',
            'Gestionar Roadmap Detallado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_urls_are_derived_from_assessment(): void
    {
        $source = $this->source();

        foreach ([
            '/diagnostico/${props.assessmentId}',
            '/diagnostico/${props.assessmentId}/informe-ampliado',
            '/diagnostico/${props.assessmentId}/roadmap-detallado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_urls_are_derived_from_contact(): void
    {
        $source = $this->source();

        foreach ([
            '/admin/diagnosis-requests/${props.contactId}',
            '/admin/diagnosis-requests/${props.contactId}/expanded-report',
            '/admin/diagnosis-requests/${props.contactId}/detailed-roadmap',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_quick_actions_are_integrated_in_main_views(): void
    {
        $client = file_get_contents(
            $this->root()
            .'/resources/js/pages/Diagnosis/Show.vue'
        );

        $admin = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        foreach ([
            'TransformationQuickActions',
            'mode="client"',
            ':assessment-id="assessment.id"',
            ':progress="transformation_progress"',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $client
            );
        }

        foreach ([
            'TransformationQuickActions',
            'mode="admin"',
            ':contact-id="contact.id"',
            ':progress="transformation_progress"',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $admin
            );
        }
    }

    public function test_client_does_not_unlock_unpublished_deliverables(): void
    {
        $source = $this->source();

        foreach ([
            'expandedReportAvailable',
            'roadmapAvailable',
            'Informe Ampliado no disponible',
            'Roadmap Detallado no disponible',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_exposes_implementation_plan_continuation(): void
    {
        $source = $this->source();

        foreach ([
            'implementationPlanUrl?: string | null',
            'v-if="implementationPlanUrl"',
            ':href="implementationPlanUrl"',
            'Continuar con mi transformación',
            'Plan de Implementación en preparación',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_quick_actions_have_no_active_commercial_flow(): void
    {
        $source = $this->source();

        foreach ([
            'CommercialState',
            'AdminCommercialEndpoints',
            'expandedReportCommercial',
            'roadmapCommercial',
            'requestExpandedReportUrl',
            'requestRoadmapUrl',
            'commercialEndpoints',
            'paid_access',
            'Preparar factura',
            'Confirmar pago',
            'Factura preparada',
            'Pago confirmado',
            'useForm(',
            'router.post(',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
