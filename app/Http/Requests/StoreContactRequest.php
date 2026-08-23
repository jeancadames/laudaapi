<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    private const CURRENT_DIAGNOSIS_REQUEST_TYPE = 'digital_diagnosis_access_request';
    private const LEGACY_DIAGNOSIS_REQUEST_TYPE = 'digital_transformation_diagnosis';

    private const CURRENT_DIAGNOSIS_TOPIC = 'Solicitud de acceso al Diagnóstico LAUDA 360';
    private const LEGACY_DIAGNOSIS_TOPIC = 'Solicitud de Diagnóstico Digital 360';

    private const COMPANY_SIZES = [
        '1 a 10 personas',
        '11 a 50 personas',
        '51 a 200 personas',
        'Más de 200 personas',
    ];

    private const MAIN_CHALLENGES = [
        'No sé por dónde comenzar',
        'Organizar procesos y reducir trabajo manual',
        'Mejorar captación, clientes y ventas',
        'Digitalizar la operación diaria',
        'Integrar administración, fiscalidad y cumplimiento',
        'Centralizar datos, indicadores y BI',
        'Conectar sistemas que hoy trabajan separados',
    ];

    private const ASSISTANCE_LEVELS = [
        'Quiero que LAUDA me recomiende la modalidad',
        'LAUDA 360 Guiado',
        'LAUDA 360 Asistido',
        'LAUDA 360 Gestionado',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isDiagnosis = $this->isDiagnosisRequest();
        $isCurrentDiagnosis = $this->input('metadata.request_type') === self::CURRENT_DIAGNOSIS_REQUEST_TYPE;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:254'],

            'phone' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                'min:7',
                'max:50',
                'regex:/^[0-9+()\s.\-]+$/u',
            ],

            'company' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                'min:2',
                'max:255',
            ],

            'topic' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                'max:255',
                $isDiagnosis
                    ? Rule::in([
                        self::CURRENT_DIAGNOSIS_TOPIC,
                        self::LEGACY_DIAGNOSIS_TOPIC,
                    ])
                    : null,
            ],

            'message' => ['nullable', 'string', 'max:10000'],
            'terms' => ['accepted'],

            'metadata' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'array',
            ],

            'metadata.source' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                'max:100',
                $isDiagnosis ? Rule::in(['laudaapi.com']) : null,
            ],

            'metadata.request_type' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                'max:100',
                $isDiagnosis
                    ? Rule::in([
                        self::CURRENT_DIAGNOSIS_REQUEST_TYPE,
                        self::LEGACY_DIAGNOSIS_REQUEST_TYPE,
                    ])
                    : null,
            ],

            'metadata.solution_interest' => ['nullable', 'string', 'max:100'],
            'metadata.rnc' => ['nullable', 'string', 'max:50'],

            'metadata.intake_type' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                'max:100',
                $isDiagnosis ? Rule::in(['digital_transformation_360']) : null,
            ],

            'metadata.company_size' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                Rule::in(self::COMPANY_SIZES),
            ],

            'metadata.main_challenge' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                Rule::in(self::MAIN_CHALLENGES),
            ],

            'metadata.assistance_level' => [
                Rule::requiredIf($isDiagnosis),
                'nullable',
                'string',
                Rule::in(self::ASSISTANCE_LEVELS),
            ],

            'metadata.diagnosis_access' => [
                Rule::requiredIf($isCurrentDiagnosis),
                'nullable',
                'string',
                'max:100',
                Rule::in(['private_invitation']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',

            'email.required' => 'El correo electrónico es requerido.',
            'email.email' => 'Debes ingresar un correo válido.',

            'phone.required' => 'El teléfono es requerido para solicitar el Diagnóstico LAUDA 360.',
            'phone.regex' => 'Debes ingresar un teléfono válido.',

            'company.required' => 'La empresa es requerida para solicitar el Diagnóstico LAUDA 360.',

            'metadata.required' => 'Falta la información del Diagnóstico LAUDA 360.',
            'metadata.source.required' => 'Falta el origen de la solicitud.',
            'metadata.source.in' => 'El origen de la solicitud no es válido.',
            'metadata.request_type.required' => 'Falta el tipo de solicitud.',
            'metadata.request_type.in' => 'El tipo de solicitud no es válido.',
            'metadata.intake_type.required' => 'Falta el tipo de proceso LAUDA 360.',
            'metadata.intake_type.in' => 'El tipo de proceso LAUDA 360 no es válido.',

            'metadata.company_size.required' => 'Debes indicar el tamaño aproximado de la empresa.',
            'metadata.company_size.in' => 'El tamaño de empresa seleccionado no es válido.',

            'metadata.main_challenge.required' => 'Debes indicar el principal reto de transformación.',
            'metadata.main_challenge.in' => 'El reto seleccionado no es válido.',

            'metadata.assistance_level.required' => 'Debes indicar tu preferencia de acompañamiento.',
            'metadata.assistance_level.in' => 'La modalidad seleccionada no es válida.',

            'metadata.diagnosis_access.required' => 'Falta el tipo de acceso al diagnóstico.',
            'metadata.diagnosis_access.in' => 'El tipo de acceso al diagnóstico no es válido.',

            'topic.required' => 'Falta el motivo de la solicitud.',
            'topic.in' => 'El motivo de la solicitud no corresponde al Diagnóstico LAUDA 360.',

            'terms.accepted' => 'Debes aceptar los términos y condiciones.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        if (is_array($metadata)) {
            foreach ([
                'source',
                'request_type',
                'solution_interest',
                'rnc',
                'intake_type',
                'company_size',
                'main_challenge',
                'assistance_level',
                'diagnosis_access',
            ] as $key) {
                if (isset($metadata[$key]) && is_string($metadata[$key])) {
                    $metadata[$key] = trim($metadata[$key]);
                }
            }
        }

        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'email' => $this->filled('email')
                ? mb_strtolower(trim((string) $this->input('email')))
                : null,
            'phone' => $this->filled('phone') ? trim((string) $this->input('phone')) : null,
            'company' => $this->filled('company') ? trim((string) $this->input('company')) : null,
            'topic' => $this->filled('topic') ? trim((string) $this->input('topic')) : null,
            'message' => $this->filled('message') ? trim((string) $this->input('message')) : null,
            'metadata' => $metadata,
        ]);
    }

    public function isDiagnosisRequest(): bool
    {
        return in_array(
            $this->input('metadata.request_type'),
            [
                self::CURRENT_DIAGNOSIS_REQUEST_TYPE,
                self::LEGACY_DIAGNOSIS_REQUEST_TYPE,
            ],
            true
        )
            || in_array(
                $this->input('topic'),
                [
                    self::CURRENT_DIAGNOSIS_TOPIC,
                    self::LEGACY_DIAGNOSIS_TOPIC,
                ],
                true
            )
            || $this->input('metadata.intake_type') === 'digital_transformation_360';
    }
}
