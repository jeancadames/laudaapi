<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiUx2StickySaveContractTest extends TestCase
{
    private function vueSource(): string
    {
        $source = file_get_contents(base_path(
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ));
        $this->assertIsString($source);

        return $source;
    }

    public function test_sticky_bar_reuses_the_same_save_action_and_permissions(): void
    {
        $source = $this->vueSource();
        $this->assertStringContainsString('<!-- BI13_UX2_T4 · Una sola accion de guardado, siempre accesible -->', $source);
        $this->assertStringContainsString('role="status"', $source);
        $this->assertStringContainsString('humanReviewForm.isDirty', $source);
        $this->assertStringContainsString('humanReviewForm.processing || !humanReviewForm.isDirty', $source);
        $this->assertStringContainsString('props.actions.can_review_definition && props.actions.definition_review_endpoint', $source);
        $this->assertStringContainsString('@click="saveImplementationDefinitionHumanReview"', $source);
        $this->assertStringContainsString('function saveImplementationDefinitionHumanReview(): void {', $source);
        $this->assertStringContainsString('humanReviewForm.patch(', $source);
        $this->assertStringContainsString('Guardar revisión humana', $source);
    }

    public function test_defaults_are_established_only_after_review_sync_not_during_edits(): void
    {
        $source = $this->vueSource();
        $this->assertSame(1, substr_count($source, 'humanReviewForm.defaults();'));
        $this->assertStringContainsString(
            "syncHumanReviewForm();\n        // BI13_UX2_T4",
            $source
        );
        $this->assertStringContainsString('if (props.capability.key === DATA_BI_CAPABILITY) {', $source);
        $this->assertStringContainsString('v-model="dataBiUx2Search"', $source);
        $this->assertStringContainsString('Editar vinculaciones y aclaración', $source);
    }
}
