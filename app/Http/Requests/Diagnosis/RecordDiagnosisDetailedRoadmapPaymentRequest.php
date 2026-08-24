<?php

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordDiagnosisDetailedRoadmapPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'method' => [
                'required',
                Rule::in([
                    'bank_transfer',
                    'cash',
                    'check',
                    'other',
                ]),
            ],
            'reference' => [
                Rule::requiredIf(
                    fn (): bool => in_array(
                        $this->input('method'),
                        ['bank_transfer', 'check'],
                        true
                    )
                ),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
