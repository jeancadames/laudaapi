<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisDetailedRoadmapLegacyRegenerationContractTest extends TestCase
{
    public function test_service_allows_regeneration_of_editable_versions(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        foreach ([
            'function regenerateDraft(',
            'DiagnosisDetailedRoadmap::STATUS_DRAFT',
            'DiagnosisDetailedRoadmap::STATUS_UNDER_REVIEW',
            'Solo una versión editable puede regenerarse.',
            'diagnosis_detailed_roadmap_regenerated',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'Solo una versión en borrador puede regenerarse.',
            $source
        );
    }

    public function test_regeneration_does_not_touch_commercial_models(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        foreach ([
            'Invoice::',
            'Payment::',
            'DiagnosisDetailedRoadmapOrder::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_ui_allows_regeneration_while_editable(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/resources/js/pages/Admin/DiagnosisRequests/DetailedRoadmap.vue'
        );

        foreach ([
            'if (!props.roadmap || !canEdit.value) return;',
            'v-if="canEdit"',
            'Regenerar versión editable',
            'se conservarán la versión, el estado comercial y las notas internas',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_paid_publish_gate_remains_intact(): void
    {
        $root = dirname(__DIR__, 3);

        $source = file_get_contents(
            $root
            . '/app/Http/Controllers/Admin/AdminDiagnosisDetailedRoadmapController.php'
        );

        foreach ([
            '$commercialService->hasPaidAccess(',
            'solo puede publicarse después de confirmar el pago',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_current_methodology_requires_capabilities(): void
    {
        $root = dirname(__DIR__, 3);

        $generator = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapGenerator.php'
        );

        $service = file_get_contents(
            $root
            . '/app/Services/Diagnosis/DiagnosisDetailedRoadmapService.php'
        );

        $this->assertStringContainsString(
            "'transformation_capabilities' =>",
            $generator
        );

        foreach ([
            'transformation_capabilities.procedures_guide.title',
            'transformation_capabilities.branding_identity.title',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $service
            );
        }
    }
}
