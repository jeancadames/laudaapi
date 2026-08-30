<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapCommercialUiContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_active_purchase_and_billing_routes_are_removed(): void
    {
        $web = file_get_contents(
            $this->root().'/routes/web.php'
        );

        $admin = file_get_contents(
            $this->root().'/routes/admin.php'
        );

        $this->assertStringNotContainsString(
            'detailed_roadmap.request',
            $web
        );

        foreach ([
            'detailed_roadmap.prepare_invoice',
            'detailed_roadmap.record_payment',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $admin
            );
        }
    }

    public function test_client_reads_published_roadmap_without_paid_access(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Diagnosis/'
            .'DiagnosisDetailedRoadmapController.php'
        );

        $this->assertStringContainsString(
            'DiagnosisDetailedRoadmap::STATUS_PUBLISHED',
            $source
        );

        foreach ([
            'hasPaidAccess(',
            'requiere pago confirmado',
            'DiagnosisDetailedRoadmapCommercialService',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_publication_has_no_paid_access_gate(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/app/Http/Controllers/Admin/'
            .'AdminDiagnosisDetailedRoadmapController.php'
        );

        $this->assertStringContainsString(
            '$service->publish(',
            $source
        );

        foreach ([
            'DiagnosisDetailedRoadmapCommercialService',
            'hasPaidAccess(',
            'solo puede publicarse después de confirmar el pago',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }
    }

    public function test_active_admin_ui_has_no_commercial_card_or_payment_gate(): void
    {
        $source = file_get_contents(
            $this->root()
            .'/resources/js/pages/Admin/DiagnosisRequests/'
            .'DetailedRoadmap.vue'
        );

        foreach ([
            'DetailedRoadmapAdminCommercialCard',
            'commercial?.paid_access',
            'prepare_invoice',
            'record_payment',
            'Factura Roadmap',
            'Pago Roadmap',
            'estado comercial',
        ] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $source
            );
        }

        foreach ([
            'Estado del Roadmap',
            'Publicar para cliente',
            'publicationReady.value',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_historical_commercial_components_and_controllers_are_preserved(): void
    {
        foreach ([
            '/resources/js/components/diagnosis/DetailedRoadmapCommercialCard.vue',
            '/resources/js/components/diagnosis/DetailedRoadmapAdminCommercialCard.vue',
            '/app/Http/Controllers/Diagnosis/DiagnosisDetailedRoadmapCommercialController.php',
            '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapCommercialController.php',
            '/app/Services/Diagnosis/DiagnosisDetailedRoadmapCommercialService.php',
        ] as $path) {
            $this->assertFileExists(
                $this->root().$path
            );
        }
    }
}
