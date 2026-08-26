<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisTransformationQuickActionsContractTest extends TestCase
{
    public function test_quick_actions_component_has_client_and_admin_modes(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            "mode: 'client' | 'admin'",
            'expanded_report_published',
            'roadmap_published',
            'Ver Informe Ampliado',
            'Ver Roadmap Detallado',
            'Gestionar Informe Ampliado',
            'Gestionar Roadmap Detallado',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_client_urls_are_derived_from_assessment(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            '/diagnostico/${props.assessmentId}',
            '/diagnostico/${props.assessmentId}/informe-ampliado',
            '/diagnostico/${props.assessmentId}/roadmap-detallado',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_admin_urls_are_derived_from_contact(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            '/admin/diagnosis-requests/${props.contactId}',
            '/admin/diagnosis-requests/${props.contactId}/expanded-report',
            '/admin/diagnosis-requests/${props.contactId}/detailed-roadmap',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_quick_actions_are_integrated_in_main_views(): void
    {
        $root = dirname(__DIR__, 3);

        $client = file_get_contents(
            $root . '/resources/js/pages/Diagnosis/Show.vue'
        );

        $admin = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        foreach ([
            'TransformationQuickActions',
            'mode="client"',
            ':assessment-id="assessment.id"',
            ':progress="transformation_progress"',
        ] as $token) {
            $this->assertStringContainsString($token, $client);
        }

        foreach ([
            'TransformationQuickActions',
            'mode="admin"',
            ':contact-id="contact.id"',
            ':progress="transformation_progress"',
        ] as $token) {
            $this->assertStringContainsString($token, $admin);
        }
    }

    public function test_client_does_not_unlock_unpublished_deliverables(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'expandedReportAvailable',
            'roadmapAvailable',
            'Informe Ampliado no disponible',
            'Roadmap Detallado no disponible',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }
}
