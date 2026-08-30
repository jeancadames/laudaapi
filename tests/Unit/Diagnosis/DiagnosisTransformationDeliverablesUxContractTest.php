<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisTransformationDeliverablesUxContractTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.$path
        );
    }

    public function test_three_deliverables_are_always_declared(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'Documentos y resultados',
            'Informe del Diagnóstico',
            'Informe Ampliado',
            'Roadmap Detallado',
            "isCompleted('diagnosis_published')",
            "isCompleted('expanded_report_published')",
            "isCompleted('roadmap_published')",
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_admin_deliverables_are_gated_in_sequence(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'diagnosisSubmitted && adminDiagnosisUrl',
            'diagnosisPublished && adminExpandedReportUrl',
            'expandedReportAvailable &&',
            'adminRoadmapUrl',
            'Informe generado automáticamente',
            'Roadmap generado automáticamente tras el Informe',
            'Gestionar Informe Ampliado',
            'Gestionar Roadmap Detallado',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            'Generar / publicar informe',
            $source
        );
    }

    public function test_client_never_opens_unpublished_deliverable_directly(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'expandedReportAvailable &&',
            'clientExpandedReportUrl',
            'roadmapAvailable && clientRoadmapUrl',
            'Informe Ampliado no disponible',
            'Roadmap Detallado no disponible',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_plan_is_presented_as_next_phase_not_as_fourth_document(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationQuickActions.vue'
        );

        foreach ([
            'Siguiente fase',
            'Gestionar Plan de Implementación',
            'Continuar con mi transformación',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_checklist_uses_more_readable_spacing(): void
    {
        $source = $this->read(
            'resources/js/components/diagnosis/TransformationProgressChecklist.vue'
        );

        foreach ([
            "'grid gap-4'",
            "'grid gap-4 xl:grid-cols-2'",
            'p-5 shadow-sm',
            'Siguiente acción:',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }

        $this->assertStringNotContainsString(
            'grid gap-3 lg:grid-cols-2',
            $source
        );
    }

    public function test_client_and_admin_have_document_anchors_and_spacing(): void
    {
        $client = $this->read(
            'resources/js/pages/Diagnosis/Show.vue'
        );

        $admin = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        $this->assertStringContainsString(
            'mb-8 space-y-6',
            $client
        );

        $this->assertStringContainsString(
            'id="informe-diagnostico"',
            $client
        );

        $this->assertStringContainsString(
            'id="informe-ampliado"',
            $client
        );

        $this->assertStringContainsString(
            'space-y-6',
            $admin
        );

        $this->assertStringContainsString(
            'id="informe-diagnostico"',
            $admin
        );
    }
}
