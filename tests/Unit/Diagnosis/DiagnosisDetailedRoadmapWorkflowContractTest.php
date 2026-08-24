<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapWorkflowContractTest extends TestCase
{
    public function test_routes_are_present(): void
    {
        $root = dirname(__DIR__, 3);
        $admin = file_get_contents($root . '/routes/admin.php');
        $web = file_get_contents($root . '/routes/web.php');

        foreach ([
            'detailed_roadmap.show',
            'detailed_roadmap.generate',
            'detailed_roadmap.save_review',
            'detailed_roadmap.review',
            'detailed_roadmap.regenerate',
            'detailed_roadmap.publish',
        ] as $token) {
            $this->assertStringContainsString($token, $admin);
        }

        $this->assertStringContainsString(
            'detailed_roadmap.show',
            $web
        );
    }

    public function test_client_reads_only_published_roadmap(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapController.php'
        );

        $this->assertStringContainsString(
            'DiagnosisDetailedRoadmap::STATUS_PUBLISHED',
            $source
        );
        $this->assertStringContainsString(
            "whereNotNull('published_at')",
            $source
        );
        $this->assertStringContainsString(
            "Gate::authorize('view',",
            $source
        );
    }

    public function test_service_has_review_publish_workflow(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        foreach ([
            'function saveReviewNotes(',
            'function markUnderReview(',
            'function publish(',
            'Una versión publicada no puede editarse.',
            'diagnosis_detailed_roadmap_published',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_admin_ui_contains_workflow(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/DetailedRoadmap.vue'
        );

        foreach ([
            'Generar Roadmap Detallado',
            'Guardar notas',
            '@click="markReview"',
            '@click="publish"',
            'Crear nueva versión',
            'DetailedRoadmapAdminCommercialCard',
            'Iniciativas priorizadas',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_client_ui_contains_executable_structure(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root
            . '/resources/js/pages/Diagnosis/DetailedRoadmap.vue'
        );

        foreach ([
            '4 fases de transformación',
            'Iniciativas priorizadas',
            'Responsable',
            'Dependencias',
            'Acciones',
            'Indicadores de éxito',
            'Gobierno del Roadmap',
            'Alcance del entregable',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    public function test_publication_route_is_now_commercially_guarded(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'
        );

        $this->assertStringContainsString(
            'hasPaidAccess(',
            $source
        );

        $this->assertStringContainsString(
            'solo puede publicarse después de confirmar el pago',
            $source
        );
    }

    public function test_workflow_remains_decoupled_from_billing(): void
    {
        $root = dirname(__DIR__, 3);

        $source = implode("\n", [
            file_get_contents(
                $root
                . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'
            ),
            file_get_contents(
                $root
                . '/app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapController.php'
            ),
            file_get_contents(
                $root
                . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
            ),
        ]);

        foreach ([
            'Invoice::create',
            'Payment::create',
            'Subscription::create',
            'DiagnosisDetailedRoadmapOrder',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
