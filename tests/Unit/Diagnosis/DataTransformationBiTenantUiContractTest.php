<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantUiContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );
    }

    public function test_page_has_clear_information_hierarchy(): void
    {
        $source = $this->source();

        foreach ([
            'Estado de la capacidad',
            'Diagnóstico · Datos e Inteligencia',
            'Prioridad',
            'Plan de Implementación',
            '¿Por qué se recomienda?',
            'Alcance considerado',
            'Próximo paso',
            'Alcance de esta vista',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_plan_context_is_presented_once_and_not_as_navigation(): void
    {
        $source = $this->source();

        $this->assertSame(
            1,
            substr_count(
                $source,
                'Recomendado en tu Plan de Implementación'
            )
        );

        $this->assertStringContainsString(
            'capability.phase_name',
            $source
        );

        $this->assertStringNotContainsString(
            'Ver Plan de Implementación',
            $source
        );

        $this->assertStringNotContainsString(
            'Ver Roadmap Detallado',
            $source
        );
    }

    public function test_commercial_panel_is_not_prominent_in_the_hero(): void
    {
        $source = $this->source();

        $this->assertStringNotContainsString(
            'Etapa comercial',
            $source
        );

        $this->assertStringContainsString(
            'Esta etapa no inicia ejecución, contratación,',
            $source
        );
    }

    public function test_future_request_area_does_not_fake_activation(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'Preparar la solicitud de implementación',
            $source
        );

        $this->assertStringContainsString(
            'La solicitud no activará servicios ni generará',
            $source
        );

        $this->assertStringNotContainsString(
            '<Button',
            $source
        );

        $this->assertStringNotContainsString(
            'Solicitar implementación</',
            $source
        );
    }

    public function test_summary_cards_have_consistent_layout(): void
    {
        $source = $this->source();

        $this->assertGreaterThanOrEqual(
            3,
            substr_count(
                $source,
                'flex min-h-36 flex-col rounded-2xl'
            )
        );

        $this->assertStringContainsString(
            'md:grid-cols-3',
            $source
        );
    }

    public function test_parent_navigation_is_preserved(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'Volver a Transformación 360',
            $source
        );

        $this->assertStringContainsString(
            'href="/app/transformacion-360"',
            $source
        );
    }
}
