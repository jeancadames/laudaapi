<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapReadinessContractTest extends TestCase
{
    public function test_backend_exposes_content_generation_and_publication_readiness(): void
    {
        $root = dirname(__DIR__, 3);

        $controller = file_get_contents(
            $root
            .'/app/Http/Controllers/Admin/'
            .'AdminDiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            'DiagnosisTransformationProgressService',
            'roadmapReadiness(',
            "'generation_readiness'",
            "'transformation_progress'",
            "'generation_ready'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        $service = file_get_contents(
            $root
            .'/app/Services/Diagnosis/'
            .'DiagnosisTransformationProgressService.php'
        );

        foreach ([
            "'diagnosis_published'",
            "'expanded_report_published'",
            "'publication_ready'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        foreach ([
            'roadmap_requested',
            'roadmap_invoiced',
            'roadmap_paid',
            "'commercial' =>",
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $service
            );
        }
    }

    public function test_ui_explains_disabled_publication_without_commercial_prerequisites(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'DetailedRoadmap.vue'
        );

        foreach ([
            'Estado del Roadmap',
            'Diagnóstico publicado',
            'Informe publicado',
            'ya generado',
            'Publicar para cliente todavía no está disponible',
            'publicationReady.value',
            'Los requisitos de contenido y revisión están',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Roadmap solicitado',
            'Factura Roadmap',
            'Pago Roadmap',
            'commercial?.paid_access',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_backend_paid_gate_is_removed(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            .'/app/Http/Controllers/Admin/'
            .'AdminDiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            '$commercialService->hasPaidAccess(',
            'solo puede publicarse después de confirmar el pago',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }
}
