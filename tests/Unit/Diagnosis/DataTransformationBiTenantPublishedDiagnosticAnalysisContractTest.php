<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Services\Diagnosis\DataTransformationBiTenantPublishedEvaluationProjection;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class DataTransformationBiTenantPublishedDiagnosticAnalysisContractTest
    extends TestCase
{
    private string $projectionSource;
    private string $tenantUi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectionSource =
            file_get_contents(
                base_path(
                    'app/Services/Diagnosis/'
                    .'DataTransformationBiTenantPublishedEvaluationProjection.php'
                )
            );

        $this->tenantUi =
            file_get_contents(
                base_path(
                    'resources/js/pages/App/'
                    .'DataTransformationBi.vue'
                )
            );
    }

    public function test_published_projection_exposes_sanitized_frozen_analysis(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation::STATUS_PUBLISHED,

                    'published_at' =>
                        '2026-09-28 13:11:29',

                    'diagnostic_analysis_schema_version' =>
                        2,

                    'diagnostic_analysis_snapshot' => [
                        'kind' =>
                            'data_bi_diagnostic_analysis',

                        'schema_version' =>
                            2,

                        'available' =>
                            true,

                        'evidence' => [
                            'evidence_sha256' =>
                                'SECRET_EVIDENCE_HASH',
                        ],

                        'analyses' => [
                            [
                                'key' =>
                                    'record_identification',

                                'label' =>
                                    'Identificación de registros',

                                'status' =>
                                    'supported',

                                'status_basis' =>
                                    'INTERNAL_STATUS_BASIS',

                                'required_signal_keys' => [
                                    'identifier',
                                ],

                                'observed_signal_keys' => [
                                    'identifier',
                                ],

                                'supporting_source_ids' => [
                                    991,
                                ],

                                'supporting_source_count' =>
                                    1,

                                'evidence_column_count' =>
                                    1,

                                'coverage_counts' => [
                                    'complete' => 1,
                                ],

                                'observed_type_families' => [
                                    'integer',
                                ],

                                'domain_context_scope' =>
                                    'INTERNAL_DOMAIN_SCOPE',

                                'declared_domain_context' => [
                                    [
                                        'domain' =>
                                            'Clientes',

                                        'group' =>
                                            'operaciones',

                                        'source_ids' => [
                                            991,
                                        ],
                                    ],
                                ],

                                'supporting_evidence' => [
                                    [
                                        'source_id' =>
                                            991,

                                        'display_name' =>
                                            'Clientes',

                                        'source_object_name' =>
                                            'Ctes',

                                        'declared_domains' => [
                                            [
                                                'domain' =>
                                                    'Clientes',

                                                'group' =>
                                                    'operaciones',

                                                'source_ids' => [
                                                    991,
                                                ],
                                            ],
                                        ],

                                        'matched_column_count' =>
                                            1,

                                        'coverage_counts' => [
                                            'complete' => 1,
                                        ],

                                        'columns' => [
                                            [
                                                'sheet_index' =>
                                                    0,

                                                'sheet_name' =>
                                                    'Clientes',

                                                'column_key' =>
                                                    'cliente_id',

                                                'column_index' =>
                                                    0,

                                                'header' =>
                                                    'ClienteId',

                                                'matched_term' =>
                                                    'cliente',

                                                'coverage_status' =>
                                                    'complete',

                                                'observed_type_families' => [
                                                    'integer',
                                                ],

                                                'sample_values' => [
                                                    'SECRET_RAW_VALUE',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection()
        );

        $result =
            (new DataTransformationBiTenantPublishedEvaluationProjection())
                ->project(
                    $evaluation
                );

        self::assertNotNull(
            $result
        );

        self::assertArrayHasKey(
            'diagnostic_analysis',
            $result
        );

        $diagnostic =
            $result[
                'diagnostic_analysis'
            ];

        self::assertTrue(
            $diagnostic['available']
        );

        self::assertCount(
            1,
            $diagnostic['analyses']
        );

        $analysis =
            $diagnostic['analyses'][0];

        self::assertSame(
            [
                'key',
                'label',
                'status',
                'required_signal_keys',
                'observed_signal_keys',
                'supporting_source_count',
                'evidence_column_count',
                'coverage_counts',
                'observed_type_families',
                'declared_domain_context',
                'supporting_evidence',
            ],
            array_keys($analysis)
        );

        self::assertSame(
            'record_identification',
            $analysis['key']
        );

        self::assertSame(
            'supported',
            $analysis['status']
        );

        self::assertArrayNotHasKey(
            'status_basis',
            $analysis
        );

        self::assertArrayNotHasKey(
            'supporting_source_ids',
            $analysis
        );

        self::assertArrayNotHasKey(
            'domain_context_scope',
            $analysis
        );

        self::assertArrayNotHasKey(
            'source_ids',
            $analysis[
                'declared_domain_context'
            ][0]
        );

        $evidence =
            $analysis[
                'supporting_evidence'
            ][0];

        self::assertArrayNotHasKey(
            'source_id',
            $evidence
        );

        self::assertArrayNotHasKey(
            'source_object_name',
            $evidence
        );

        self::assertArrayNotHasKey(
            'source_ids',
            $evidence[
                'declared_domains'
            ][0]
        );

        self::assertArrayNotHasKey(
            'sample_values',
            $evidence['columns'][0]
        );

        $serialized =
            json_encode(
                $diagnostic,
                JSON_THROW_ON_ERROR
            );

        self::assertStringNotContainsString(
            'SECRET_EVIDENCE_HASH',
            $serialized
        );

        self::assertStringNotContainsString(
            'SECRET_RAW_VALUE',
            $serialized
        );

        self::assertStringNotContainsString(
            '991',
            $serialized
        );
    }

    public function test_historical_published_evaluation_remains_visible_without_reconstruction(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation::STATUS_PUBLISHED,

                    'published_at' =>
                        '2026-09-28 13:11:29',

                    'diagnostic_analysis_schema_version' =>
                        null,

                    'diagnostic_analysis_snapshot' =>
                        null,
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection()
        );

        $result =
            (new DataTransformationBiTenantPublishedEvaluationProjection())
                ->project(
                    $evaluation
                );

        self::assertNotNull(
            $result
        );

        self::assertSame(
            [
                'available' => false,
                'analyses' => [],
            ],
            $result[
                'diagnostic_analysis'
            ]
        );
    }

    public function test_unknown_future_analysis_schema_fails_closed(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation::STATUS_PUBLISHED,

                    'published_at' =>
                        '2026-09-28 13:11:29',

                    'diagnostic_analysis_schema_version' =>
                        91,

                    'diagnostic_analysis_snapshot' => [
                        'kind' =>
                            'data_bi_diagnostic_analysis',

                        'schema_version' =>
                            91,

                        'available' =>
                            true,

                        'analyses' => [
                            [
                                'key' =>
                                    'FUTURE_DO_NOT_INTERPRET',
                            ],
                        ],
                    ],
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection()
        );

        $result =
            (new DataTransformationBiTenantPublishedEvaluationProjection())
                ->project(
                    $evaluation
                );

        self::assertNotNull(
            $result
        );

        self::assertSame(
            [
                'available' => false,
                'analyses' => [],
            ],
            $result[
                'diagnostic_analysis'
            ]
        );
    }

    public function test_unpublished_result_still_fails_closed(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation::STATUS_READY_FOR_REVIEW,

                    'diagnostic_analysis_schema_version' =>
                        2,

                    'diagnostic_analysis_snapshot' => [
                        'kind' =>
                            'data_bi_diagnostic_analysis',

                        'schema_version' =>
                            2,

                        'available' =>
                            true,

                        'analyses' => [],
                    ],
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection()
        );

        self::assertNull(
            (new DataTransformationBiTenantPublishedEvaluationProjection())
                ->project(
                    $evaluation
                )
        );
    }

    public function test_projection_has_no_live_analysis_recomputation_dependency(): void
    {
        foreach (
            [
                'DataTransformationBiDiagnosticAnalysisReadModel',
                'DataTransformationBiDiagnosticAnalysisWorkspaceProjection',
                'DataTransformationBiSourceAsset::query',
                'DB::',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->projectionSource
            );
        }

        self::assertStringContainsString(
            'diagnostic_analysis_snapshot',
            $this->projectionSource
        );
    }

    public function test_tenant_ui_exposes_published_analysis_with_business_boundaries(): void
    {
        foreach (
            [
                'diagnostic_analysis: TenantPublishedDiagnosticAnalysis',
                'tenantPublishedDiagnosticAnalyses',
                'Análisis BI sustentados por la entrega evaluada',
                'Sustentado por la evidencia evaluada',
                'Sustento parcial en la evidencia evaluada',
                'Evidencia insuficiente en esta entrega',
                'No indican',
                'preparación global para BI',
                'no asignan columnas a dominios',
                'LAUDA no reconstruye resultados históricos',
                'D2D_TENANT_PUBLISHED_DIAGNOSTIC_ANALYSIS_UI',
            ]
            as $required
        ) {
            self::assertStringContainsString(
                $required,
                $this->tenantUi
            );
        }

        foreach (
            [
                'diagnostic_analysis_sha256',
                'evidence_sha256',
                'supporting_source_ids',
                'readiness_score',
                'risk_score',
                'opportunity_score',
            ]
            as $forbidden
        ) {
            self::assertStringNotContainsString(
                $forbidden,
                $this->tenantUi
            );
        }
    }

    public function test_tenant_published_analysis_ui_does_not_render_source_object_name(): void
    {
        $startMarker =
            '<!-- D2D_TENANT_PUBLISHED_DIAGNOSTIC_ANALYSIS_UI -->';

        $endMarker =
            '<!-- D2D_TENANT_PUBLISHED_DIAGNOSTIC_ANALYSIS_UI_END -->';

        $start =
            strpos(
                $this->tenantUi,
                $startMarker
            );

        $end =
            strpos(
                $this->tenantUi,
                $endMarker
            );

        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertGreaterThan(
            $start,
            $end
        );

        $block =
            substr(
                $this->tenantUi,
                $start,
                $end - $start
            );

        self::assertStringNotContainsString(
            'source_object_name',
            $block
        );
    }

}
