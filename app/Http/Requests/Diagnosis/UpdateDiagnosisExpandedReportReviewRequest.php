<?php

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiagnosisExpandedReportReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'review_notes' => [
                'nullable',
                'string',
                'max:12000',
            ],
        ];
    }
}
