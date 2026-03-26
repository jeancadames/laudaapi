<?php

namespace App\Http\Requests\LaudaErp\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->input('company_id', 0);

        return [
            'type' => ['required', Rule::in(['company', 'individual'])],

            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:200'],

            'document_type' => ['nullable', Rule::in(['rnc', 'cedula', 'passport', 'other'])],
            'document_number' => ['nullable', 'string', 'max:50'],

            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],

            'industry' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],

            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],

            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],

            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->email)) : null,
            'country' => $this->filled('country') ? strtoupper(trim((string) $this->country)) : null,
            'name' => $this->filled('name') ? trim((string) $this->name) : null,
            'business_name' => $this->filled('business_name') ? trim((string) $this->business_name) : null,
            'document_number' => $this->filled('document_number') ? trim((string) $this->document_number) : null,
        ]);
    }
}
