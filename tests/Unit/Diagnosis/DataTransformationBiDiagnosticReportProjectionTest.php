<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiSourceAsset;
use App\Services\Diagnosis\DataTransformationBiDiagnosticReportProjection;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class DataTransformationBiDiagnosticReportProjectionTest
    extends TestCase
{
    public function test_null_evaluation_has_no_report_projection(): void
    {
        self::assertNull(
            DataTransformationBiDiagnosticReportProjection
                ::fromEvaluation(
                    null
                )
        );
    }

    public function test_ready_evaluation_composes_frozen_analysis_and_human_findings(): void
    {
        $frozen = [
            'kind' =>
                'data_bi_diagnostic_analysis',

            'schema_version' =>
                3,

            'scope' =>
                'evaluated_delivery',

            'available' =>
                true,

            'source_count' =>
                2,

            'analysis_count' =>
                1,

            'analyses' => [
                [
                    'key' =>
                        'record_identification',

                    'label' =>
                        'Identificación de registros',

                    'status' =>
                        'supported',
                ],
            ],
        ];

        $source =
            (new DataTransformationBiSourceAsset())
                ->forceFill([
                    'id' => 91,
                    'display_name' => 'Clientes',
                ]);

        $finding =
            (new DataTransformationBiEvaluationFinding())
                ->forceFill([
                    'id' => 77,
                    'finding_type' =>
                        DataTransformationBiEvaluationFinding
                            ::TYPE_WEAKNESS,

                    'title' =>
                        'Tipos mixtos',

                    'details' =>
                        'Se observaron tipos mixtos.',

                    'recommendation' =>
                        'Revisar consistencia.',

                    'priority' =>
                        DataTransformationBiEvaluationFinding
                            ::PRIORITY_MEDIUM,

                    /*
                     * Deliberately stale against evaluation V7.
                     */
                    'evidence_version' =>
                        6,

                    'sort_order' =>
                        10,
                ]);

        $finding->setRelation(
            'sources',
            new Collection([
                $source,
            ])
        );

        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'id' => 55,

                    'status' =>
                        DataTransformationBiEvaluation
                            ::STATUS_READY_FOR_REVIEW,

                    'evidence_version' =>
                        7,

                    'evidence_captured_at' =>
                        '2026-09-29 14:00:00',

                    'evidence_snapshot' => [
                        'schema_version' =>
                            2,
                    ],

                    'diagnostic_analysis_schema_version' =>
                        3,

                    'diagnostic_analysis_evidence_version' =>
                        7,

                    'diagnostic_analysis_snapshot' =>
                        $frozen,

                    'diagnostic_analysis_generated_at' =>
                        '2026-09-29 14:05:00',
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection([
                $finding,
            ])
        );

        $report =
            DataTransformationBiDiagnosticReportProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertIsArray(
            $report
        );

        self::assertSame(
            DataTransformationBiEvaluation
                ::STATUS_READY_FOR_REVIEW,
            $report[
                'report_context'
            ][
                'evaluation_status'
            ]
        );

        self::assertSame(
            7,
            $report[
                'report_context'
            ][
                'evidence_version'
            ]
        );

        self::assertSame(
            3,
            $report[
                'report_context'
            ][
                'analysis_schema_version'
            ]
        );

        self::assertSame(
            2,
            $report[
                'report_context'
            ][
                'evaluated_source_count'
            ]
        );

        self::assertSame(
            'frozen',
            $report[
                'structural_analysis'
            ][
                'mode'
            ]
        );

        self::assertTrue(
            $report[
                'structural_analysis'
            ][
                'frozen'
            ]
        );

        self::assertSame(
            $frozen,
            $report[
                'structural_analysis'
            ][
                'snapshot'
            ]
        );

        self::assertSame(
            1,
            $report[
                'professional_findings'
            ][
                'count'
            ]
        );

        self::assertSame(
            1,
            $report[
                'professional_findings'
            ][
                'weakness_count'
            ]
        );

        self::assertSame(
            0,
            $report[
                'professional_findings'
            ][
                'opportunity_count'
            ]
        );

        self::assertSame(
            0,
            $report[
                'professional_findings'
            ][
                'observation_count'
            ]
        );

        self::assertSame(
            1,
            $report[
                'professional_findings'
            ][
                'stale_count'
            ]
        );

        self::assertFalse(
            $report[
                'professional_findings'
            ][
                'items'
            ][0][
                'evidence_current'
            ]
        );

        self::assertSame(
            [91],
            $report[
                'professional_findings'
            ][
                'items'
            ][0][
                'source_ids'
            ]
        );

        self::assertTrue(
            $report[
                'interpretation_boundaries'
            ][
                'presentation_only'
            ]
        );

        self::assertFalse(
            $report[
                'interpretation_boundaries'
            ][
                'automatic_findings'
            ]
        );

        self::assertFalse(
            $report[
                'interpretation_boundaries'
            ][
                'automatic_recommendations'
            ]
        );
    }

    public function test_historical_published_analysis_unavailability_is_preserved(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation
                            ::STATUS_PUBLISHED,

                    'evidence_version' =>
                        1,

                    'evidence_snapshot' => [
                        'schema_version' =>
                            1,
                    ],

                    'published_at' =>
                        '2026-09-29 14:30:00',

                    'diagnostic_analysis_schema_version' =>
                        null,

                    'diagnostic_analysis_snapshot' =>
                        null,
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection()
        );

        $report =
            DataTransformationBiDiagnosticReportProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertIsArray(
            $report
        );

        self::assertSame(
            'historical_unavailable',
            $report[
                'structural_analysis'
            ][
                'mode'
            ]
        );

        self::assertFalse(
            $report[
                'structural_analysis'
            ][
                'available'
            ]
        );

        self::assertNull(
            $report[
                'report_context'
            ][
                'evaluated_source_count'
            ]
        );

        self::assertSame(
            0,
            $report[
                'professional_findings'
            ][
                'count'
            ]
        );
    }

    public function test_projection_requires_preloaded_professional_relations(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation
                            ::STATUS_DRAFT,

                    'evidence_version' =>
                        1,
                ]);

        self::expectException(
            \LogicException::class
        );

        DataTransformationBiDiagnosticReportProjection
            ::fromEvaluation(
                $evaluation
            );
    }

    public function test_projection_is_presentation_only_and_delegates_analysis_authority(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiDiagnosticReportProjection.php'
            );

        self::assertStringContainsString(
            'DataTransformationBiDiagnosticAnalysisWorkspaceProjection',
            $source
        );

        self::assertStringContainsString(
            '::fromEvaluation(',
            $source
        );

        foreach ([
            '::query(',
            'DB::',
            'loadMissing(',
            'DataTransformationBiDiagnosticAnalysisReadModel',
            'DataTransformationBiTenantPublishedEvaluationProjection',
            'report_snapshot',
            'report_sha',
            'readiness_score',
            'risk_score',
            'opportunity_score',
            'confirmed_join',
            'referential_integrity',
            'canonical_readiness',
            'production_readiness',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_scope_statement_preserves_business_interpretation_boundaries(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation
                            ::STATUS_PUBLISHED,

                    'evidence_version' =>
                        1,

                    'evidence_snapshot' => [
                        'schema_version' =>
                            1,
                    ],
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection()
        );

        $report =
            DataTransformationBiDiagnosticReportProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertSame(
            [
                'Este informe describe únicamente la entrega evaluada.',

                'La ausencia de evidencia en esta entrega no demuestra '
                .'que la empresa carezca de esa información.',

                'Este informe no calcula un porcentaje global '
                .'de preparación para BI.',
            ],
            $report[
                'scope_statement'
            ]
        );
    }
}
