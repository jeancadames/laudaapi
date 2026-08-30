<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisPostValidationClosureContractTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3) . '/' . $path);
    }

    public function test_validation_service_closes_validated_versions_and_full_cycle(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/DiagnosisDeliverableValidationService.php'
        );

        foreach ([
            'closureForAssessment(',
            'assertAssessmentOpenForPublication(',
            'assertNotValidated(',
            "->whereNotNull('validated_at')",
            'El ciclo documental fue validado por el tenant y está cerrado.',
            'Esta versión fue validada por el tenant y quedó cerrada.',
        ] as $token) {
            $this->assertStringContainsString($token, $service);
        }
    }

    public function test_diagnosis_republication_is_blocked_after_full_validation(): void
    {
        $publisher = $this->read(
            'app/Services/Diagnosis/DiagnosisResultPublisher.php'
        );

        $controller = $this->read(
            'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
        );

        $ui = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        $this->assertStringContainsString(
            '$this->validations->assertAssessmentOpenForPublication(',
            $publisher
        );
        $this->assertStringContainsString("'document_closure' =>", $controller);
        $this->assertStringContainsString('documentCycleClosed', $ui);
        $this->assertStringContainsString(
            'Ciclo documental validado por el tenant',
            $ui
        );
        $this->assertStringContainsString('v-if="!documentCycleClosed"', $ui);
    }

    public function test_report_and_roadmap_endpoints_guard_validated_versions(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/AdminDiagnosisExpandedReportController.php',
            'app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php',
        ] as $path) {
            $source = $this->read($path);
            $this->assertStringContainsString(
                'DiagnosisDeliverableValidationService',
                $source
            );
            $this->assertStringContainsString('assertNotValidated(', $source);
            $this->assertStringContainsString("'tenant_validation' =>", $source);
        }
    }

    public function test_plan_re_presentation_is_guarded_and_admin_shows_closure(): void
    {
        $service = $this->read(
            'app/Services/Diagnosis/TransformationImplementationPlanService.php'
        );
        $controller = $this->read(
            'app/Http/Controllers/Admin/AdminTransformationImplementationPlanController.php'
        );
        $ui = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/ImplementationPlan.vue'
        );

        $this->assertStringContainsString(
            '$this->validations->assertNotValidated($plan);',
            $service
        );
        $this->assertStringContainsString("'tenant_validation' =>", $controller);
        $this->assertStringContainsString('planValidated', $ui);
        $this->assertStringContainsString(
            'Versión validada por el tenant y cerrada',
            $ui
        );
    }

    public function test_existing_new_version_action_remains_explicit(): void
    {
        $report = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/ExpandedReport.vue'
        );
        $roadmap = $this->read(
            'resources/js/pages/Admin/DiagnosisRequests/DetailedRoadmap.vue'
        );

        $this->assertStringContainsString(
            'Crear nueva versión',
            $report
        );
        $this->assertStringContainsString(
            'Crear nueva versión',
            $roadmap
        );
    }
}
