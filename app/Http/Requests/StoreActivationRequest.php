<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza input antes de validar.
     * - Corrige mismatch Vue: otherTopic -> other_topic
     * - Trim strings
     * - Lowercase email
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // ✅ map Vue camelCase -> snake_case
        if (isset($data['otherTopic']) && !isset($data['other_topic'])) {
            $data['other_topic'] = $data['otherTopic'];
        }

        // ✅ normalizaciones básicas
        if (isset($data['email'])) {
            $data['email'] = Str::lower(trim((string) $data['email']));
        }

        foreach (['name', 'company', 'role', 'phone', 'topic', 'other_topic', 'system', 'volume', 'message'] as $key) {
            if (array_key_exists($key, $data) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }

        // opcional: si topic != "Otro", limpiar other_topic
        if (!empty($data['topic']) && $data['topic'] !== 'Otro') {
            $data['other_topic'] = null;
        }

        $this->replace($data);
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'company'     => 'required|string|max:255',

            // ✅ alineado con tu validación front (lo haces requerido allá)
            'role'        => 'required|string|max:255',

            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:50',

            'topic'       => 'required|string|max:255',

            // ✅ si topic = "Otro", esto debe venir sí o sí
            'other_topic' => 'nullable|string|max:255|required_if:topic,Otro',

            // ✅ alineado con tu validación front (allá lo haces requerido)
            'system'      => 'required|string|max:255',
            'volume'      => 'required|string|max:255',

            'message'     => 'nullable|string|max:2000',

            // ✅ términos
            'terms'       => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'El nombre es requerido.',
            'company.required'     => 'La empresa es requerida.',
            'role.required'        => 'El cargo o rol es requerido.',

            'email.required'       => 'El correo electrónico es requerido.',
            'email.email'          => 'Debes ingresar un correo válido.',

            'topic.required'       => 'Debes seleccionar un área de interés.',
            'other_topic.required_if' => 'Describe brevemente el tema.',

            'system.required'      => 'Selecciona tu sistema actual.',
            'volume.required'      => 'Selecciona tu volumen mensual.',

            'message.max'          => 'El mensaje es demasiado largo.',
            'terms.accepted'       => 'Debes aceptar los términos y condiciones.',
        ];
    }
}
