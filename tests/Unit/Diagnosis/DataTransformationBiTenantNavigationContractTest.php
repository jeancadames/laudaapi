<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiTenantNavigationContractTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private function read(string $path): string
    {
        return file_get_contents(
            $this->root().'/'.$path
        );
    }

    public function test_bi_surface_keeps_only_parent_transformation_navigation(): void
    {
        $source = $this->read(
            'resources/js/pages/App/DataTransformationBi.vue'
        );

        $this->assertStringContainsString(
            'Volver a Transformación 360',
            $source
        );

        $this->assertStringContainsString(
            '/app/transformacion-360',
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

    public function test_plan_can_still_be_referenced_as_context_not_as_bi_action(): void
    {
        $source = $this->read(
            'resources/js/pages/App/DataTransformationBi.vue'
        );

        /*
         * Es correcto decir:
         * "Recomendado en tu Plan de Implementación"
         * o mostrar el estado del Plan.
         *
         * Lo que se prohíbe son los CTA que hacen parecer
         * Plan/Roadmap funcionalidades propias de BI.
         */
        $this->assertStringContainsString(
            'Plan de Implementación',
            $source
        );

        $this->assertStringNotContainsString(
            'Ver Plan de Implementación',
            $source
        );
    }

    public function test_catalog_uses_company_language(): void
    {
        $source = $this->read(
            'app/Services/Diagnosis/'
            .'TransformationProfessionalCapabilityCatalog.php'
        );

        $this->assertStringContainsString(
            'estructurar los datos de la empresa',
            $source
        );

        $this->assertStringNotContainsString(
            'estructurar los datos del tenant',
            $source
        );
    }

    public function test_admin_bi_uses_human_copy_instead_of_internal_key(): void
    {
        $source = $this->read(
            'resources/js/pages/Admin/Transformation360/DataBi.vue'
        );

        $normalized = preg_replace(
            '/\s+/',
            ' ',
            $source
        );

        $this->assertStringContainsString(
            'Solo aparecen Planes que incluyen la capacidad de Datos e Inteligencia BI.',
            $normalized
        );

        $this->assertStringNotContainsString(
            'Solo aparecen Planes que contienen la capacidad data_transformation_bi.',
            $normalized
        );
    }
}
