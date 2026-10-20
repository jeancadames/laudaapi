<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DataTransformationBi15T3GateCoherenceTest extends TestCase
{
    public function test_bi_presentation_copy_respects_four_confirmation_exception(): void
    {
        $root = dirname(__DIR__, 3);
        $vue = file_get_contents($root . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
        $tenantReviewService = file_get_contents($root . '/app/Services/Diagnosis/TransformationImplementationRequestDefinitionTenantReviewService.php');

        $this->assertIsString($vue);
        $this->assertIsString($tenantReviewService);
        $this->assertStringContainsString('En Datos BI, presentar requiere cuatro confirmaciones humanas guardadas', $vue);
        $this->assertStringContainsString('Insumos y accesos se verifican', $vue);
        $this->assertStringContainsString('Condiciones adicionales de presentación', $vue);
        $this->assertStringNotContainsString('El contrato general contempla seis confirmaciones', $vue);
        $this->assertStringNotContainsString('El servidor también exige las validaciones de insumos y accesos', $vue);

        $this->assertSame(1, preg_match(
            '/private function requiredConfirmations\([\s\S]{0,600}?data_transformation_bi[\s\S]{0,450}?return\s*\[([\s\S]{0,550}?)\];/',
            $tenantReviewService,
            $matches
        ));
        foreach (['scope_confirmed', 'deliverables_confirmed', 'dependencies_confirmed', 'responsibilities_confirmed'] as $confirmation) {
            $this->assertStringContainsString("'{$confirmation}'", $matches[1]);
        }
        $this->assertStringNotContainsString('inputs_validated', $matches[1]);
        $this->assertStringNotContainsString('accesses_validated', $matches[1]);
    }
}
