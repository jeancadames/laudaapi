<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiCommercialBoundaryCopyContractTest
    extends TestCase
{
    private function tenantPage(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/DataTransformationBi.vue'
        );
    }

    public function test_tenant_presents_bi_as_optional_professional_service(): void
    {
        $source = $this->tenantPage();

        foreach ([
            'Servicio profesional',
            'Opcional',
            'Servicio profesional opcional',
            '¿Por qué puede ser relevante?',
            'Alcance potencial del servicio',
            'Fase de referencia',
            'Solicitar evaluación para implementación',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'Recomendado por tu Diagnóstico 360',
            'Recomendada en tu Plan de Implementación',
            'Recomendado en tu Plan de Implementación',
            'Incluida en tu Plan de Implementación',
            '¿Por qué se recomienda?',
            'Alcance considerado',
            'Sin recomendación vigente dentro del Plan.',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_tenant_preserves_explicit_commercial_boundary(): void
    {
        $source = $this->tenantPage();

        foreach ([
            'no constituye contratación del servicio',
            'Enviar la solicitud no genera cargos ni contrata',
            'LAUDA podrá presentar alcance comercial, precio y',
            'condiciones para tu aprobación antes de cualquier',
            'contratación o ejecución.',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }
    }

    public function test_professional_section_is_optional_without_recommendation_copy(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/App/Transformation360.vue'
        );

        $marker =
            '<!-- IMPLEMENTATION-ONLY PROFESSIONAL CAPABILITIES -->';

        $start = strpos($source, $marker);

        $this->assertNotFalse($start);

        $end = strpos(
            $source,
            '</section>',
            $start
        );

        $this->assertNotFalse($end);

        $section = substr(
            $source,
            $start,
            $end - $start
        );

        $this->assertStringContainsString(
            'Servicios profesionales opcionales',
            $section
        );

        $this->assertStringContainsString(
            'Servicio profesional · Opcional',
            $section
        );

        $this->assertStringNotContainsString(
            'Recomendado por tu Diagnóstico 360',
            $section
        );

        $this->assertStringNotContainsString(
            'Recomendado en tu Plan de Implementación',
            $section
        );
    }

    public function test_admin_supervisor_uses_optional_scope_language(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/'
            .'Transformation360/DataBi.vue'
        );

        foreach ([
            'Empresas con alcance BI identificado en Plan 360',
            'Servicio profesional opcional',
            'Alcance potencial del servicio',
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $source
            );
        }

        foreach ([
            'Empresas con BI recomendado en Plan 360',
            'Recomendado en Plan 360',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }
}
