<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiUx2R5T2NavigationReviewTest extends TestCase
{
    private function vue(): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
        self::assertIsString($source);
        return $source;
    }

    public function test_navigation_has_pointer_hover_focus_and_active_state(): void
    {
        $source = $this->vue();
        self::assertStringContainsString('data-bi13-ux2-r5-t2-nav="1"', $source);
        self::assertStringContainsString('cursor-pointer', $source);
        self::assertStringContainsString('focus-visible:ring-2', $source);
        self::assertStringContainsString('hover:bg-muted', $source);
        foreach (['needs','proposals','requirements','links','review'] as $area) {
            self::assertStringContainsString("@click=\"bi13Ux2R5Select('{$area}')\"", $source);
            self::assertStringContainsString(":aria-pressed=\"bi13Ux2R5Mode === 'focus' && bi13Ux2R5Section === '{$area}'\"", $source);
        }
        self::assertStringContainsString("bi13Ux2R5Mode === 'all' ? 'bg-primary", $source);
    }

    public function test_review_panels_preserve_original_content_and_are_collapsible(): void
    {
        $source = $this->vue();
        foreach (['scope','deliverables','dependencies','responsibilities','sources'] as $panel) {
            self::assertSame(1, substr_count($source, 'data-bi13-ux2-r5-t2="' . $panel . '"'));
        }
        self::assertStringContainsString(':open="props.capability.key !== DATA_BI_CAPABILITY ? true : undefined"', $source);
        self::assertStringContainsString('<summary v-if="props.capability.key === DATA_BI_CAPABILITY"', $source);
        self::assertSame(2, substr_count($source, '@click="saveImplementationDefinitionHumanReview"'));
        self::assertStringContainsString('humanReviewForm.patch(', $source);
        self::assertStringContainsString('R5_REVIEW_FOOTER', $source);
    }
}
