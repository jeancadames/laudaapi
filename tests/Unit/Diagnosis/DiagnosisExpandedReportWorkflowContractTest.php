<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisExpandedReport;
use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportWorkflowContractTest extends TestCase
{
    public function test_workflow_routes_are_present(): void
    {
        $root = dirname(__DIR__, 3);

        $admin = file_get_contents(
            $root . '/routes/admin.php'
        );

        $web = file_get_contents(
            $root . '/routes/web.php'
        );

        foreach ([
            'expanded_report.show',
            'expanded_report.generate',
            'expanded_report.save_review',
            'expanded_report.review',
            'expanded_report.regenerate',
            'expanded_report.publish',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $admin
            );
        }

        $this->assertStringContainsString(
            'expanded_report.show',
            $web
        );
    }

    public function test_client_controller_only_reads_published_reports(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            . '/app/Http/Controllers/Diagnosis/DiagnosisExpandedReportController.php'
        );

        $this->assertStringContainsString(
            'STATUS_PUBLISHED',
            $source
        );

        $this->assertStringContainsString(
            "whereNotNull('published_at')",
            $source
        );

        $this->assertStringContainsString(
            "Gate::authorize(",
            $source
        );
    }

    public function test_ui_contract_is_present(): void
    {
        $root = dirname(__DIR__, 3);

        $admin = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/ExpandedReport.vue'
        );

        $client = file_get_contents(
            $root
            . '/resources/js/pages/Diagnosis/ExpandedReport.vue'
        );

        foreach ([
            'Generar borrador contextualizado',
            'Guardar notas',
            '@click="markReview"',
            '@click="publish"',
            '@click="generate"',
            'Facturación one-time',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $admin
            );
        }

        foreach ([
            'Informe Ampliado',
            'Análisis por dimensión',
            'Brechas críticas',
            'Focos recomendados',
            'Del Informe Ampliado al Roadmap',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $client
            );
        }
    }

    public function test_published_report_is_not_editable(): void
    {
        $report = new DiagnosisExpandedReport([
            'status' =>
                DiagnosisExpandedReport::STATUS_PUBLISHED,
        ]);

        $this->assertFalse(
            $report->isEditable()
        );
    }
}
