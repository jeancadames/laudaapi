<?php

namespace App\Http\Requests\LaudaErp\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'crm_customer_id' => ['nullable', 'integer', Rule::exists('crm_customers', 'id')],
            'crm_lead_id' => ['nullable', 'integer', Rule::exists('crm_leads', 'id')],

            'title' => ['required', 'string', 'max:180'],

            'stage' => ['required', Rule::in(['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'])],
            'status' => ['required', Rule::in(['open', 'won', 'lost', 'cancelled'])],

            'amount' => ['nullable', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],

            'expected_close_date' => ['nullable', 'date'],

            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],

            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'loss_reason' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->title) : null,
        ]);
    }
}
