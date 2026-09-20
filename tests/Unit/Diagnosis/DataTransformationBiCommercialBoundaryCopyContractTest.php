<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiCommercialBoundaryCopyContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );
    }

    public function test_plan_recommendation_never_implies_service_inclusion(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'Recomendada en tu Plan de Implementación',
            $source
        );

        $this->assertStringContainsString(
            'Recomendado en tu Plan de Implementación',
            $source
        );

        $this->assertStringNotContainsString(
            'Incluida en tu Plan de Implementación',
            $source
        );

        $this->assertStringNotContainsString(
            'Esta capacidad todavía no está incluida en',
            $source
        );

        $this->assertStringNotContainsString(
            'debe formar parte de un Plan',
            $source
        );

        $this->assertStringContainsString(
            'debe estar recomendada en un Plan',
            $source
        );
    }

    public function test_request_is_free_but_service_remains_commercially_separate(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'Solicitar evaluación para implementación',
            $source
        );

        $this->assertStringContainsString(
            'no constituye contratación del servicio',
            $source
        );

        $this->assertStringContainsString(
            'Enviar la solicitud no genera cargos ni contrata',
            $source
        );

        $this->assertStringContainsString(
            'LAUDA podrá presentar alcance comercial, precio y',
            $source
        );

        $this->assertStringContainsString(
            'condiciones para tu aprobación antes de cualquier',
            $source
        );

        $this->assertStringContainsString(
            'contratación o ejecución.',
            $source
        );
    }
}
