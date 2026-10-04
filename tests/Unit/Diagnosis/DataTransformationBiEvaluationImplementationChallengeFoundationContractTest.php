<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DataTransformationBiEvaluationImplementationChallenge;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

final class DataTransformationBiEvaluationImplementationChallengeFoundationContractTest
    extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_challenge_is_a_separate_human_professional_model(): void
    {
        $challenge =
            new DataTransformationBiEvaluationImplementationChallenge();

        $this->assertSame(
            'data_transformation_bi_evaluation_implementation_challenges',
            $challenge->getTable()
        );

        $this->assertSame(
            [
                'high',
                'medium',
                'low',
            ],
            DataTransformationBiEvaluationImplementationChallenge
                ::PRIORITIES
        );

        $this->assertSame(
            [
                'data_transformation_bi_evaluation_id',
                'company_id',
                'title',
                'details',
                'recommended_response',
                'priority',
                'evidence_version',
                'sort_order',
                'created_by_user_id',
                'updated_by_user_id',
            ],
            $challenge->getFillable()
        );
    }

    public function test_challenge_has_evaluation_and_finding_traceability(): void
    {
        $challenge =
            new DataTransformationBiEvaluationImplementationChallenge();

        $this->assertInstanceOf(
            BelongsTo::class,
            $challenge->evaluation()
        );

        $this->assertInstanceOf(
            BelongsToMany::class,
            $challenge->findings()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $challenge->createdBy()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $challenge->updatedBy()
        );
    }

    public function test_persistence_is_separate_from_diagnostic_findings(): void
    {
        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_10_03_231500_create_data_transformation_bi_evaluation_implementation_challenges.php'
        );

        foreach (
            [
                'data_transformation_bi_evaluation_implementation_challenges',
                'data_transformation_bi_impl_challenge_findings',
                "'evidence_version'",
                "'recommended_response'",
                "'sort_order'",
                'data_transformation_bi_evaluation_finding_id',
            ]
            as $required
        ) {
            $this->assertStringContainsString(
                $required,
                $migration
            );
        }

        /*
         * Existing finding persistence remains a different table.
         */
        $this->assertStringContainsString(
            'data_transformation_bi_evaluation_findings',
            $migration
        );
    }

    public function test_foundation_does_not_create_a_fourth_finding_type(): void
    {
        $findingModel = file_get_contents(
            $this->root()
            .'/app/Models/'
            .'DataTransformationBiEvaluationFinding.php'
        );

        $challengeModel = file_get_contents(
            $this->root()
            .'/app/Models/'
            .'DataTransformationBiEvaluationImplementationChallenge.php'
        );

        $this->assertStringNotContainsString(
            'implementation_challenge',
            $findingModel
        );

        foreach (
            [
                'TYPE_WEAKNESS',
                'TYPE_OPPORTUNITY',
                'TYPE_OBSERVATION',
                'public const TYPES',
            ]
            as $findingTypeToken
        ) {
            $this->assertStringNotContainsString(
                $findingTypeToken,
                $challengeModel
            );
        }
    }

    public function test_foundation_has_no_automatic_or_commercial_authority(): void
    {
        $model = file_get_contents(
            $this->root()
            .'/app/Models/'
            .'DataTransformationBiEvaluationImplementationChallenge.php'
        );

        $migration = file_get_contents(
            $this->root()
            .'/database/migrations/'
            .'2026_10_03_231500_create_data_transformation_bi_evaluation_implementation_challenges.php'
        );

        foreach (
            [
                "'automatic_score'",
                "'readiness_score'",
                "'risk_score'",
                "'confidence_score'",
                "'roadmap_id'",
                "'implementation_plan_id'",
                "'invoice_id'",
                "'subscription_id'",
                "'payment_id'",
                "'source_snapshot'",
                "'profiling_snapshot'",
                "'raw_value'",
            ]
            as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $model
            );

            $this->assertStringNotContainsString(
                $forbidden,
                $migration
            );
        }
    }
}
