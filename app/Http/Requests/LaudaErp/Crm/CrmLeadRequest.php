<?php

namespace App\Http\Requests\LaudaErp\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['company', 'individual'])],

            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:200'],

            'document_type' => ['nullable', Rule::in(['rnc', 'cedula', 'passport', 'other'])],
            'document_number' => ['nullable', 'string', 'max:50'],

            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],

            'source' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['new', 'qualified', 'unqualified', 'converted', 'lost'])],

            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],

            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->name) : null,
            'business_name' => $this->filled('business_name') ? trim((string) $this->business_name) : null,
            'document_number' => $this->filled('document_number') ? trim((string) $this->document_number) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->email)) : null,
        ]);
    }
}
