<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisFreeStageModalityDecouplingContractTest extends TestCase
{
    public function test_publish_and_review_requests_do_not_accept_final_modality(): void
    {
        foreach ([
            'app/Http/Requests/Diagnosis/PublishDiagnosisResultRequest.php',
            'app/Http/Requests/Diagnosis/SaveDiagnosisReviewRequest.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString(
                "'final_modality' =>",
                $source,
                $file
            );

            $this->assertStringNotContainsString(
                "Rule::in(['guided', 'assisted', 'managed'])",
                $source,
                $file
            );
        }
    }

    public function test_diagnosis_publication_preserves_historical_modality_without_using_it(): void
    {
        $publisher = file_get_contents(
            base_path('app/Services/Diagnosis/DiagnosisResultPublisher.php')
        );

        foreach ([
            'MODALITY_LABELS',
            'labelForModality',
            '$data[\'final_modality\']',
            "'final_modality' =>",
            "'final_modality_label' =>",
        ] as $token) {
            $this->assertStringNotContainsString($token, $publisher);
        }

        $this->assertStringContainsString(
            "'commercial_modality_changed' => false",
            $publisher
        );

        $this->assertStringContainsString(
            '$this->deliverables->generateAndPresent(',
            $publisher
        );
    }

    public function test_free_deliverable_generators_are_modality_neutral(): void
    {
        $report = file_get_contents(
            base_path('app/Services/Diagnosis/DiagnosisExpandedReportGenerator.php')
        );

        $roadmap = file_get_contents(
            base_path('app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php')
        );

        $this->assertStringContainsString(
            "'execution_capacity' =>",
            $report
        );

        foreach ([
            '$assessment->final_modality',
            '$assessment->final_modality_label',
            '$assessment->recommended_modality',
            '$assessment->recommended_modality_label',
            "'modality_and_capacity' =>",
        ] as $token) {
            $this->assertStringNotContainsString($token, $report);
        }

        foreach ([
            '$assessment->final_modality',
            '$assessment->final_modality_label',
            '$assessment->recommended_modality',
            '$assessment->recommended_modality_label',
            "'recommended_modality' =>",
            "'recommended_modality_label' =>",
        ] as $token) {
            $this->assertStringNotContainsString($token, $roadmap);
        }
    }

    public function test_active_diagnosis_ui_has_no_execution_modality_selector_or_recommendation(): void
    {
        $files = [
            'resources/js/pages/Admin/DiagnosisRequests/Show.vue',
            'resources/js/pages/Diagnosis/Show.vue',
            'resources/js/pages/Diagnosis/ExpandedReport.vue',
            'resources/js/pages/Diagnosis/DigitalDiagnosisWizard.vue',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(base_path($file));

            foreach ([
                'Modalidad final',
                'Modalidad automática',
                'Modalidad recomendada',
                'LAUDA 360 Guiado',
                'LAUDA 360 Asistido',
                'LAUDA 360 Gestionado',
            ] as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $source,
                    "{$file}: {$token}"
                );
            }
        }
    }

    public function test_admin_diagnosis_dashboard_does_not_surface_modality(): void
    {
        $controller = file_get_contents(
            base_path('app/Http/Controllers/Admin/AdminDashboardController.php')
        );

        $page = file_get_contents(
            base_path('resources/js/pages/Admin/Dashboard.vue')
        );

        foreach ([
            "'modalities' =>",
            'recommended_modality',
            'recommended_modality_label',
        ] as $token) {
            $this->assertStringNotContainsString($token, $controller);
        }

        foreach ([
            'modalities:',
            'recommended_modality',
            'recommended_modality_label',
            'Modalidad por definir',
        ] as $token) {
            $this->assertStringNotContainsString($token, $page);
        }
    }

    public function test_historical_fields_are_not_deleted_from_model(): void
    {
        $model = file_get_contents(
            base_path('app/Models/DiagnosisAssessment.php')
        );

        $this->assertStringContainsString(
            "'final_modality'",
            $model
        );

        $this->assertStringContainsString(
            "'final_modality_label'",
            $model
        );
    }
}
