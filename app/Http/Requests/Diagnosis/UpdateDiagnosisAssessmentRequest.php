<?php

namespace App\Http\Requests\Diagnosis;

use App\Services\Diagnosis\DiagnosisBusinessProfileService;
use App\Services\Diagnosis\Lauda360ScoringService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDiagnosisAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            app(DiagnosisBusinessProfileService::class)
                ->normalize($this->all())
        );
    }

    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*' => ['required', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
            'current_step' => [
                'required',
                'integer',
                'between:1,' . config(
                    'lauda360_diagnosis.steps',
                    11
                ),
            ],
            ...app(DiagnosisBusinessProfileService::class)
                ->rules($this->all()),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $validIds = app(
                    Lauda360ScoringService::class
                )->allQuestionIds();

                $answerIds = array_keys(
                    $this->input('answers', [])
                );

                $noteIds = array_keys(
                    $this->input('notes', [])
                );

                $unexpectedAnswers = array_diff(
                    $answerIds,
                    $validIds
                );

                $unexpectedNotes = array_diff(
                    $noteIds,
                    $validIds
                );

                if ($unexpectedAnswers !== []) {
                    $validator->errors()->add(
                        'answers',
                        'Se recibieron preguntas que no pertenecen a la versión vigente del diagnóstico.'
                    );
                }

                if ($unexpectedNotes !== []) {
                    $validator->errors()->add(
                        'notes',
                        'Se recibieron notas para preguntas no válidas.'
                    );
                }
            },
        ];
    }
}
