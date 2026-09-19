<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSourceReadinessContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path =
            dirname(__DIR__, 3)
            .'/app/Services/Diagnosis/'
            .'DataTransformationBiSourceReadinessService.php';

        $this->source =
            file_get_contents(
                $path
            );

        self::assertIsString(
            $this->source
        );
    }

    public function test_readiness_uses_latest_request_scoped_session(): void
    {
        foreach ([
            "'company_id'",
            "'transformation_implementation_request_id'",
            "->orderByDesc('id')",
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_readiness_uses_only_active_dynamic_sources(): void
    {
        foreach ([
            "'data_transformation_bi_intake_session_id'",
            "'archived_at'",
            '::STATUS_ACTIVE',
            '::STATUS_READY',
            '::DATA_RECEIVED',
            '::DATA_ANALYZED',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_every_source_requires_current_uploaded_csv_or_xlsx(): void
    {
        foreach ([
            '->whereHas(',
            "'dataFile'",
            '::STATUS_UPLOADED',
            '::FORMAT_CSV',
            '::FORMAT_XLSX',
            '$completeSourceCount === $sourceCount',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }

    public function test_zero_sources_are_not_validated(): void
    {
        self::assertStringContainsString(
            'if ($sourceCount === 0)',
            $this->source
        );

        self::assertMatchesRegularExpression(
            "/'inputs_validated'\\s*=>\\s*false/",
            $this->source
        );

        self::assertMatchesRegularExpression(
            "/'accesses_validated'\\s*=>\\s*false/",
            $this->source
        );
    }

    public function test_legacy_access_name_does_not_introduce_remote_access(): void
    {
        $lower =
            strtolower(
                $this->source
            );

        foreach ([
            'password',
            'connection_string',
            'sqlsrv_connect',
            'odbc_connect',
            'db::connection',
            'domain_key',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $lower
            );
        }

        self::assertStringContainsString(
            'does not request, store or',
            $lower
        );

        self::assertStringContainsString(
            'remote database access',
            $lower
        );
    }

    public function test_schema_rollout_is_safe(): void
    {
        foreach ([
            "'data_transformation_bi_intake_sessions'",
            "'data_transformation_bi_source_assets'",
            "'data_transformation_bi_source_asset_files'",
            'Schema::hasTable(',
        ] as $required) {
            self::assertStringContainsString(
                $required,
                $this->source
            );
        }
    }
}
