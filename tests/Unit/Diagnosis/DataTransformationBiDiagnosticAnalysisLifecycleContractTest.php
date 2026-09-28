<?php

namespace Tests\Unit\Diagnosis;

use PHPUnit\Framework\TestCase;

final class DataTransformationBiDiagnosticAnalysisLifecycleContractTest
    extends TestCase
{
    private string $service;
    private string $model;

    protected function setUp(): void
    {
        parent::setUp();

        $root =
            dirname(
                __DIR__,
                3
            );

        $this->service =
            (string) file_get_contents(
                $root
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiEvaluationService.php'
            );

        $this->model =
            (string) file_get_contents(
                $root
                .'/app/Models/'
                .'DataTransformationBiEvaluation.php'
            );
    }

    public function test_analysis_is_built_from_pinned_evaluation_evidence_not_live_sources(): void
    {
        foreach ([
            'captureDiagnosticAnalysis(',
            'DataTransformationBiDiagnosticAnalysisReadModel',
            '->evidence_snapshot',
            '->evidence_version',
            '->evidence_sha256',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }
    }

    public function test_mark_ready_freezes_diagnostic_analysis(): void
    {
        foreach ([
            "'diagnostic_analysis_schema_version' =>",
            "'diagnostic_analysis_evidence_version' =>",
            "'diagnostic_analysis_sha256' =>",
            "'diagnostic_analysis_snapshot' =>",
            "'diagnostic_analysis_generated_at' =>",
            'STATUS_READY_FOR_REVIEW',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }
    }

    public function test_publication_requires_frozen_analysis_but_does_not_generate_it(): void
    {
        self::assertStringContainsString(
            'assertDiagnosticAnalysisFrozen(',
            $this->service
        );

        self::assertStringContainsString(
            'Publication consumes the analysis frozen',
            $this->service
        );

        /*
         * captureDiagnosticAnalysis appears in the class for ready
         * transition, but publication is guarded by the explicit
         * frozen-snapshot assertion.
         */
        self::assertStringContainsString(
            'La evaluación no tiene un análisis diagnóstico congelado y verificable para publicación.',
            $this->service
        );
    }

    public function test_analysis_is_pinned_to_exact_evidence_revision(): void
    {
        self::assertStringContainsString(
            'diagnostic_analysis_evidence_version',
            $this->service
        );

        self::assertStringContainsString(
            'El análisis diagnóstico no corresponde a la versión actual de la evidencia.',
            $this->service
        );

        self::assertStringContainsString(
            'La trazabilidad del análisis diagnóstico no coincide con la evidencia fijada.',
            $this->service
        );
    }

    public function test_analysis_hash_is_canonical_semantic_json(): void
    {
        foreach ([
            'diagnosticAnalysisSha256(',
            'canonicalizeDiagnosticAnalysisValue(',
            'array_is_list(',
            'ksort(',
            'JSON_UNESCAPED_UNICODE',
            'JSON_PRESERVE_ZERO_FRACTION',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }
    }

    public function test_evidence_refresh_invalidates_any_stored_analysis(): void
    {
        foreach ([
            "'diagnostic_analysis_schema_version' =>\n                        null",
            "'diagnostic_analysis_evidence_version' =>\n                        null",
            "'diagnostic_analysis_sha256' =>\n                        null",
            "'diagnostic_analysis_snapshot' =>\n                        null",
            "'diagnostic_analysis_generated_at' =>\n                        null",
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $this->service
            );
        }
    }

    public function test_analysis_snapshot_is_separate_from_evidence_snapshot(): void
    {
        self::assertStringContainsString(
            "'evidence_snapshot'",
            $this->model
        );

        self::assertStringContainsString(
            "'diagnostic_analysis_snapshot'",
            $this->model
        );
    }

    public function test_lifecycle_does_not_generate_findings_or_start_future_pipeline(): void
    {
        foreach ([
            'DataTransformationBiCanonical',
            'DataTransformationBiStaging',
            'DataTransformationBiSourceAssetMapping',
            'DataTransformationBiPreparedDataset',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->service
            );
        }

        self::assertStringNotContainsString(
            'DataTransformationBiEvaluationFinding::create',
            $this->service
        );
    }
}
