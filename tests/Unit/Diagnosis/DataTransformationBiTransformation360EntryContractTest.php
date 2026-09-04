<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DataTransformationBiTransformation360EntryContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_dashboard_exposes_implementation_only_capabilities_separately(): void
    {
        $service = file_get_contents(
            $this->root()
            .'/app/Services/Ecosystem/'
            .'SubscriberTransformation360DashboardService.php'
        );

        foreach ([
            "'professional_capabilities' =>",
            'professionalCapabilities(',
            "'activation_policy'",
            "'implementation_only'",
            "'recommended_in_plan'",
            "'phase_name'",
            'transformation_implementation_phase_capabilities',
            'transformation_implementation_phases',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }

        /*
         * El renderer es metadata-driven:
         * no debe existir un branch específico de BI
         * dentro del nuevo método.
         */
        $start = strpos(
            $service,
            'private function professionalCapabilities('
        );

        $end = strpos(
            $service,
            'private function schemaReady()',
            $start
        );

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $method = substr(
            $service,
            $start,
            $end - $start
        );

        $this->assertStringNotContainsString(
            'data_transformation_bi',
            $method
        );

        $this->assertStringContainsString(
            "'activation_policy'"
            ."\n"
            ."                ) !== 'implementation_only'",
            $method
        );

        $this->assertStringNotContainsString(
            'activation_endpoint',
            $method
        );

        $this->assertStringNotContainsString(
            'Subscription::',
            $method
        );
    }

    public function test_transformation_page_renders_professional_block_after_branding(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'Transformation360.vue'
        );

        foreach ([
            'ImplementationProfessionalCapability',
            'professional_capabilities',
            'implementationOnlyCapabilities',
            'IMPLEMENTATION-ONLY PROFESSIONAL CAPABILITIES',
            'Servicio profesional',
            'Recomendado por tu Diagnóstico 360',
            'Recomendado en tu Plan de Implementación',
            'Datos e Inteligencia:',
            'Se define y cotiza en',
            'Implementación.',
            'Ver Plan',
            'Ver Roadmap',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $page
            );
        }

        $brandingPosition = strpos(
            $page,
            'v-if="optionalBranding"'
        );

        $professionalPosition = strpos(
            $page,
            'IMPLEMENTATION-ONLY PROFESSIONAL CAPABILITIES'
        );

        $this->assertNotFalse($brandingPosition);
        $this->assertNotFalse($professionalPosition);

        $this->assertGreaterThan(
            $brandingPosition,
            $professionalPosition
        );
    }

    public function test_bi_is_not_added_to_optional_branding_contract(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'Transformation360.vue'
        );

        $this->assertStringNotContainsString(
            'optional_capabilities?.data_transformation_bi',
            $page
        );

        $this->assertStringNotContainsString(
            "capability_key: 'data_transformation_bi'",
            $page
        );
    }

    public function test_professional_ui_has_no_activation_action(): void
    {
        $page = file_get_contents(
            $this->root()
            .'/resources/js/pages/App/'
            .'Transformation360.vue'
        );

        $start = strpos(
            $page,
            '<!-- IMPLEMENTATION-ONLY PROFESSIONAL CAPABILITIES -->'
        );

        $this->assertNotFalse($start);

        $block = substr(
            $page,
            $start
        );

        foreach ([
            'Iniciar evaluación',
            'Activar',
            'activation_endpoint',
            'Subscription',
            'Contratar ahora',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $block
            );
        }
    }
}
