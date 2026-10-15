<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiUx2R4WorkspaceCompactTest extends TestCase
{
    private function vueSource(): string
    {
        $path = dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue';
        $source = file_get_contents($path);
        self::assertIsString($source);
        return $source;
    }

    public function test_quick_groups_keep_r2_filters_and_form_indices(): void
    {
        $source = $this->vueSource();
        foreach (['BI13_UX2_R4_COMPACT_WORKSPACE',
            '@click="bi13Ux2R2Group = group.key"',
            'bi13Ux2R2MatchCount()',
            'v-show="bi13Ux2R2Matches(improvement)"',
            'items[index].status', 'items[index].rationale',
        ] as $needle) self::assertStringContainsString($needle, $source);
    }

    public function test_requirements_fold_without_remounting_inputs(): void
    {
        $source = $this->vueSource();
        self::assertStringContainsString('Editor de requisito plegable', $source);
        self::assertStringContainsString('<details class="mt-3 rounded-lg border border-dashed p-3">', $source);
        foreach (['source.name', 'source.origin_hint', 'source.source_type', 'source.notes'] as $field) {
            self::assertStringContainsString('v-model="'.$field.'"', $source);
        }
        self::assertStringContainsString('dataBiRemoveInformationRequirement(source.key)', $source);
    }

    public function test_links_and_review_contract_remain_intact(): void
    {
        $source = $this->vueSource();
        self::assertStringContainsString('v-show="bi13Ux2R3Visible(improvement)"', $source);
        self::assertStringContainsString("? 'requisito' : 'requisitos'", $source);
        self::assertStringContainsString("? 'entregable' : 'entregables'", $source);
        self::assertStringContainsString('dataBiToggleNeedRelation(', $source);
        self::assertStringContainsString('dataBiUpdateNeedLinkNotes(', $source);
        self::assertStringContainsString('humanReviewForm.patch(', $source);
        self::assertStringContainsString('const options = dataBiDeliverableOptions.value;', $source);
        self::assertStringNotContainsString('dataBiDeliverableOptions.findIndex(', $source);
    }
}
