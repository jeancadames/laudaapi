<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisAdminLifecycleUiAndTenantRoutingContractTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.ltrim($file, '/')
        ) ?: '';
    }

    public function test_admin_exposes_assessment_lifecycle(): void
    {
        $controller = $this->source(
            'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
        );

        foreach ([
            "'is_active' => (bool) \$assessment->is_active",
            "'inactivated_at' =>",
            "'superseded_by_assessment_id' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        $ui = $this->source(
            'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        foreach ([
            'DIAGNOSIS_ADMIN_LIFECYCLE_UI',
            'Inactivar solicitud',
            'Reactivar solicitud',
            'Eliminar diagnóstico borrador',
            '/inactivate',
            '/reactivate',
            '/assessment',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $ui
            );
        }
    }

    public function test_gateway_fallbacks_only_use_active_assessments(): void
    {
        $gateway = $this->source(
            'app/Http/Controllers/AppGatewayController.php'
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $gateway,
                "->where('is_active', true)"
            )
        );

        $this->assertGreaterThanOrEqual(
            2,
            substr_count(
                $gateway,
                "->whereHas("
            )
        );
    }

    public function test_direct_tenant_access_denies_inactive_assessment(): void
    {
        $policy = $this->source(
            'app/Policies/DiagnosisAssessmentPolicy.php'
        );

        $this->assertStringContainsString(
            'if (! (bool) $assessment->is_active)',
            $policy
        );

        $this->assertStringContainsString(
            'return false;',
            $policy
        );
    }

    public function test_admin_index_distinguishes_workflow_status_from_assessment_lifecycle(): void
    {
        $controller = $this->source(
            'app/Http/Controllers/Admin/AdminDiagnosisAccessRequestController.php'
        );

        foreach ([
            "'da.is_active as assessment_is_active'",
            "'assessment_is_active' =>",
            "'assessment_inactivated_at' =>",
            "'assessment_superseded_by_assessment_id' =>",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $controller
            );
        }

        $ui = $this->source(
            'resources/js/pages/Admin/DiagnosisRequests/Index.vue'
        );

        foreach ([
            'assessment_is_active: boolean | null',
            "active: 'Acceso activo'",
            "label: 'Acceso activo'",
            'Diagnóstico vigente',
            'Diagnóstico inactivo',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $ui
            );
        }
    }

}
