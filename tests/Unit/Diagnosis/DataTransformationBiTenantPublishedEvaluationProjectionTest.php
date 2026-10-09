<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluation;
use App\Models\DataTransformationBiEvaluationFinding;
use App\Models\DataTransformationBiSourceAsset;
use App\Services\Diagnosis\DataTransformationBiTenantPublishedEvaluationProjection;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class DataTransformationBiTenantPublishedEvaluationProjectionTest
    extends TestCase
{
    public function test_unpublished_evaluation_is_never_projected(): void
    {
        $evaluation =
            (new DataTransformationBiEvaluation())
                ->forceFill([
                    'status' =>
                        DataTransformationBiEvaluation::STATUS_DRAFT,
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

    public function test_published_projection_contains_only_tenant_safe_result(): void
    {
        $source =
            (new DataTransformationBiSourceAsset())
                ->forceFill([
                    'id' => 91,
                    'display_name' => 'Clientes',
                    'source_object_name' => 'dbo.Ctes',
                    'company_id' => 19,
                ]);

        $finding =
            (new DataTransformationBiEvaluationFinding())
                ->forceFill([
                    'id' => 77,
                    'finding_type' => 'weakness',
                    'title' => 'Tipos mixtos',
                    'details' => 'Se observaron tipos mixtos.',
                    'recommendation' => 'Revisar consistencia.',
                    'priority' => 'medium',
                    'evidence_version' => 4,
                    'created_by_user_id' => 1,
                    'updated_by_user_id' => 1,
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
                    'company_id' => 19,
                    'transformation_implementation_request_id' => 2,
                    'status' =>
                        DataTransformationBiEvaluation::STATUS_PUBLISHED,
                    'evidence_version' => 4,
                    'evidence_sha256' => str_repeat('a', 64),
                    'submission_manifest_sha256' => str_repeat('b', 64),
                    'evidence_snapshot' => [
                        'private' => true,
                    ],
                    'created_by_user_id' => 1,
                    'ready_for_review_by_user_id' => 1,
                    'published_by_user_id' => 1,
                    'published_at' => '2026-09-28 13:11:29',
                ]);

        $evaluation->setRelation(
            'findings',
            new Collection([
                $finding,
            ])
        );

        $result =
            (new DataTransformationBiTenantPublishedEvaluationProjection())
                ->project(
                    $evaluation
                );

        self::assertNotNull($result);

        self::assertSame(
            [
                'published_at',
                'summary',
                'diagnostic_analysis',
                'executive_summary',
                'findings',
            ],
            array_keys($result)
        );

        self::assertSame(
            [
                'available' => false,
                'source_count' => null,
                'declared_domain_count' => null,
                'analysis_count' => null,
                'supported_count' => null,
                'partial_count' => null,
                'insufficient_evidence_count' => null,
            ],
            $result['executive_summary']
        );

        self::assertSame(
            [
                'weakness_count' => 1,
                'opportunity_count' => 0,
                'observation_count' => 0,
            ],
            $result['summary']
        );

        self::assertCount(
            1,
            $result['findings']
        );

        self::assertSame(
            [
                'finding_type',
                'title',
                'details',
                'recommendation',
                'priority',
                'sources',
            ],
            array_keys(
                $result['findings'][0]
            )
        );

        self::assertSame(
            ['Clientes'],
            $result['findings'][0]['sources']
        );

        foreach (
            [
                'id',
                'status',
                'company_id',
                'transformation_implementation_request_id',
                'data_transformation_bi_intake_session_id',
                'evidence_version',
                'evidence_current',
                'evidence_sha256',
                'evidence_snapshot',
                'submission_manifest_sha256',
                'created_by_user_id',
                'updated_by_user_id',
                'ready_for_review_by_user_id',
                'published_by_user_id',
                'source_object_name',
                'actions',
            ]
            as $forbidden
        ) {
            self::assertArrayNotHasKey(
                $forbidden,
                $result
            );

            self::assertArrayNotHasKey(
                $forbidden,
                $result['findings'][0]
            );
        }
    }

    public function test_executive_summary_uses_only_valid_frozen_v3_analyses(): void
    {
        $evaluation = (new DataTransformationBiEvaluation())->forceFill([
            'status' => DataTransformationBiEvaluation::STATUS_PUBLISHED,
            'published_at' => '2026-10-06 21:18:08',
            'diagnostic_analysis_schema_version' => 3,
            'diagnostic_analysis_snapshot' => [
                'kind' => 'data_bi_diagnostic_analysis',
                'schema_version' => 3,
                'available' => true,
                'source_count' => 4,
                'semantic_diagnostic' => [
                    'classification' => [
                        'unique_domain_count' => 3,
                    ],
                ],
                'evidence' => [
                    'evidence_sha256' => str_repeat('a', 64),
                ],
                'analyses' => [
                    [
                        'key' => 'customers',
                        'label' => 'Clientes',
                        'status' => 'supported',
                    ],
                    [
                        'key' => 'products',
                        'label' => 'Productos',
                        'status' => 'partial',
                    ],
                    [
                        'key' => 'sales',
                        'label' => 'Ventas',
                        'status' => 'not_supported_by_current_evidence',
                    ],
                    [
                        'key' => '',
                        'label' => 'Análisis inválido',
                        'status' => 'supported',
                    ],
                ],
            ],
        ]);

        $evaluation->setRelation('findings', new Collection());

        $result = (new DataTransformationBiTenantPublishedEvaluationProjection())
            ->project($evaluation);

        self::assertNotNull($result);

        self::assertSame([
            'available' => true,
            'source_count' => 4,
            'declared_domain_count' => 3,
            'analysis_count' => 3,
            'supported_count' => 1,
            'partial_count' => 1,
            'insufficient_evidence_count' => 1,
        ], $result['executive_summary']);

        self::assertCount(3, $result['diagnostic_analysis']['analyses']);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString(str_repeat('a', 64), $encoded);
        self::assertStringNotContainsString('evidence_sha256', $encoded);
        self::assertStringNotContainsString('semantic_diagnostic', $encoded);
        self::assertStringNotContainsString('source_object_name', $encoded);
    }

    public function test_executive_summary_fails_closed_for_invalid_schema(): void
    {
        $evaluation = (new DataTransformationBiEvaluation())->forceFill([
            'status' => DataTransformationBiEvaluation::STATUS_PUBLISHED,
            'diagnostic_analysis_schema_version' => 99,
            'diagnostic_analysis_snapshot' => [
                'kind' => 'data_bi_diagnostic_analysis',
                'schema_version' => 99,
                'available' => true,
                'source_count' => 100,
                'analyses' => [],
            ],
        ]);

        $evaluation->setRelation('findings', new Collection());

        $result = (new DataTransformationBiTenantPublishedEvaluationProjection())
            ->project($evaluation);

        self::assertNotNull($result);
        self::assertFalse($result['executive_summary']['available']);
        self::assertNull($result['executive_summary']['source_count']);
        self::assertNull($result['executive_summary']['analysis_count']);
    }
}
