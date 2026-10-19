<?php

declare(strict_types=1);

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBi14CoverageMatrixTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3).'/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ) ?: '';
    }

    public function test_matrix_is_read_only_and_uses_current_form(): void
    {
        $source = $this->source();
        self::assertStringContainsString('BI14_T1_COVERAGE_MATRIX', $source);
        self::assertStringContainsString('bi14ClassifyLinks(', $source);
        self::assertStringContainsString('relationByNeed.get(needKey)', $source);
        self::assertStringContainsString('fullyLinkedNeeds', $source);
        self::assertStringContainsString('partiallyLinkedNeeds', $source);
        self::assertStringContainsString('dataBiFunctionalCoverageMetrics().coverageRows', $source);
        self::assertStringContainsString('dataBiNeedDecisionStatus(key)', $source);
        self::assertStringContainsString('humanReviewForm.patch(', $source);
        self::assertStringContainsString('Ver matriz de cobertura por necesidad', $source);
        self::assertSame(1, substr_count($source, 'function bi14ClassifyLinks('));
    }
}
