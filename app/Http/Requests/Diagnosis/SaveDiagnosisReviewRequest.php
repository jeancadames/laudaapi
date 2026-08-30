<?php

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;

class SaveDiagnosisReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'review_summary' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'review_priorities' => [
                'nullable',
                'array',
                'max:5',
            ],
            'review_priorities.*' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $summary = $this->input('review_summary');
        $priorities = $this->input('review_priorities');

        $this->merge([
            'review_summary' => is_string($summary)
                ? trim($summary)
                : $summary,
            'review_priorities' => is_array($priorities)
                ? array_values(array_filter(
                    array_map(
                        fn ($item) => is_string($item)
                            ? trim($item)
                            : $item,
                        $priorities
                    ),
                    fn ($item) => is_string($item)
                        ? $item !== ''
                        : $item !== null
                ))
                : [],
        ]);
    }
}
