<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapReadinessContractTest extends TestCase
{
    public function test_backend_exposes_generation_and_publication_readiness(): void
    {
        $root = dirname(__DIR__, 3);

        $controller = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'
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
    }

    public function test_ui_explains_disabled_publication(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/DetailedRoadmap.vue'
        );

        foreach ([
            'Estado del Roadmap',
            'Roadmap solicitado',
            'Factura Roadmap',
            'Pago Roadmap',
            'ya generado',
            'Publicar para cliente todavía no está disponible',
            ':disabled="commercial?.paid_access !== true"',
            'publicationReady.value',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_backend_paid_gate_is_preserved(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'
        );

        $this->assertStringContainsString(
            '$commercialService->hasPaidAccess(',
            $source
        );

        $this->assertStringContainsString(
            'solo puede publicarse después de confirmar el pago',
            $source
        );
    }
}
