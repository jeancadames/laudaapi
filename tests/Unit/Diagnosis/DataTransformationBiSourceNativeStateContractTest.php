<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceNativeStateContractTest
    extends TestCase
{
    private string $state;

    private string $view;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->state =
            file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiIntakeV2StateService.php'
            );

        $this->view =
            file_get_contents(
                $root
                .'/resources/js/pages/Admin/Transformation360/'
                .'ImplementationRequests/Show.vue'
            );

        self::assertIsString(
            $this->state
        );

        self::assertIsString(
            $this->view
        );
    }

    public function test_state_exposes_source_native_metadata(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiSourceDomainFile',
            $this->state
        );

        self::assertStringContainsString(
            "'source_native_supported'",
            $this->state
        );

        self::assertStringContainsString(
            "'source_native'",
            $this->state
        );

        self::assertStringContainsString(
            "'source_structure_snapshot'",
            $this->state
        );

        self::assertStringContainsString(
            "'reader_configuration'",
            $this->state
        );
    }

    public function test_state_never_exposes_private_source_path(): void
    {
        self::assertStringNotContainsString(
            "'source_path'",
            $this->state
        );
    }

    public function test_session_draft_status_is_translated(): void
    {
        self::assertStringContainsString(
            'standardIntakeV2SessionStatusLabel',
            $this->view
        );

        self::assertStringContainsString(
            "draft: 'Borrador'",
            $this->view
        );
    }
}
