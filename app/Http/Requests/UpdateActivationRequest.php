<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivationRequest extends FormRequest
{
    public function authorize()
    {
        // autorización fina en controller/policy; aquí permitimos si está autenticado
        return $this->user() !== null;
    }

    public function rules()
    {
        return [
            'name' => ['nullable', 'string', 'max:191'],
            'company' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'topic' => ['nullable', 'string', 'max:191'],
            'message' => ['nullable', 'string'],
            'terms' => ['nullable', 'boolean'],
            'services' => ['required', 'array', 'min:0'],
            'services.*' => [
                'integer',
                Rule::exists('services', 'id')->where(fn($q) => $q->where('active', true)),
            ],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('services') && is_string($this->services)) {
            $decoded = json_decode($this->services, true);
            if (is_array($decoded)) $this->merge(['services' => $decoded]);
        }
    }
}
