<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BusinessIdentityRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Revisa los datos de la solicitud.', 'errors' => $validator->errors()], 422));
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'complete', 'preview' => ['proof_token' => ['required', 'string', 'max:16384']],
            'ready' => ['ready_token' => ['required', 'string', 'max:16384']],
            'revoke' => ['whole_company' => ['required', 'boolean']],
            default => ['link_company' => ['required', 'boolean'], 'restart' => ['sometimes', 'boolean']],
        };
    }
}
