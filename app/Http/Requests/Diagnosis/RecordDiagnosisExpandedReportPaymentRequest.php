<?php

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;

class RecordDiagnosisExpandedReportPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:bank_transfer,cash,check,other'],
            'reference' => ['nullable', 'string', 'max:255', 'required_if:method,bank_transfer,check'],
        ];
    }
}
