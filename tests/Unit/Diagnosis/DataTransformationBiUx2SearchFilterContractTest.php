<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiUx2SearchFilterContractTest extends TestCase
{
    private function readVueSource(): string
    {
        $view = file_get_contents(base_path(
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ));
        $this->assertIsString($view);
        return $view;
    }

    public function test_filters_are_visual_and_both_lists_share_the_same_source(): void
    {
        $view = $this->readVueSource();
        $this->assertStringContainsString('function dataBiUx2FilteredNeeds()', $view);
        $this->assertStringContainsString('v-model="dataBiUx2Search"', $view);
        $this->assertStringContainsString('v-model="dataBiUx2Group"', $view);
        $this->assertStringContainsString('v-model="dataBiUx2Status"', $view);
        $this->assertSame(2, substr_count($view, 'v-for="improvement in dataBiUx2FilteredNeeds()"')
            + substr_count($view, 'v-for="(improvement, index) in dataBiUx2FilteredNeeds()"'));
        $this->assertStringContainsString('need.group !== dataBiUx2Group.value', $view);
        $this->assertStringContainsString("status === 'excluded'", $view);
    }

    public function test_search_has_direct_targets_without_changing_persistence(): void
    {
        $view = $this->readVueSource();
        $this->assertStringContainsString('bi13-ux2-eval-${improvement.key}', $view);
        $this->assertStringContainsString('bi13-ux2-link-${improvement.key}', $view);
        $this->assertStringContainsString('dataBiUx2NeedCode(need.key)', $view);
        $this->assertStringContainsString('function dataBiUx2ClearFilters()', $view);
        $this->assertStringContainsString('humanReviewForm.patch(', $view);
        $this->assertStringContainsString('function saveImplementationDefinitionHumanReview()', $view);
    }
}
