<?php

namespace App\Services\Diagnosis;

class TransformationProfessionalCapabilityCatalog
{
    public static function all(): array
    {
        return [
            'branding_identity' => [
                'capability_key' =>
                    'branding_identity',

                'title' =>
                    'Branding e Identidad Digital',

                'kind' =>
                    'professional_service',

                'category' =>
                    'branding',

                'service_key' =>
                    null,

                'subscription_candidate' =>
                    false,

                'requires_lauda_review' =>
                    true,

                'commercial_readiness' =>
                    'implementation_plan_estimate_required',

                'purpose' =>
                    'Alinear posicionamiento, identidad y aplicación digital de la marca cuando la situación del cliente limite la experiencia, comunicación o adopción de nuevos canales.',

                'includes' => [
                    'Diagnóstico de marca y consistencia.',
                    'Posicionamiento y propuesta de valor.',
                    'Mensajes principales y personalidad.',
                    'Refresh o rediseño de identidad cuando aplique.',
                    'Paleta, tipografía y lineamientos de uso.',
                    'Brand Kit Digital.',
                    'Aplicación a web, ecommerce, redes y documentos.',
                ],

                'commercial_model' => [
                    'type' =>
                        'implementation_project',

                    'currency' =>
                        'DOP',

                    'pricing_source' =>
                        'transformation_implementation_phase_estimate',

                    'duration_source' =>
                        'transformation_implementation_phase_estimate',

                    'billing_source' =>
                        'transformation_implementation_milestones',

                    'recurring' =>
                        false,
                ],

                'execution' => [
                    'tracked' =>
                        true,

                    'go_live_supported' =>
                        true,

                    'creates_subscription' =>
                        false,

                    'creates_subscription_item' =>
                        false,
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return array_key_exists(
            trim($key),
            self::all()
        );
    }

    public static function isProfessional(string $key): bool
    {
        $definition = self::get(trim($key));

        return $definition !== null
            && ($definition['kind'] ?? null)
                === 'professional_service';
    }
}
