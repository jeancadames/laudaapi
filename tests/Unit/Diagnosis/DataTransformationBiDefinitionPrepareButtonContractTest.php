<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDefinitionPrepareButtonContractTest
    extends TestCase
{
    public function test_initial_prepare_does_not_receive_click_event_as_reprepare_flag(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/Transformation360/'
            .'ImplementationRequests/Show.vue'
        );

        $this->assertStringContainsString(
            '@click="generateImplementationDefinition(false)"',
            $source
        );

        $this->assertStringContainsString(
            '@click="generateImplementationDefinition(true)"',
            $source
        );

        $this->assertStringNotContainsString(
            '@click="generateImplementationDefinition"',
            $source
        );
    }

    public function test_generate_function_keeps_reprepare_explicit(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3)
            .'/resources/js/pages/Admin/Transformation360/'
            .'ImplementationRequests/Show.vue'
        );

        $this->assertStringContainsString(
            'function generateImplementationDefinition(reprepare = false): void',
            $source
        );

        $this->assertStringContainsString(
            '? { reprepare: true }',
            $source
        );
    }
}
