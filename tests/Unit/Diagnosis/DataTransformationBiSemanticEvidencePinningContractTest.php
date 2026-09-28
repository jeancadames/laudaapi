<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiSemanticEvidencePinningContractTest
    extends TestCase
{
    private string $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );
    }

    public function test_new_evidence_schema_is_v2_while_v1_remains_explicitly_supported(): void
    {
        self::assertStringContainsString(
            'private const LEGACY_EVIDENCE_SCHEMA_VERSION = 1;',
            $this->service
        );

        self::assertStringContainsString(
            'private const EVIDENCE_SCHEMA_VERSION = 2;',
            $this->service
        );

        self::assertStringContainsString(
            'evidenceSchemaVersion(',
            $this->service
        );
    }

    public function test_v2_pins_dynamic_business_domains_and_structural_semantic_signals(): void
    {
        foreach ([
            "'business_domains'",
            "'structural_semantic_signals'",
            'DataTransformationBiStructuralSemanticSignalsReadModel',
            'businessDomains(',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }
    }

    public function test_v2_pins_delivery_level_semantic_diagnostic(): void
    {
        self::assertStringContainsString(
            "\$snapshot['semantic_diagnostic']",
            $this->service
        );

        self::assertStringContainsString(
            'DataTransformationBiSemanticDiagnosticReadModel',
            $this->service
        );

        self::assertStringContainsString(
            '::fromSources(',
            $this->service
        );
    }

    public function test_existing_evaluation_is_checked_using_its_original_schema_version(): void
    {
        self::assertStringContainsString(
            "\$this->evidenceSchemaVersion(",
            $this->service
        );

        self::assertStringContainsString(
            "\$evaluation",
            $this->service
        );

        self::assertStringContainsString(
            '?int $schemaVersion = null',
            $this->service
        );
    }

    public function test_semantic_evidence_stays_inside_diagnostic_snapshot_not_database_schema(): void
    {
        foreach ([
            'Schema::',
            'DB::statement',
            'create_data_transformation_bi_semantic',
            'semantic_diagnostic_id',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->service
            );
        }
    }

    public function test_semantic_evidence_does_not_start_future_pipeline_or_scoring(): void
    {
        foreach ([
            'DataTransformationBiSourceDomainRegistry',
            'DataTransformationBiStaging',
            'DataTransformationBiCanonical',
            'DataTransformationBiPreparedDataset',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->service
            );
        }

        foreach ([
            "'score' =>",
            '"score" =>',
            "'readiness_score' =>",
            '"readiness_score" =>',
            "'capability_status' =>",
            '"capability_status" =>',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                strtolower(
                    $this->service
                )
            );
        }
    }
}
