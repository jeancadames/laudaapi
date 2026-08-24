<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisTransformationCapabilitiesContractTest extends TestCase
{
    public function test_generator_has_procedures_and_branding_capabilities(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php'
        );

        foreach ([
            "'transformation_capabilities' =>",
            "'procedures_guide' =>",
            'Guía de Procesos y Procedimientos LAUDA 360',
            "'branding_identity' =>",
            'Branding e Identidad Digital',
            "'type' =>",
            "'structural'",
            "'optional'",
            "'requires_lauda_review'",
            'no modifican la puntuación',
            'se cotizan y planifican por separado',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_branding_recommendation_requires_explicit_business_signal(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php'
        );

        foreach ([
            "'branding'",
            "'marca'",
            "'identidad'",
            "'imagen corporativa'",
            "'rebranding'",
            '$brandingRecommended = false;',
            'str_contains(',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_publish_completeness_requires_capability_structure(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        foreach ([
            'transformation_capabilities.procedures_guide.title',
            'transformation_capabilities.branding_identity.title',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_admin_and_client_expose_capability_component(): void
    {
        $root = dirname(__DIR__, 3);

        $component = file_get_contents(
            $root
            . '/resources/js/components/diagnosis/DetailedRoadmapTransformationCapabilities.vue'
        );

        foreach ([
            'Capacidades de Transformación Detallada',
            'Estructural',
            'Opcional',
            'Recomendado para revisión',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $component
            );
        }

        foreach ([
            '/resources/js/pages/Admin/DiagnosisRequests/DetailedRoadmap.vue',
            '/resources/js/pages/Diagnosis/DetailedRoadmap.vue',
        ] as $file) {
            $source =
                file_get_contents(
                    $root . $file
                );

            $this->assertStringContainsString(
                'DetailedRoadmapTransformationCapabilities',
                $source
            );

            $this->assertStringContainsString(
                'content.transformation_capabilities',
                $source
            );
        }
    }
}
