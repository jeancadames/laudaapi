<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DiagnosisRequestAdministrativeControlUiContractTest extends TestCase
{
    private function source(string $file): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/'.ltrim($file, '/')
        ) ?: '';
    }

    public function test_tenant_exposes_company_request_block(): void
    {
        $component = $this->source(
            'resources/js/pages/App/Diagnosis360.vue'
        );

        foreach ([
            'request_blocked: boolean;',
            'request_block_reason: string | null;',
            'props.state.request_blocked',
            'Nuevas evaluaciones temporalmente bloqueadas',
            'request_block_reason',
            '&& !props.state.request_blocked',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $component
            );
        }
    }

    public function test_admin_separates_request_from_assessment_lifecycle(): void
    {
        $component = $this->source(
            'resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        foreach ([
            'Estado administrativo de la solicitud',
            'Inactivar solicitud',
            'Reactivar solicitud',
            'Vigencia del diagnóstico',
            'Inactivar diagnóstico',
            'Reactivar diagnóstico',
            'Nuevas solicitudes del tenant',
            'Bloquear nuevas solicitudes',
            'Habilitar nuevas solicitudes',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $component
            );
        }
    }

    public function test_admin_index_exposes_inactive_request_status(): void
    {
        $component = $this->source(
            'resources/js/pages/Admin/DiagnosisRequests/Index.vue'
        );

        foreach ([
            "inactive: 'Solicitud inactiva'",
            "{ value: 'inactive', label: 'Inactivas' }",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $component
            );
        }
    }
}
