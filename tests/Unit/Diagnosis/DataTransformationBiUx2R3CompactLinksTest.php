<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiUx2R3CompactLinksTest extends TestCase
{
    private function vueSource(): string
    {
        $path = dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue';
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function test_compact_links_preserve_filters_and_original_inputs(): void
    {
        $source = $this->vueSource();
        foreach ([
            'BI13_UX2_R3_COMPACT_LINKS',
            'function bi13Ux2R3Visible(',
            'bi13Ux2R3FiltersActive()',
            'v-show="bi13Ux2R3Visible(improvement)"',
            'v-show="bi13Ux2R2Matches(improvement)"',
            'Mostrar todas las necesidades',
            'Ver solo vinculadas',
            'Editar vinculaciones y aclaración',
            'dataBiToggleNeedRelation(',
            'dataBiUpdateNeedLinkNotes(',
            'v-for="source in dataBiExpectedPlan().sources"',
            'v-for="deliverable in dataBiDeliverableOptions"',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function test_deliverable_ref_is_unwrapped_in_typescript(): void
    {
        $source = $this->vueSource();
        self::assertStringContainsString(
            'const options = dataBiDeliverableOptions.value;', $source
        );
        self::assertStringContainsString('options.findIndex(', $source);
        self::assertStringNotContainsString('dataBiDeliverableOptions.findIndex(', $source);
    }

    public function test_reactive_status_is_not_human_approval(): void
    {
        $source = $this->vueSource();
        self::assertStringContainsString('function bi13Ux2R3Status(', $source);
        self::assertStringContainsString("decision === 'excluded'", $source);
        self::assertStringContainsString("decision === 'clarification'", $source);
        self::assertStringContainsString("'Pendiente de evaluación'", $source);
        self::assertStringNotContainsString('Vinculación pendiente o editable', $source);
        self::assertStringContainsString('humanReviewForm.patch(', $source);
        self::assertStringContainsString('BI13_UX2_R1_SAFE_NAV', $source);
    }
}
