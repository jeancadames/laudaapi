<?php

namespace Tests\Unit\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Services\Diagnosis\DiagnosisAssessmentCompletenessService;
use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DiagnosisAssessmentCompletenessServiceTest extends TestCase
{
    private DiagnosisAssessmentCompletenessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DiagnosisAssessmentCompletenessService(
            new DiagnosisBusinessProfileService()
        );
    }

    public function test_empty_profile_is_incomplete(): void
    {
        $assessment = new DiagnosisAssessment([
            'sales_channels' => [],
            'logistics_operation_types' => [],
        ]);

        $errors = $this->service->profileErrors($assessment);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey(
            'business_activity_type',
            $errors
        );
        $this->assertArrayHasKey(
            'business_sector',
            $errors
        );
        $this->assertArrayHasKey(
            'customer_market',
            $errors
        );
        $this->assertArrayHasKey(
            'sales_channels',
            $errors
        );
        $this->assertArrayHasKey(
            'business_activity_description',
            $errors
        );
        $this->assertFalse(
            $this->service->hasCompleteProfile($assessment)
        );
    }

    public function test_valid_profile_is_complete(): void
    {
        $assessment = $this->validAssessment();

        $this->assertSame(
            [],
            $this->service->profileErrors($assessment)
        );

        $this->assertTrue(
            $this->service->hasCompleteProfile($assessment)
        );

        $this->service->assertProfileComplete($assessment);

        $this->addToAssertionCount(1);
    }

    public function test_short_description_is_rejected(): void
    {
        $assessment = $this->validAssessment();
        $assessment->business_activity_description = 'Muy corta';

        $errors = $this->service->profileErrors($assessment);

        $this->assertArrayHasKey(
            'business_activity_description',
            $errors
        );
    }

    public function test_other_sector_requires_detail(): void
    {
        $assessment = $this->validAssessment();
        $assessment->business_sector = 'other';
        $assessment->business_sector_other = null;

        $errors = $this->service->profileErrors($assessment);

        $this->assertArrayHasKey(
            'business_sector_other',
            $errors
        );
    }

    public function test_other_sales_channel_requires_detail(): void
    {
        $assessment = $this->validAssessment();
        $assessment->sales_channels = ['other'];
        $assessment->sales_channel_other = null;

        $errors = $this->service->profileErrors($assessment);

        $this->assertArrayHasKey(
            'sales_channel_other',
            $errors
        );
    }

    public function test_logistics_requires_operation_types(): void
    {
        $assessment = $this->validAssessment();
        $assessment->business_sector = 'logistics';
        $assessment->logistics_operation_types = [];

        $errors = $this->service->profileErrors($assessment);

        $this->assertArrayHasKey(
            'logistics_operation_types',
            $errors
        );
    }

    public function test_assert_throws_validation_exception_for_incomplete_profile(): void
    {
        $assessment = new DiagnosisAssessment([
            'sales_channels' => [],
            'logistics_operation_types' => [],
        ]);

        try {
            $this->service->assertProfileComplete($assessment);

            $this->fail(
                'Se esperaba ValidationException para perfil incompleto.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('assessment', $errors);
            $this->assertArrayHasKey(
                'business_profile',
                $errors
            );
        }
    }

    public function test_required_questions_come_from_methodology_config(): void
    {
        $expected = [];

        foreach (
            config('lauda360_diagnosis.dimensions', [])
            as $dimension
        ) {
            $expected = array_merge(
                $expected,
                $dimension['questions'] ?? []
            );
        }

        $expected = array_merge(
            $expected,
            config(
                'lauda360_diagnosis.capacity_questions',
                []
            ),
            config(
                'lauda360_diagnosis.urgency_questions',
                []
            )
        );

        $expected = array_values(
            array_unique($expected)
        );

        $actual = $this->service->requiredQuestionIds(
            (string) config(
                'lauda360_diagnosis.version'
            )
        );

        $this->assertSame($expected, $actual);

        /*
         * This protects against accidentally validating only one
         * section of the methodology without hardcoding "51".
         */
        $this->assertNotEmpty($actual);
    }

    public function test_missing_required_answer_is_detected(): void
    {
        $assessment = $this->completeAssessment();

        $required = $this->service->requiredQuestionIds(
            $assessment->methodology_version
        );

        $missingId = $required[0];

        $answers = $assessment->answers;
        unset($answers[$missingId]);

        $assessment->answers = $answers;

        $this->assertContains(
            $missingId,
            $this->service->missingAnswerIds(
                $assessment
            )
        );

        $this->assertFalse(
            $this->service->hasCompleteAnswers(
                $assessment
            )
        );
    }

    public function test_null_required_answer_is_detected(): void
    {
        $assessment = $this->completeAssessment();

        $required = $this->service->requiredQuestionIds(
            $assessment->methodology_version
        );

        $missingId = $required[0];

        $answers = $assessment->answers;
        $answers[$missingId] = null;

        $assessment->answers = $answers;

        $this->assertContains(
            $missingId,
            $this->service->missingAnswerIds(
                $assessment
            )
        );
    }

    public function test_complete_methodology_answers_are_accepted(): void
    {
        $assessment = $this->completeAssessment();

        $this->assertSame(
            [],
            $this->service->missingAnswerIds(
                $assessment
            )
        );

        $this->assertTrue(
            $this->service->hasCompleteAnswers(
                $assessment
            )
        );

        $this->service->assertAnswersComplete(
            $assessment
        );

        $this->addToAssertionCount(1);
    }

    public function test_assert_complete_rejects_missing_answer(): void
    {
        $assessment = $this->completeAssessment();

        $required = $this->service->requiredQuestionIds(
            $assessment->methodology_version
        );

        $missingId = $required[0];

        $answers = $assessment->answers;
        unset($answers[$missingId]);

        $assessment->answers = $answers;

        try {
            $this->service->assertComplete(
                $assessment
            );

            $this->fail(
                'Se esperaba ValidationException por respuesta faltante.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'answers',
                $errors
            );

            $this->assertArrayHasKey(
                'missing_answers',
                $errors
            );

            $this->assertContains(
                $missingId,
                $errors['missing_answers']
            );
        }
    }

    public function test_unknown_methodology_version_is_rejected(): void
    {
        $assessment = $this->completeAssessment();
        $assessment->methodology_version = '999.999';

        $this->expectException(
            ValidationException::class
        );

        $this->service->assertAnswersComplete(
            $assessment
        );
    }

    public function test_missing_information_combines_profile_and_answers(): void
    {
        $assessment = new DiagnosisAssessment();

        $assessment->methodology_version =
            (string) config(
                'lauda360_diagnosis.version'
            );

        $assessment->answers = [];

        $missing =
            $this->service->missingInformation(
                $assessment
            );

        $this->assertFalse(
            $missing['complete']
        );

        $this->assertFalse(
            $missing['profile_complete']
        );

        $this->assertFalse(
            $missing['answers_complete']
        );

        $this->assertNotEmpty(
            $missing['business_profile']
        );

        $this->assertSame(
            $this->service->requiredQuestionIds(
                $assessment->methodology_version
            ),
            $missing['missing_answers']
        );
    }

    public function test_missing_information_is_empty_for_complete_assessment(): void
    {
        $assessment = $this->completeAssessment();

        $missing =
            $this->service->missingInformation(
                $assessment
            );

        $this->assertTrue(
            $missing['complete']
        );

        $this->assertTrue(
            $missing['profile_complete']
        );

        $this->assertTrue(
            $missing['answers_complete']
        );

        $this->assertSame(
            [],
            $missing['business_profile']
        );

        $this->assertSame(
            [],
            $missing['missing_answers']
        );
    }

    private function completeAssessment(): DiagnosisAssessment
    {
        $assessment = $this->validAssessment();

        $assessment->methodology_version =
            (string) config(
                'lauda360_diagnosis.version'
            );

        $answers = [];

        foreach (
            $this->service->requiredQuestionIds(
                $assessment->methodology_version
            ) as $questionId
        ) {
            /*
             * The completeness service validates presence here.
             * Domain/range validation remains owned by the request
             * and scoring layers.
             */
            $answers[$questionId] = 3;
        }

        $assessment->answers = $answers;

        return $assessment;
    }

    private function validAssessment(): DiagnosisAssessment
    {
        $options = config('lauda360_business_profile');

        $activity = array_key_first(
            $options['activity_types'] ?? []
        );

        $sectors = array_keys($options['sectors'] ?? []);
        $sector = collect($sectors)
            ->first(
                fn (string $value): bool =>
                    ! in_array(
                        $value,
                        ['other', 'logistics'],
                        true
                    )
            );

        $market = array_key_first(
            $options['customer_markets'] ?? []
        );

        $channels = array_keys(
            $options['sales_channels'] ?? []
        );

        $channel = collect($channels)
            ->first(
                fn (string $value): bool => $value !== 'other'
            );

        $this->assertNotNull($activity);
        $this->assertNotNull($sector);
        $this->assertNotNull($market);
        $this->assertNotNull($channel);

        return new DiagnosisAssessment([
            'business_activity_type' => $activity,
            'business_sector' => $sector,
            'business_sector_other' => null,
            'customer_market' => $market,
            'sales_channels' => [$channel],
            'sales_channel_other' => null,
            'logistics_operation_types' => [],
            'logistics_operation_other' => null,
            'business_activity_description' =>
                'Empresa dedicada a operaciones comerciales y servicios para sus clientes.',
        ]);
    }
}
