<?php

namespace App\Services\Diagnosis;

class TransformationProfessionalCapabilityCatalog
{
    public static function all(): array
    {
        return [
            'procedures_guide' => [
                'capability_key' =>
                    'procedures_guide',

                'title' =>
                    'Guía de Procesos y Procedimientos LAUDA 360',

                'kind' =>
                    'professional_service',

                'category' =>
                    'operations',

                'service_key' =>
                    null,

                'subscription_candidate' =>
                    false,

                'requires_lauda_review' =>
                    false,

                'linked_initiative_keys' => [
                    'STR-01',
                    'PPL-01',
                    'OPS-01',
                    'GOV-01',
                ],

                'commercial_readiness' =>
                    'implementation_plan_estimate_required',

                'purpose' =>
                    'Documentar cómo debe operar la empresa después de la transformación, reduciendo dependencia de conocimiento informal y facilitando adopción, control y continuidad.',

                'includes' => [
                    'Objetivo y alcance del proceso.',
                    'Responsables y participantes.',
                    'Entradas, requisitos y precondiciones.',
                    'Procedimiento paso a paso.',
                    'Sistemas y herramientas utilizadas.',
                    'Controles, autorizaciones y excepciones.',
                    'Evidencias y documentos.',
                    'Indicadores y resultado esperado.',
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
                        false,

                    'creates_subscription' =>
                        false,

                    'creates_subscription_item' =>
                        false,
                ],
            ],

            'data_transformation_bi' => [
                'capability_key' =>
                    'data_transformation_bi',

                'title' =>
                    'Transformación e Inteligencia de Datos para BI',

                'kind' =>
                    'professional_service',

                'category' =>
                    'data',

                /*
                 * No existe todavía como Service comercial.
                 * La futura definición comercial pertenece a
                 * la Etapa de Implementación.
                 */
                'service_key' =>
                    null,

                'subscription_candidate' =>
                    false,

                'requires_lauda_review' =>
                    true,

                'linked_initiative_keys' => [
                    'DAT-01',
                ],

                'commercial_readiness' =>
                    'implementation_plan_estimate_required',

                /*
                 * Contrato S13:
                 * esta capability NO puede utilizar el flujo
                 * gratuito de activación profesional.
                 */
                'activation_policy' =>
                    'implementation_only',

                'purpose' =>
                    'Preparar, organizar, transformar y estructurar los datos de la empresa para convertirlos en una base analítica confiable y reutilizable por BI y por las capacidades operativas e inteligentes de LAUDA.',

                'includes' => [
                    'Maestro, calidad, comportamiento histórico, segmentación e industria de clientes.',
                    'Riesgo, oportunidad, concentración y exposición de clientes y segmentos mediante indicadores explicables.',
                    'Productos, categorías, existencia, movimientos, costos, precios, rotación, margen, disponibilidad e inventario valorizado.',
                    'Relación producto ↔ materia prima y exposición a variaciones de costos y precios de mercado.',
                    'Confiabilidad, riesgo, oportunidad, dependencia y exposición de suplidores.',
                    'Historial de ventas por fecha, cliente, producto, categoría, sucursal y vendedor.',
                    'Perfilado, limpieza, normalización, relaciones y preparación del modelo analítico.',
                    'Identificación de fuentes, calidad, complejidad, alertas y oportunidades de inteligencia futura.',
                    'Preparación de datos reutilizables para BI, CRM, pricing, inventario, compras, CxC, planificación y alertas.',
                ],

                'excludes' => [
                    'Ejecución gratuita de ETL o pipelines de datos.',
                    'Transformación masiva o limpieza productiva de datos durante el ciclo gratuito LAUDA 360.',
                    'Construcción gratuita de warehouse, data mart, lakehouse o equivalente.',
                    'Construcción gratuita de dashboards o reportes BI productivos.',
                    'Cambio automático de precios por variaciones de materias primas.',
                    'Creación automática de precio, factura, pago, suscripción o contratación.',
                ],
            ],

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

                'linked_initiative_keys' => [
                    'STR-01',
                    'PRS-01',
                ],

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
