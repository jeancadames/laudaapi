<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiUx2R5WorkspaceModesTest extends TestCase
{
    public function test_hybrid_navigation_has_five_real_actions(): void
    {
        $vue = file_get_contents(dirname(__DIR__, 3) . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
        self::assertIsString($vue);
        foreach (['needs', 'proposals', 'requirements', 'links', 'review'] as $section) {
            self::assertStringContainsString("@click=\"bi13Ux2R5Select('{$section}')\"", $vue);
            self::assertStringContainsString("bi13Ux2R5Visible('{$section}')", $vue);
        }
        self::assertStringContainsString('Por secciones', $vue);
        self::assertStringContainsString('Vista continua', $vue);
    }

    public function test_workspaces_hide_instead_of_unmounting_forms(): void
    {
        $vue = file_get_contents(dirname(__DIR__, 3) . '/resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue');
        self::assertStringContainsString('R5_REQUIREMENTS', $vue);
        self::assertStringContainsString('R5_LINKS', $vue);
        self::assertStringContainsString('R5_REVIEW_SECTIONS', $vue);
        self::assertStringContainsString('R5_REVIEW_FOOTER', $vue);
        self::assertStringContainsString('props.capability.key !== DATA_BI_CAPABILITY', $vue);
        self::assertStringContainsString('function saveImplementationDefinitionHumanReview()', $vue);
        self::assertStringContainsString('humanReviewForm.patch(', $vue);
        self::assertStringContainsString('v-show="bi13Ux2R5Visible(', $vue);
    }
}
