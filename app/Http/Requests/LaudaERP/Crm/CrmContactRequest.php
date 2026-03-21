<?php

namespace App\Http\Requests\LaudaErp\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'crm_customer_id' => ['required', 'integer', Rule::exists('crm_customers', 'id')],

            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],

            'position' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],

            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],

            'is_primary' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],

            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->filled('first_name') ? trim((string) $this->first_name) : null,
            'last_name' => $this->filled('last_name') ? trim((string) $this->last_name) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->email)) : null,
            'is_primary' => filter_var($this->input('is_primary', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
