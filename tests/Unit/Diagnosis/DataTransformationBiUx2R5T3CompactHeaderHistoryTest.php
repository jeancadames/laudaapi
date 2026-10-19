<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiUx2R5T3CompactHeaderHistoryTest extends TestCase
{
    private function vue(): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
        self::assertIsString($source);
        return $source;
    }

    public function test_header_and_history_are_collapsible_only_for_bi_and_keep_nodes_mounted(): void
    {
        $source = $this->vue();
        self::assertStringContainsString('data-bi13-ux2-r5-t3="history-toggle"', $source);
        self::assertStringContainsString('data-bi13-ux2-r5-t3="context-toggle"', $source);
        self::assertStringContainsString('id="bi13-ux2-r5-t3-history-events" v-show="props.capability.key !== DATA_BI_CAPABILITY || bi13Ux2R5T3HistoryExpanded"', $source);
        self::assertStringContainsString('id="bi13-ux2-r5-t3-company-needs-body" v-show="props.capability.key !== DATA_BI_CAPABILITY || bi13Ux2R5T3ContextExpanded"', $source);
        self::assertStringContainsString('const bi13Ux2R5T3HistoryExpanded = ref(false)', $source);
        self::assertStringContainsString('const bi13Ux2R5T3ContextExpanded = ref(false)', $source);
        self::assertStringContainsString('@click="bi13Ux2R5T3HistoryExpanded = !bi13Ux2R5T3HistoryExpanded"', $source);
        self::assertStringContainsString('@click="bi13Ux2R5T3ContextExpanded = !bi13Ux2R5T3ContextExpanded"', $source);
        self::assertStringContainsString('v-for="event in events"', $source);
        self::assertStringContainsString('v-for="improvement in implementation_request.business_needs.improvements"', $source);
    }

    public function test_five_review_panels_show_action_matching_open_state_and_keep_save_actions(): void
    {
        $source = $this->vue();
        self::assertSame(0, substr_count($source, '<span>Mostrar / ocultar</span>'));
        self::assertSame(5, substr_count($source, 'class="group-open:hidden">Mostrar detalles</span>'));
        self::assertSame(5, substr_count($source, 'class="hidden group-open:inline">Ocultar detalles</span>'));
        foreach (['scope', 'deliverables', 'dependencies', 'responsibilities', 'sources'] as $panel) {
            self::assertSame(1, substr_count($source, 'data-bi13-ux2-r5-t2="' . $panel . '"'));
        }
        self::assertSame(2, substr_count($source, '@click="saveImplementationDefinitionHumanReview"'));
        self::assertStringContainsString('humanReviewForm.patch(', $source);
    }
}
