<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class DiagnosisAssessmentCompletenessService
{
    public function __construct(
        private readonly DiagnosisBusinessProfileService $profiles
    ) {
    }

    /**
     * Validate the minimum business context required for a diagnosis
     * to become an official LAUDA 360 result.
     *
     * Methodology-answer completeness is intentionally handled
     * separately from this profile gate.
     */
    public function profileErrors(DiagnosisAssessment $assessment): array
    {
        $profile = $this->profiles->normalize(
            $this->profiles->extract(
                $assessment->only([
                    'business_activity_type',
                    'business_sector',
                    'business_sector_other',
                    'customer_market',
                    'sales_channels',
                    'sales_channel_other',
                    'logistics_operation_types',
                    'logistics_operation_other',
                    'business_activity_description',
                ])
            )
        );

        $validator = Validator::make(
            $profile,
            $this->profiles->rules($profile)
        );

        return $validator->errors()->toArray();
    }

    public function hasCompleteProfile(
        DiagnosisAssessment $assessment
    ): bool {
        return $this->profileErrors($assessment) === [];
    }

    public function requiredQuestionIds(
        ?string $methodologyVersion = null
    ): array {
        $configuredVersion = (string) config(
            'lauda360_diagnosis.version',
            ''
        );

        $methodologyVersion = trim(
            (string) ($methodologyVersion ?: $configuredVersion)
        );

        if (
            $methodologyVersion === ''
            || $configuredVersion === ''
            || $methodologyVersion !== $configuredVersion
        ) {
            throw ValidationException::withMessages([
                'methodology_version' => [
                    'La versión de metodología del diagnóstico no está disponible para validación.',
                ],
            ]);
        }

        $dimensions = config(
            'lauda360_diagnosis.dimensions',
            []
        );

        $questionIds = [];

        foreach ($dimensions as $dimension) {
            foreach (
                (array) ($dimension['questions'] ?? [])
                as $questionId
            ) {
                $questionIds[] = (string) $questionId;
            }
        }

        foreach (
            (array) config(
                'lauda360_diagnosis.capacity_questions',
                []
            ) as $questionId
        ) {
            $questionIds[] = (string) $questionId;
        }

        foreach (
            (array) config(
                'lauda360_diagnosis.urgency_questions',
                []
            ) as $questionId
        ) {
            $questionIds[] = (string) $questionId;
        }

        return array_values(
            array_unique(
                array_filter(
                    $questionIds,
                    fn (string $questionId): bool =>
                        trim($questionId) !== ''
                )
            )
        );
    }

    public function missingAnswerIds(
        DiagnosisAssessment $assessment
    ): array {
        $required = $this->requiredQuestionIds(
            $assessment->methodology_version
        );

        $answers = is_array($assessment->answers)
            ? $assessment->answers
            : [];

        return array_values(
            array_filter(
                $required,
                static function (string $questionId) use (
                    $answers
                ): bool {
                    if (
                        ! array_key_exists(
                            $questionId,
                            $answers
                        )
                    ) {
                        return true;
                    }

                    $value = $answers[$questionId];

                    return $value === null
                        || $value === '';
                }
            )
        );
    }

    public function hasCompleteAnswers(
        DiagnosisAssessment $assessment
    ): bool {
        return $this->missingAnswerIds($assessment) === [];
    }

    public function assertAnswersComplete(
        DiagnosisAssessment $assessment
    ): void {
        $missing = $this->missingAnswerIds($assessment);

        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'answers' => [
                'El diagnóstico tiene preguntas obligatorias sin responder.',
            ],
            'missing_answers' => $missing,
        ]);
    }

    public function missingInformation(
        DiagnosisAssessment $assessment
    ): array {
        $profileErrors = $this->profileErrors($assessment);
        $missingAnswers = $this->missingAnswerIds($assessment);

        $profileMessages = [];

        foreach ($profileErrors as $field => $messages) {
            $profileMessages[] = [
                'field' => (string) $field,
                'messages' => array_values(
                    array_unique(
                        array_map(
                            'strval',
                            (array) $messages
                        )
                    )
                ),
            ];
        }

        return [
            'business_profile' => $profileMessages,
            'missing_answers' => $missingAnswers,
            'profile_complete' => $profileMessages === [],
            'answers_complete' => $missingAnswers === [],
            'complete' =>
                $profileMessages === []
                && $missingAnswers === [],
        ];
    }

    public function assertComplete(
        DiagnosisAssessment $assessment
    ): void {
        $this->assertProfileComplete($assessment);
        $this->assertAnswersComplete($assessment);
    }

    public function assertProfileComplete(
        DiagnosisAssessment $assessment
    ): void {
        $errors = $this->profileErrors($assessment);

        if ($errors === []) {
            return;
        }

        throw ValidationException::withMessages([
            'assessment' => [
                'El diagnóstico no puede publicarse porque falta información vital del perfil empresarial.',
            ],
            'business_profile' => array_values(array_unique(
                array_merge(...array_values($errors))
            )),
        ]);
    }
}
