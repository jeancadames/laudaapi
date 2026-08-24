<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisTransformationProgressUiContractTest extends TestCase
{
    public function test_component_exposes_step_by_step_progress(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/TransformationProgressChecklist.vue'
        );

        foreach ([
            'Seguimiento Transformación 360',
            'Etapa actual',
            'Completado',
            'Pendiente',
            'Bloqueado',
            'Siguiente acción:',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_and_admin_show_progress(): void
    {
        $root = dirname(__DIR__, 3);

        foreach ([
            '/resources/js/pages/Diagnosis/Show.vue',
            '/resources/js/pages/Admin/DiagnosisRequests/Show.vue',
        ] as $file) {
            $source = file_get_contents(
                $root . $file
            );

            $this->assertStringContainsString(
                'TransformationProgressChecklist',
                $source
            );

            $this->assertStringContainsString(
                'transformation_progress',
                $source
            );
        }

        $admin = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/Show.vue'
        );

        $this->assertStringContainsString(
            ':admin="true"',
            $admin
        );
    }
}
