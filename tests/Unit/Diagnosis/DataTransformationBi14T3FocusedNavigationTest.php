<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBi14T3FocusedNavigationTest extends TestCase
{
    private function vue(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ) ?: '';
    }

    public function test_actions_are_scoped_without_changing_bi13_coverage(): void
    {
        $vue = $this->vue();
        $start = strpos($vue, '<!-- BI14_T3_FOCUSED_ACTIONS');
        $end = strpos($vue, '<!-- BI13_T3_COVERAGE', $start ?: 0);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $actions = substr($vue, $start, $end - $start);
        self::assertStringContainsString('v-show="bi13Ux2R5Visible(\'requirements\')"', $actions);
        self::assertSame(5, substr_count($actions, 'class="rounded-lg border p-2"'));
        // Cinco categorías, más el summary independiente del panel principal.
        foreach ([
            'pendingDecisions',
            'clarificationNeeds',
            'unlinkedNeeds',
            'partiallyLinkedNeeds',
            'fullyLinkedNeeds',
        ] as $metric) {
            self::assertSame(1, substr_count(
                $actions,
                '<details v-if="dataBiFunctionalCoverageMetrics().' . $metric . '" class="rounded-lg border p-2">'
            ));
        }
        self::assertSame(5, substr_count(
            $actions,
            '<summary class="cursor-pointer font-semibold focus-visible:outline'
        ));
        self::assertSame(1, substr_count(
            $actions,
            '<summary class="cursor-pointer font-semibold">'
        ));
        self::assertSame(5, substr_count($actions, '@click="bi14T2FocusNeed(row.key,'));
        self::assertStringNotContainsString('humanReviewForm.patch(', $actions);
        self::assertStringNotContainsString('dataBiEnsureNeedLink(', $actions);

        $coverage = substr($vue, $end, strpos($vue, '<!-- BI13_T2', $end) - $end);
        self::assertStringNotContainsString('@click=', $coverage);
    }

    public function test_target_scroll_and_link_details_are_local_only(): void
    {
        $vue = $this->vue();
        self::assertSame(1, substr_count($vue, ':data-bi14-evaluation-need="improvement.key"'));
        self::assertSame(1, substr_count($vue, ':data-bi14-link-need="improvement.key"'));
        $start = strpos($vue, 'function bi14T2FocusNeed(');
        $end = strpos($vue, 'function bi13Ux2R2Matches(', $start ?: 0);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $helper = substr($vue, $start, $end - $start);
        self::assertStringContainsString('document.querySelectorAll', $helper);
        self::assertStringContainsString('matching as HTMLDetailsElement', $helper);
        self::assertStringContainsString('document.getElementById(', $helper);
        self::assertStringNotContainsString('humanReviewForm.patch(', $helper);
        self::assertStringNotContainsString('dataBiEnsureNeedLink(', $helper);
    }
}
