<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiUx2R2SearchFilterTest extends TestCase
{
    private function vueSource(): string
    {
        $path = dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue';
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    public function test_search_and_filters_are_client_side_only(): void
    {
        $source = $this->vueSource();
        foreach ([
            'BI13_UX2_R2_SAFE_FILTERS',
            'function bi13Ux2R2Matches(',
            'v-model="bi13Ux2R2Search"',
            'v-model="bi13Ux2R2Group"',
            'v-model="bi13Ux2R2Status"',
            '@click="bi13Ux2R2Clear"',
            'bi13Ux2VisualNeedCode(need.key)',
        ] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function test_both_editable_lists_keep_original_indices_and_dom(): void
    {
        $source = $this->vueSource();
        // R3 conserva v-show y los índices originales. El listado de
        // evaluación usa R2; el de vínculos compone R2 con la vista compacta.
        self::assertSame(1, substr_count($source, 'v-show="bi13Ux2R2Matches(improvement)"'));
        self::assertSame(1, substr_count($source, 'v-show="bi13Ux2R3Visible(improvement)"'));
        self::assertStringContainsString('if (!bi13Ux2R2Matches(need)) return false;', $source);
        self::assertStringContainsString(
            'v-for="(improvement, index) in implementation_request.business_needs.improvements"',
            $source
        );
        self::assertStringContainsString('items[index].status', $source);
        self::assertStringContainsString(
            'v-for="improvement in implementation_request.business_needs.improvements"',
            $source
        );
        self::assertStringContainsString('dataBiToggleNeedRelation(', $source);
        self::assertStringContainsString('dataBiUpdateNeedLinkNotes(', $source);
    }

    public function test_r1_navigation_and_original_review_save_remain(): void
    {
        $source = $this->vueSource();
        self::assertStringContainsString('BI13_UX2_R1_SAFE_NAV', $source);
        self::assertStringContainsString('id="bi-ux2-links"', $source);
        self::assertStringContainsString('@click="saveImplementationDefinitionHumanReview"', $source);
        self::assertStringContainsString('function saveImplementationDefinitionHumanReview(): void', $source);
        self::assertStringContainsString('humanReviewForm.patch(', $source);
    }
}
