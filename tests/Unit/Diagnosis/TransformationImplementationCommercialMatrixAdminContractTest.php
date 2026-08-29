<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\TransformationImplementationCommercialMatrixService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransformationImplementationCommercialMatrixAdminContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_base_matrix_remains_unconfigured(): void
    {
        $service =
            app(
                TransformationImplementationCommercialMatrixService::class
            );

        $matrix =
            $service->base();

        $readiness =
            $service->readiness(
                $matrix
            );

        $this->assertFalse(
            $readiness['ready']
        );

        $this->assertSame(
            30,
            $readiness['missing_count']
        );

        $this->assertSame(
            'commercial_matrix_v1',
            $readiness['version']
        );

        $this->assertSame(
            'DOP',
            $readiness['currency']
        );
    }

    public function test_complete_admin_payload_is_ready(): void
    {
        $service =
            app(
                TransformationImplementationCommercialMatrixService::class
            );

        $payload = [];

        foreach (
            [
                'guided',
                'assisted',
                'managed',
            ] as $modality
        ) {
            foreach (
                [
                    'low',
                    'medium',
                    'high',
                ] as $effort
            ) {
                $payload[$modality]
                    ['initiative_effort']
                    [$effort] = [
                        'price_amount' => 1000,
                        'duration_days' => 2,
                    ];
            }

            foreach (
                [
                    'procedures_guide',
                    'branding_identity',
                ] as $capability
            ) {
                $payload[$modality]
                    ['professional_capabilities']
                    [$capability] = [
                        'price_amount' => 500,
                        'duration_days' => 1,
                    ];
            }
        }

        $matrix =
            $service->normalizeAdminPayload(
                $payload
            );

        $readiness =
            $service->readiness(
                $matrix
            );

        $this->assertTrue(
            $readiness['ready']
        );

        $this->assertSame(
            0,
            $readiness['missing_count']
        );
    }

    public function test_price_and_duration_must_be_configured_together(): void
    {
        $service =
            app(
                TransformationImplementationCommercialMatrixService::class
            );

        $this->expectException(
            ValidationException::class
        );

        $service->normalizeAdminPayload([
            'guided' => [
                'initiative_effort' => [
                    'low' => [
                        'price_amount' => 100,
                        'duration_days' => null,
                    ],
                ],
            ],
        ]);
    }

    public function test_matrix_has_its_own_storage_domain(): void
    {
        $migration =
            file_get_contents(
                $this->root()
                .'/database/migrations/'
                .'2026_08_29_234500_create_transformation_implementation_commercial_rates_table.php'
            );

        $this->assertStringContainsString(
            'transformation_implementation_commercial_rates',
            $migration
        );

        $this->assertStringContainsString(
            'matrix_version',
            $migration
        );

        $this->assertStringContainsString(
            'component_type',
            $migration
        );

        $this->assertStringContainsString(
            'component_key',
            $migration
        );

        $this->assertStringNotContainsString(
            "constrained('services')",
            $migration
        );

        $this->assertStringNotContainsString(
            "constrained('subscriptions')",
            $migration
        );
    }

    public function test_engine_reads_database_backed_matrix_service(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/app/Services/Diagnosis/'
                .'TransformationImplementationCommercialEngine.php'
            );

        $this->assertStringContainsString(
            'TransformationImplementationCommercialMatrixService::class',
            $source
        );

        $this->assertStringContainsString(
            '->current()',
            $source
        );

        $this->assertStringNotContainsString(
            "config(\n                'lauda360_implementation'",
            $source
        );
    }

    public function test_admin_ui_exposes_all_commercial_dimensions(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/Transformation360/'
                .'CommercialSettings.vue'
            );

        foreach (
            [
                'Matriz comercial de implementación',
                'Iniciativas por nivel de esfuerzo',
                'Servicios profesionales',
                'Precio DOP',
                'Duración',
                'Guardar matriz',
                'monthly',
            ] as $token
        ) {
            if ($token === 'monthly') {
                $this->assertStringNotContainsString(
                    $token,
                    $source
                );

                continue;
            }

            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_plan_links_to_commercial_settings(): void
    {
        $source =
            file_get_contents(
                $this->root()
                .'/resources/js/pages/Admin/DiagnosisRequests/'
                .'ImplementationPlan.vue'
            );

        $this->assertStringContainsString(
            '/admin/transformation-360/commercial-settings',
            $source
        );

        $this->assertStringContainsString(
            'Tarifas T360',
            $source
        );
    }
}
