<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

class DiagnosisExpandedReportContractTest extends TestCase
{
    public function test_foundation_keeps_billing_decoupled(): void
    {
        $root = dirname(__DIR__, 3);

        $migration = file_get_contents(
            $root .
            '/database/migrations/2026_08_23_230000_create_diagnosis_expanded_reports_table.php'
        );

        $this->assertIsString($migration);

        $this->assertStringContainsString(
            "'diagnosis_expanded_reports'",
            $migration
        );

        $this->assertStringContainsString(
            "'diagnosis_assessment_id'",
            $migration
        );

        $this->assertStringContainsString(
            "'source_snapshot'",
            $migration
        );

        $this->assertStringContainsString(
            "'sections'",
            $migration
        );

        $this->assertStringNotContainsString(
            "foreignId('company_id')",
            $migration
        );

        $this->assertStringNotContainsString(
            "foreignId('subscription_id')",
            $migration
        );

        $this->assertStringNotContainsString(
            "foreignId('invoice_id')",
            $migration
        );
    }

    public function test_commercial_config_preserves_agreed_offer(): void
    {
        $root = dirname(__DIR__, 3);

        $config = file_get_contents(
            $root . '/config/lauda360_commercial.php'
        );

        $this->assertIsString($config);

        $this->assertStringContainsString(
            "'subtotal' => 29900.00",
            $config
        );

        $this->assertStringContainsString(
            "'subtotal' => 95000.00",
            $config
        );

        $this->assertStringContainsString(
            "'expanded_report_credit' => 29900.00",
            $config
        );

        $this->assertStringContainsString(
            "'expanded_report_credit_window_days' => 30",
            $config
        );
    }

    public function test_model_supports_versioned_review_workflow(): void
    {
        $root = dirname(__DIR__, 3);

        $model = file_get_contents(
            $root .
            '/app/Models/DiagnosisExpandedReport.php'
        );

        $this->assertIsString($model);

        foreach ([
            'STATUS_DRAFT',
            'STATUS_UNDER_REVIEW',
            'STATUS_PUBLISHED',
            "'source_snapshot' => 'array'",
            "'sections' => 'array'",
            'function isEditable()',
            'function isPublished()',
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $model
            );
        }
    }
}
