<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBi14T2ActionablePendingTest extends TestCase
{
    public function test_actions_reuse_local_navigation_without_autosave(): void
    {
        $vue = file_get_contents(
            dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ) ?: '';
        $begin = strpos($vue, 'function bi14T2FocusNeed(');
        $end = strpos($vue, 'function bi13Ux2R2Matches(', $begin ?: 0);
        self::assertNotFalse($begin);
        self::assertNotFalse($end);
        $helper = substr($vue, $begin, $end - $begin);
        self::assertStringContainsString('bi13Ux2R2Clear()', $helper);
        self::assertStringContainsString('bi13Ux2R2Search.value', $helper);
        self::assertStringContainsString('bi13Ux2R5Select(section)', $helper);
        self::assertStringNotContainsString('humanReviewForm.patch(', $helper);
        self::assertStringNotContainsString('dataBiEnsureNeedLink(', $helper);
        self::assertSame(1, substr_count($vue, 'function bi14T2FocusNeed('));
        self::assertStringContainsString("item.coverage === 'unlinked'", $vue);
        self::assertStringContainsString("item.decisionStatus === 'clarification'", $vue);
        self::assertStringContainsString('Pendientes accionables', $vue);
        $coverageStart = strpos($vue, '<!-- BI13_T3_COVERAGE');
        $coverageEnd = strpos($vue, '<!-- BI13_T2', $coverageStart ?: 0);
        self::assertNotFalse($coverageStart);
        self::assertNotFalse($coverageEnd);
        self::assertStringNotContainsString('@click=', substr($vue, $coverageStart, $coverageEnd - $coverageStart));
    }
}
