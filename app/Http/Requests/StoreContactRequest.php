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
            'terms' => 'accepted',
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
