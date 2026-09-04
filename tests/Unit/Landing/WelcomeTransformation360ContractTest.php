<?php

namespace Tests\Unit\Landing;

use PHPUnit\Framework\TestCase;

class WelcomeTransformation360ContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/Welcome.vue'
        );
    }

    public function test_independent_bi_solution_remains_available(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "id: 'bi'",
            $source
        );

        $this->assertStringContainsString(
            'https://bi.laudaapi.com',
            $source
        );

        $this->assertStringContainsString(
            'Dashboards, métricas, reporting y análisis sobre información conectada y preparada.',
            $source
        );

        $this->assertStringContainsString(
            "focus: 'Análisis + decisiones'",
            $source
        );
    }

    public function test_professional_capabilities_are_presented_in_t360(): void
    {
        $source = $this->source();

        foreach ([
            'Guía de Procesos y Procedimientos',
            'Branding e Identidad Digital',
            'Transformación e Inteligencia de Datos para BI',
        ] as $title) {
            $this->assertStringContainsString(
                $title,
                $source
            );
        }

        $this->assertStringContainsString(
            "id: 'data_transformation_bi'",
            $source
        );

        $this->assertStringContainsString(
            'Extracción, limpieza, normalización, relaciones y modelado de datos',
            $source
        );
    }

    public function test_free_diagnosis_does_not_select_modality(): void
    {
        $source = $this->source();

        foreach ([
            'const assistanceLevels',
            'contactForm.assistance_level',
            'assistance_level:',
            'Solicitar esta modalidad',
            'Nivel de acompañamiento',
            'Quiero que LAUDA me recomiende la modalidad',
        ] as $legacy) {
            $this->assertStringNotContainsString(
                $legacy,
                $source
            );
        }

        $this->assertStringContainsString(
            'La modalidad no se selecciona durante el Diagnóstico.',
            $source
        );

        $this->assertStringContainsString(
            'Se selecciona al iniciar la Etapa de Implementación.',
            $source
        );
    }

    public function test_professional_execution_is_explicitly_separate(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            'La recomendación no activa ni ejecuta automáticamente estos trabajos.',
            $source
        );

        $this->assertStringContainsString(
            'Su alcance técnico, tiempo y precio se definen y cotizan en la Etapa de Implementación.',
            $source
        );
    }

    public function test_diagnosis_intake_contract_remains_present(): void
    {
        $source = $this->source();

        foreach ([
            "source: 'laudaapi.com'",
            "request_type: 'digital_diagnosis_access_request'",
            "intake_type: 'digital_transformation_360'",
            'company_size: form.company_size',
            'main_challenge: form.main_challenge',
            "diagnosis_access: 'apphub_native'",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringContainsString(
            'Informe Ampliado, Roadmap y Plan de Implementación consultivos.',
            $source
        );
    }
}
