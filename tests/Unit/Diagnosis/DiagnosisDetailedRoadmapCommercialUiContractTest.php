<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapCommercialUiContractTest extends TestCase
{
    public function test_routes_exist_in_code(): void
    {
        $root = dirname(__DIR__, 3);

        $web = file_get_contents(
            $root . '/routes/web.php'
        );

        $admin = file_get_contents(
            $root . '/routes/admin.php'
        );

        $this->assertStringContainsString(
            'detailed_roadmap.request',
            $web
        );

        foreach ([
            'detailed_roadmap.prepare_invoice',
            'detailed_roadmap.record_payment',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $admin
            );
        }
    }

    public function test_client_requires_paid_access(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            'hasPaidAccess(',
            'El Roadmap Detallado requiere pago confirmado.',
            'DiagnosisDetailedRoadmap::STATUS_PUBLISHED',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_publish_requires_paid_access(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            'DiagnosisDetailedRoadmapCommercialService',
            'hasPaidAccess(',
            'solo puede publicarse después de confirmar el pago',
            '$service->publish(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_client_ui_has_commercial_states(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/DetailedRoadmapCommercialCard.vue'
        );

        foreach ([
            'Solicitar Roadmap Detallado',
            'Crédito del Informe Ampliado aplicable',
            'Solicitud recibida',
            'Facturación preparada',
            'Pago confirmado · Roadmap en preparación',
            'Roadmap disponible',
            'Ver Roadmap Detallado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_ui_has_invoice_payment_and_publish_gate(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/DetailedRoadmapAdminCommercialCard.vue'
        );

        /*
         * Prettier puede envolver texto visible del template
         * en varias líneas. Normalizamos whitespace para que
         * el contrato valide contenido, no formato físico.
         */
        $normalizedSource = preg_replace(
            '/\s+/u',
            ' ',
            $source
        ) ?? $source;

        foreach ([
            'Preparar factura',
            'Confirmar pago completo',
            'Acceso comercial habilitado',
            'Publicación bloqueada hasta pago',
            'Publicar requiere pago confirmado.',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $normalizedSource
            );
        }

        $page = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/DetailedRoadmap.vue'
        );

        $this->assertStringContainsString(
            ':disabled="commercial?.paid_access !== true"',
            $page
        );
    }

    public function test_controllers_do_not_create_subscription(): void
    {
        $root = dirname(__DIR__, 3);

        $source = implode(
            "\n",
            [
                file_get_contents(
                    $root
                    . '/app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapCommercialController.php'
                ),
                file_get_contents(
                    $root
                    . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapCommercialController.php'
                ),
            ]
        );

        foreach ([
            'Subscription::create',
            'ActivationRequest::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
