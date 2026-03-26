<?php

namespace App\Http\Requests\LaudaErp\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'crm_customer_id' => ['nullable', 'integer', Rule::exists('crm_customers', 'id')],
            'crm_contact_id' => ['nullable', 'integer', Rule::exists('crm_contacts', 'id')],
            'crm_lead_id' => ['nullable', 'integer', Rule::exists('crm_leads', 'id')],
            'crm_opportunity_id' => ['nullable', 'integer', Rule::exists('crm_opportunities', 'id')],

            'type' => ['required', Rule::in(['task', 'call', 'meeting', 'visit', 'email', 'note'])],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],

            'status' => ['required', Rule::in(['pending', 'completed', 'cancelled'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],

            'scheduled_at' => ['nullable', 'date'],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? trim((string) $this->title) : null,
        ]);
    }
}
