<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSqlServerExtractionPreviewEndpointContractTest
    extends TestCase
{
    private string $controller;

    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(__DIR__, 3);

        $this->controller =
            file_get_contents(
                $root
                .'/app/Http/Controllers/Admin/'
                .'AdminDataTransformationBiIntakeV2Controller.php'
            );

        $this->routes =
            file_get_contents(
                $root
                .'/routes/admin.php'
            );

        self::assertIsString(
            $this->controller
        );

        self::assertIsString(
            $this->routes
        );
    }

    public function test_preview_route_is_registered(): void
    {
        self::assertStringContainsString(
            'sql-server-extraction/preview',
            $this->routes
        );

        self::assertStringContainsString(
            'previewSqlServerExtraction',
            $this->routes
        );
    }

    public function test_preview_is_restricted_to_source_native_domains(): void
    {
        self::assertStringContainsString(
            'DataTransformationBiSourceDomainRegistry',
            $this->controller
        );

        self::assertStringContainsString(
            '::supports(',
            $this->controller
        );

        self::assertStringContainsString(
            'abort_unless(',
            $this->controller
        );
    }

    public function test_preview_accepts_structure_but_not_credentials(): void
    {
        self::assertStringContainsString(
            "'schema_name'",
            $this->controller
        );

        self::assertStringContainsString(
            "'table_name'",
            $this->controller
        );

        self::assertStringContainsString(
            "'structure_text'",
            $this->controller
        );

        self::assertStringNotContainsString(
            "'password'",
            $this->controller
        );

        self::assertStringNotContainsString(
            "'username'",
            $this->controller
        );

        self::assertStringNotContainsString(
            "'connection_string'",
            $this->controller
        );
    }

    public function test_preview_has_no_session_or_database_mutation(): void
    {
        $start =
            strpos(
                $this->controller,
                'public function previewSqlServerExtraction('
            );

        self::assertNotFalse(
            $start
        );

        $end =
            strpos(
                $this->controller,
                'public function downloadDomainCsvTemplate(',
                $start
            );

        self::assertNotFalse(
            $end
        );

        $method =
            substr(
                $this->controller,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            'scopedSession(',
            $method
        );

        self::assertStringNotContainsString(
            'save(',
            $method
        );

        self::assertStringNotContainsString(
            'create(',
            $method
        );

        self::assertStringNotContainsString(
            'update(',
            $method
        );

        self::assertStringNotContainsString(
            'DB::',
            $method
        );
    }
}
