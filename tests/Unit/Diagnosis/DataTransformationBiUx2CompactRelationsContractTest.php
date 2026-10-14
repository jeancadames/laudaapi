<?php

namespace Tests\Unit\Diagnosis;

use Tests\TestCase;

final class DataTransformationBiUx2CompactRelationsContractTest extends TestCase
{
    private function vueSource(): string
    {
        $source = file_get_contents(base_path(
            'resources/js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
        ));
        $this->assertIsString($source);

        return $source;
    }

    public function test_relation_summary_shows_stable_codes_counts_and_real_status(): void
    {
        $source = $this->vueSource();
        $this->assertStringContainsString('dataBiUx2NeedCode(improvement.key)', $source);
        $this->assertStringContainsString('function dataBiUx2LinkedSourceLabels(', $source);
        $this->assertStringContainsString('function dataBiUx2LinkedDeliverableLabels(', $source);
        $this->assertStringContainsString('function dataBiUx2LinkStatusLabel(', $source);
        $this->assertStringContainsString("if (decision === 'excluded') return 'Excluida';", $source);
        $this->assertStringContainsString("if (decision === 'clarification')", $source);
        $this->assertStringContainsString("if (decision === 'pending')", $source);
        $this->assertStringNotContainsString('Vinculación pendiente o editable', $source);
    }

    public function test_contextual_editor_preserves_original_relation_controls(): void
    {
        $source = $this->vueSource();
        $this->assertStringContainsString('Editar vinculaciones y aclaración', $source);
        $this->assertStringContainsString('dataBiToggleNeedRelation(', $source);
        $this->assertStringContainsString('dataBiUpdateNeedLinkNotes(', $source);
        $this->assertStringContainsString('v-for="source in dataBiExpectedPlan().sources"', $source);
        $this->assertStringContainsString('v-for="(deliverable, deliverableIndex) in dataBiDeliverableOptions"', $source);
        $this->assertStringContainsString('v-for="improvement in dataBiUx2FilteredNeeds()"', $source);
        $this->assertStringContainsString('humanReviewForm.patch(', $source);
        $this->assertStringContainsString('function saveImplementationDefinitionHumanReview()', $source);
    }
}
