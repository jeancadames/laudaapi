<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Services\Diagnosis\DataTransformationBiDiagnosticAnalysisWorkspaceProjection;
use Tests\TestCase;

final class DataTransformationBiDiagnosticAnalysisWorkspaceProjectionTest
    extends TestCase
{
    public function test_null_evaluation_exposes_no_analysis(): void
    {
        self::assertNull(
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    null
                )
        );
    }

    public function test_draft_uses_preview_derived_from_pinned_evidence(): void
    {
        $evaluation =
            $this->evaluation(
                DataTransformationBiEvaluation
                    ::STATUS_DRAFT,
                $this->evidenceV2()
            );

        $result =
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertIsArray(
            $result
        );

        self::assertSame(
            'preview',
            $result['mode']
        );

        self::assertFalse(
            $result['frozen']
        );

        self::assertTrue(
            $result['available']
        );

        self::assertSame(
            3,
            $result[
                'analysis_schema_version'
            ]
        );

        self::assertSame(
            5,
            $result[
                'evidence_version'
            ]
        );

        self::assertNull(
            $result['generated_at']
        );

        self::assertSame(
            9,
            $result['snapshot']
                ['analysis_count']
        );

        self::assertSame(
            [
                'record_identification',
                'geographic_segmentation',
                'temporal_analysis',
                'monetary_analysis',
            ],
            array_slice(
                array_column(
                    $result['snapshot']
                        ['analyses'],
                    'key'
                ),
                0,
                4
            )
        );
    }

    public function test_ready_for_review_uses_exact_frozen_snapshot_without_recomputing(): void
    {
        $frozen = [
            'kind' =>
                'data_bi_diagnostic_analysis',

            /*
             * Deliberately not the current schema version.
             * If the projection recomputes, this marker disappears.
             */
            'schema_version' =>
                77,

            'scope' =>
                'evaluated_delivery',

            'available' =>
                true,

            'analysis_count' =>
                1,

            'analyses' => [
                [
                    'key' =>
                        'frozen_marker',
                ],
            ],
        ];

        $evaluation =
            $this->evaluation(
                DataTransformationBiEvaluation
                    ::STATUS_READY_FOR_REVIEW,
                $this->evidenceV2(),
                $frozen,
                77
            );

        $result =
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertSame(
            'frozen',
            $result['mode']
        );

        self::assertTrue(
            $result['frozen']
        );

        self::assertSame(
            77,
            $result[
                'analysis_schema_version'
            ]
        );

        self::assertSame(
            $frozen,
            $result['snapshot']
        );

        self::assertSame(
            'frozen_marker',
            $result['snapshot']
                ['analyses'][0]['key']
        );

        self::assertSame(
            1,
            $result['snapshot']
                ['analysis_count']
        );
    }

    public function test_published_uses_exact_frozen_snapshot_without_recomputing(): void
    {
        $frozen = [
            'kind' =>
                'data_bi_diagnostic_analysis',

            'schema_version' =>
                31,

            'scope' =>
                'evaluated_delivery',

            'available' =>
                true,

            'analysis_count' =>
                1,

            'analyses' => [
                [
                    'key' =>
                        'published_frozen_marker',
                ],
            ],
        ];

        $evaluation =
            $this->evaluation(
                DataTransformationBiEvaluation
                    ::STATUS_PUBLISHED,
                $this->evidenceV2(),
                $frozen,
                31
            );

        $result =
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertSame(
            'frozen',
            $result['mode']
        );

        self::assertTrue(
            $result['frozen']
        );

        self::assertSame(
            31,
            $result[
                'analysis_schema_version'
            ]
        );

        self::assertSame(
            $frozen,
            $result['snapshot']
        );

        self::assertSame(
            'published_frozen_marker',
            $result['snapshot']
                ['analyses'][0]['key']
        );
    }

    public function test_historical_published_v1_without_analysis_is_not_recomputed(): void
    {
        $evidence =
            $this->evidenceV2();

        $evidence['schema_version'] =
            1;

        foreach (
            $evidence['sources']
            as &$source
        ) {
            unset(
                $source[
                    'business_domains'
                ],
                $source[
                    'structural_semantic_signals'
                ]
            );
        }

        unset($source);

        $evaluation =
            $this->evaluation(
                DataTransformationBiEvaluation
                    ::STATUS_PUBLISHED,
                $evidence,
                null,
                null
            );

        $result =
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertSame(
            'historical_unavailable',
            $result['mode']
        );

        self::assertFalse(
            $result['available']
        );

        self::assertFalse(
            $result['frozen']
        );

        self::assertNull(
            $result['snapshot']
        );

        self::assertSame(
            'diagnostic_analysis_not_available_for_historical_evaluation',
            $result[
                'unavailable_reason'
            ]
        );
    }

    public function test_modern_ready_state_without_snapshot_is_explicitly_unavailable_and_not_recomputed(): void
    {
        $evaluation =
            $this->evaluation(
                DataTransformationBiEvaluation
                    ::STATUS_READY_FOR_REVIEW,
                $this->evidenceV2(),
                null,
                null
            );

        $result =
            DataTransformationBiDiagnosticAnalysisWorkspaceProjection
                ::fromEvaluation(
                    $evaluation
                );

        self::assertSame(
            'frozen_unavailable',
            $result['mode']
        );

        self::assertFalse(
            $result['available']
        );

        self::assertNull(
            $result['snapshot']
        );

        self::assertSame(
            'frozen_diagnostic_analysis_snapshot_not_available',
            $result[
                'unavailable_reason'
            ]
        );
    }

    public function test_projection_has_no_live_source_query(): void
    {
        $source =
            (string) file_get_contents(
                dirname(
                    __DIR__,
                    3
                )
                .'/app/Services/Diagnosis/'
                .'DataTransformationBiDiagnosticAnalysisWorkspaceProjection.php'
            );

        foreach ([
            'DataTransformationBiSourceAsset',
            '$sourcePayloads',
            '::query()',
            'DB::',
            'profiling_snapshot',
        ] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $source
            );
        }

        self::assertStringContainsString(
            '->evidence_snapshot',
            $source
        );

        self::assertStringContainsString(
            '->diagnostic_analysis_snapshot',
            $source
        );
    }

    /**
     * @param array<string,mixed> $evidence
     * @param array<string,mixed>|null $frozen
     */
    private function evaluation(
        string $status,
        array $evidence,
        ?array $frozen = null,
        ?int $analysisSchemaVersion = null
    ): DataTransformationBiEvaluation {
        $evaluation =
            new DataTransformationBiEvaluation();

        $evaluation->forceFill([
            'status' =>
                $status,

            'evidence_version' =>
                5,

            'evidence_sha256' =>
                str_repeat(
                    'a',
                    64
                ),

            'evidence_snapshot' =>
                $evidence,

            'diagnostic_analysis_schema_version' =>
                $analysisSchemaVersion,

            'diagnostic_analysis_evidence_version' =>
                $frozen !== null
                    ? 5
                    : null,

            'diagnostic_analysis_sha256' =>
                $frozen !== null
                    ? str_repeat(
                        'b',
                        64
                    )
                    : null,

            'diagnostic_analysis_snapshot' =>
                $frozen,

            'diagnostic_analysis_generated_at' =>
                $frozen !== null
                    ? '2026-09-28 22:45:00'
                    : null,
        ]);

        return $evaluation;
    }

    /**
     * @return array<string,mixed>
     */
    private function evidenceV2(): array
    {
        return [
            'kind' =>
                'data_bi_diagnostic_evaluation_evidence',

            'schema_version' =>
                2,

            'session_id' =>
                10,

            'company_id' =>
                20,

            'transformation_implementation_request_id' =>
                30,

            'submission_manifest_sha256' =>
                str_repeat(
                    'f',
                    64
                ),

            'source_count' =>
                1,

            'sources' => [
                [
                    'source_asset_id' =>
                        100,

                    'display_name' =>
                        'Clientes',

                    'source_object_name' =>
                        'dbo.Clientes',

                    'diagnostic_summary' => [
                        'available' =>
                            true,
                    ],

                    'business_domains' => [
                        [
                            'domain' =>
                                'Clientes',

                            'group' =>
                                'gestion',
                        ],
                    ],

                    'structural_semantic_signals' => [
                        'kind' =>
                            'data_bi_structural_semantic_signals',

                        'schema_version' =>
                            1,

                        'available' =>
                            true,

                        'signals' => [
                            [
                                'key' =>
                                    'identifier',

                                'label' =>
                                    'Identificación',

                                'column_count' =>
                                    1,

                                'columns' => [
                                    [
                                        'sheet_index' =>
                                            0,

                                        'sheet_name' =>
                                            'Datos',

                                        'column_key' =>
                                            'cliente_id',

                                        'column_index' =>
                                            0,

                                        'header' =>
                                            'ClienteId',

                                        'matched_term' =>
                                            'id',

                                        'coverage_status' =>
                                            'complete',

                                        'non_empty_percent' =>
                                            100.0,

                                        'observed_type_families' => [
                                            'text',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
