<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'topic'   => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'terms'   => 'accepted',

            // Metadata para solicitudes centralizadas desde laudaapi.com
            'metadata' => 'nullable|array',
            'metadata.source' => 'nullable|string|max:100',
            'metadata.request_type' => 'nullable|string|max:100',
            'metadata.solution_interest' => 'nullable|string|max:100',
            'metadata.rnc' => 'nullable|string|max:50',
            'metadata.intake_type' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'El nombre es requerido.',
            'email.required' => 'El correo electrónico es requerido.',
            'email.email'    => 'Debes ingresar un correo válido.',
            'terms.accepted' => 'Debes aceptar los términos y condiciones.',
        ];
    }
}
