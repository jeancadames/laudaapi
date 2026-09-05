<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class TransformationImplementationRequestDefinitionReviewLifecycleIsolationContractTest
    extends TestCase
{
    private function project(
        string $path
    ): string {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/'
            .$path
        );
    }

    public function test_under_review_still_counts_as_prepared_content(): void
    {
        $action =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $readModel =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        foreach ([
            "'prepared_for_review'",
            "'under_review'",
        ] as $required) {
            $this->assertStringContainsString(
                $required,
                $action
            );

            $this->assertStringContainsString(
                $required,
                $readModel
            );
        }
    }

    public function test_generate_endpoint_is_draft_only(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        $generateStart =
            strpos(
                $controller,
                'public function generate('
            );

        $reviewStart =
            strpos(
                $controller,
                'public function review('
            );

        $this->assertNotFalse(
            $generateStart
        );

        $this->assertNotFalse(
            $reviewStart
        );

        $this->assertTrue(
            $generateStart < $reviewStart
        );

        $generate =
            substr(
                $controller,
                $generateStart,
                $reviewStart - $generateStart
            );

        $this->assertStringContainsString(
            'TransformationImplementationDefinition::STATUS_DRAFT',
            $generate
        );

        $this->assertStringContainsString(
            '$this->contentPrepared(',
            $generate
        );

        $this->assertStringContainsString(
            '$autogenerator->generate(',
            $generate
        );
    }

    public function test_read_model_hides_generate_for_non_draft_definition(): void
    {
        $controller =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestController.php'
            );

        $start =
            strpos(
                $controller,
                "'can_generate_definition' =>"
            );

        $end =
            strpos(
                $controller,
                "'definition_generate_endpoint' =>",
                $start
            );

        $this->assertNotFalse(
            $start
        );

        $this->assertNotFalse(
            $end
        );

        $chunk =
            substr(
                $controller,
                $start,
                $end - $start
            );

        $this->assertStringContainsString(
            'TransformationImplementationDefinition::STATUS_DRAFT',
            $chunk
        );

        $this->assertStringContainsString(
            '! $definitionContentPrepared',
            $chunk
        );
    }

    public function test_under_review_can_still_receive_human_review(): void
    {
        $service =
            $this->project(
                'app/Services/Diagnosis/'
                .'TransformationImplementationRequestDefinitionReviewService.php'
            );

        $this->assertStringContainsString(
            "'prepared_for_review'",
            $service
        );

        $this->assertStringContainsString(
            "'under_review'",
            $service
        );

        $this->assertStringContainsString(
            'saveReview(',
            $service
        );
    }

    public function test_hardening_does_not_advance_lifecycle(): void
    {
        $action =
            $this->project(
                'app/Http/Controllers/Admin/'
                .'AdminTransformationImplementationRequestDefinitionActionController.php'
            );

        foreach ([
            '->markReady(',
            'transitionByLauda(',
            'transitionByTenant(',
            'STATUS_AWAITING_TENANT_REVIEW',
            'STATUS_DEFINITION_AGREED',
            'STATUS_READY_FOR_COMMERCIAL',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $action
            );
        }
    }
}
