<?php

namespace App\Services\Diagnosis;

class TransformationServiceCapabilityCatalog
{
    public static function all(): array
    {
        return [
            'digital_presence' => [
                'title' =>
                    'Presencia Digital',
                'category' =>
                    'presence',
                'linked_initiative_keys' =>
                    ['PRS-01'],
                'service_key' =>
                    'digital_presence',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'catalog_and_pricing_validation_required',
                'purpose' =>
                    'Establecer una presencia digital operativa, consistente y medible que facilite descubrimiento, confianza, contacto y conversión.',
                'excludes' => [
                    'Community management recurrente.',
                    'Creación recurrente de contenido.',
                    'Diseño recurrente de piezas.',
                    'Gestión de campañas y compra de medios.',
                ],
            ],

            'social' => [
                'title' =>
                    'Social',
                'category' =>
                    'commercial',
                'linked_initiative_keys' =>
                    ['PRS-01', 'COM-01'],
                'service_key' =>
                    'social',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'service_catalog_and_pricing_validation_required',
                'purpose' =>
                    'Capturar y gestionar interacciones sociales, contenido, inbox, leads y analítica, conectando la captación con el CRM y el seguimiento comercial.',
            ],

            'crm' => [
                'title' =>
                    'CRM',
                'category' =>
                    'commercial',
                'linked_initiative_keys' =>
                    ['COM-01'],
                'service_key' =>
                    'erp_crm',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'catalog_and_pricing_validation_required',
                'purpose' =>
                    'Centralizar clientes, contactos, leads, oportunidades, actividades, seguimiento y pipeline comercial.',
            ],

            'ecommerce_b2c' => [
                'title' =>
                    'Ecommerce B2C',
                'category' =>
                    'presence',
                'linked_initiative_keys' =>
                    ['PRS-01', 'BUS-01'],
                'service_key' =>
                    'laudaone_b2c',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'catalog_and_pricing_validation_required',
                'purpose' =>
                    'Habilitar canal digital B2C con catálogo y experiencia de compra.',
            ],

            'ecommerce_b2b' => [
                'title' =>
                    'Ecommerce B2B',
                'category' =>
                    'presence',
                'linked_initiative_keys' =>
                    ['PRS-01', 'BUS-01'],
                'service_key' =>
                    'laudaone_b2b',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'catalog_and_pricing_validation_required',
                'purpose' =>
                    'Habilitar canal digital B2B con condiciones comerciales y experiencia para clientes empresariales.',
            ],

            'electronic_billing' => [
                'title' =>
                    'Facturación Electrónica',
                'category' =>
                    'fiscal',
                'linked_initiative_keys' =>
                    ['TEC-01', 'GOV-01', 'BUS-01'],
                'service_key' =>
                    'api_facturacion_electronica',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'catalog_currency_and_pricing_validation_required',
                'purpose' =>
                    'Habilitar emisión, validación e integración de comprobantes electrónicos.',
            ],

            'fiscal_compliance' => [
                'title' =>
                    'Cumplimiento Fiscal',
                'category' =>
                    'fiscal',
                'linked_initiative_keys' =>
                    ['GOV-01', 'BUS-01'],
                'service_key' =>
                    'cumplimiento_fiscal',
                'subscription_candidate' =>
                    true,
                'recommended' =>
                    false,
                'requires_lauda_review' =>
                    true,
                'commercial_readiness' =>
                    'catalog_and_pricing_validation_required',
                'purpose' =>
                    'Dar seguimiento y control a obligaciones y riesgos de cumplimiento fiscal.',
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

    public static function recurringCandidates(): array
    {
        return array_filter(
            self::all(),
            fn (array $item): bool =>
                (bool) ($item['subscription_candidate'] ?? false)
        );
    }
}
