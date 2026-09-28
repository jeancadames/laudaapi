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
                'findings',
            ],
            array_keys($result)
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
}
