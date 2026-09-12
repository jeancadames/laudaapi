<?php

use Tests\TestCase;

final class TransformationImplementationRequestDefinitionReviewErrorUiContractTest
    extends TestCase
{
    public function test_human_review_ui_exposes_exact_validation_errors(): void
    {
        $source =
            file_get_contents(
                resource_path(
                    'js/pages/Admin/Transformation360/ImplementationRequests/Show.vue'
                )
            );

        $this->assertStringContainsString(
            'humanReviewForm.errors',
            $source
        );

        $this->assertStringContainsString(
            'v-for="(message, field) in humanReviewForm.errors"',
            $source
        );

        $this->assertStringContainsString(
            '{{ field }}',
            $source
        );

        $this->assertStringContainsString(
            '{{ message }}',
            $source
        );

        $this->assertStringContainsString(
            'Revisa los campos indicados antes de guardar.',
            $source
        );
    }
}
