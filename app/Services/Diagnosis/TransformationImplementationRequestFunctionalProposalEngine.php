<?php

namespace App\Services\Diagnosis;

final class TransformationImplementationRequestFunctionalProposalEngine
{
    /* Claves y entregables verificados en BI-13-M3. Cada relación es PARCIAL. */
    private const RULES = [
        'operaciones_inventarios' => [
            'Información de productos, existencias y movimientos de inventario',
            'Productos, categorías, existencia, movimientos, costos, precios, rotación, margen, disponibilidad e inventario valorizado.',
        ],
        'gestion_clientes' => [
            'Información maestra e histórica de clientes y segmentos',
            'Maestro, calidad, comportamiento histórico, segmentación e industria de clientes.',
        ],
        'gestion_desempeno' => [
            'Historial de ventas por fecha, cliente, producto, categoría, sucursal y vendedor',
            'Historial de ventas por fecha, cliente, producto, categoría, sucursal y vendedor.',
        ],
        'gestion_riesgos' => [
            'Información de clientes y segmentos para evaluar riesgos y oportunidades',
            'Riesgo, oportunidad, concentración y exposición de clientes y segmentos mediante indicadores explicables.',
        ],
    ];

    /**
     * Requisitos PRELIMINARES por necesidad (catálogo transversal de 15 opciones).
     * No afirma que exista una fuente o que los datos estén disponibles.
     */
    private const DIAGNOSTIC_REQUIREMENT_NAMES = [
        'operaciones_procesos' => 'Información por identificar sobre procesos, actividades y servicios',
        'operaciones_recursos' => 'Información por identificar sobre capacidad, recursos y tiempos',
        'operaciones_calidad' => 'Información por identificar sobre calidad, cumplimiento y productividad',
        'operaciones_inventarios' => 'Información por identificar sobre inventarios, movimientos y abastecimiento',
        'operaciones_proyectos' => 'Información por identificar sobre proyectos, trabajos o eventos',
        'gestion_clientes' => 'Información por identificar sobre clientes, usuarios y segmentos',
        'gestion_desempeno' => 'Información por identificar sobre desempeño de actividades comerciales o servicios',
        'gestion_indicadores' => 'Información por identificar sobre indicadores, metas y resultados de gestión',
        'gestion_planificacion' => 'Información por identificar sobre planificación, metas y seguimiento',
        'gestion_riesgos' => 'Información por identificar sobre tendencias, riesgos y oportunidades',
        'finanzas_ingresos' => 'Información por identificar sobre ingresos, costos, gastos y resultados',
        'finanzas_presupuestos' => 'Información por identificar sobre presupuestos y desviaciones',
        'finanzas_cobros' => 'Información por identificar sobre cobros, pagos y movimientos de caja',
        'finanzas_rentabilidad' => 'Información por identificar sobre ingresos, costos y rentabilidad',
        'finanzas_proyecciones' => 'Información por identificar sobre históricos y supuestos para proyecciones financieras',
    ];

    private const DIAGNOSTIC_MATRIX_DELIVERABLE =
        'Matriz de correspondencia entre necesidades declaradas y datos disponibles o pendientes.';

    public static function preview(array $snapshot, array $scope, array $deliverables): array
    {
        $scope = TransformationImplementationRequestBusinessNeedsAssessment::normalize($snapshot, $scope);
        $scope = TransformationImplementationRequestExpectedSourcesPlan::normalize($snapshot, $scope, $deliverables);
        $needs = data_get($snapshot, 'business_needs.improvements', []);
        $needs = is_array($needs) ? $needs : [];
        $plan = $scope['expected_sources_plan'] ?? ['sources' => [], 'links' => []];
        // Se activa solo en definiciones BI creadas con el nuevo diagnóstico gratuito.
        // Definiciones profesionales anteriores conservan sus reglas originales.
        $diagnosticMode = data_get($scope, 'diagnostic_draft.service_phase')
            === 'basic_diagnosis_without_charge';

        $statuses = [];
        foreach (data_get($scope, 'business_needs_assessment.items', []) as $item) {
            $statuses[$item['key']] = $item['status'];
        }
        $linked = [];
        foreach ($plan['links'] as $link) {
            $linked[$link['need_key']] = true;
        }
        $byName = [];
        $usedKeys = [];
        foreach ($plan['sources'] as $source) {
            $byName[mb_strtolower(trim($source['name']))][] = $source['key'];
            $usedKeys[$source['key']] = true;
        }

        $catalog = [];
        foreach ($deliverables as $item) {
            if (!is_array($item)
                || ($item['source'] ?? null) !== ($diagnosticMode ? 'basic_diagnostic_scope' : 'professional_capability')
                || ($item['capability_key'] ?? null) !== 'data_transformation_bi'
                || !is_string($item['deliverable'] ?? null)) {
                continue;
            }
            $description = trim($item['deliverable']);
            $catalog[$description][] = TransformationImplementationRequestExpectedSourcesPlan::deliverableRef($item);
        }

        $suggestions = [];
        $skipped = [];
        foreach ($needs as $need) {
            $key = trim((string) ($need['key'] ?? ''));
            $reason = null;
            if (($statuses[$key] ?? null) === 'excluded') {
                $reason = 'excluded_by_lauda';
            } elseif (isset($linked[$key])) {
                $reason = 'existing_human_link';
            } elseif ($diagnosticMode
                ? !isset(self::DIAGNOSTIC_REQUIREMENT_NAMES[$key])
                : !isset(self::RULES[$key])) {
                $reason = 'no_verified_rule';
            }
            if ($reason !== null) {
                $skipped[] = ['need_key' => $key, 'reason' => $reason];
                continue;
            }

            if ($diagnosticMode) {
                $name = self::DIAGNOSTIC_REQUIREMENT_NAMES[$key];
                $description = self::DIAGNOSTIC_MATRIX_DELIVERABLE;
            } else {
                [$name, $description] = self::RULES[$key];
            }
            $matches = $catalog[$description] ?? [];
            if (count($matches) !== 1) {
                $skipped[] = [
                    'need_key' => $key,
                    'reason' => $matches === [] ? 'deliverable_missing' : 'deliverable_ambiguous',
                ];
                continue;
            }

            $matchesExisting = $byName[mb_strtolower($name)] ?? [];
            if (count($matchesExisting) > 1) {
                $skipped[] = ['need_key' => $key, 'reason' => 'existing_requirement_ambiguous'];
                continue;
            }
            $reuse = $matchesExisting !== [];
            $sourceKey = $reuse
                ? $matchesExisting[0]
                : 'src_bi13_'.substr(hash('sha256', $key.'|'.$name), 0, 20);
            if (!$reuse && isset($usedKeys[$sourceKey])) {
                $skipped[] = ['need_key' => $key, 'reason' => 'generated_key_collision'];
                continue;
            }
            $source = $reuse ? null : [
                'key' => $sourceKey,
                'name' => $name,
                'source_type' => 'to_define',
                'origin_hint' => '',
                'data_domains' => [],
                'notes' => $diagnosticMode
                    ? 'Requisito preliminar para diagnóstico básico. Fuente, origen, disponibilidad, cobertura y autorización aún por confirmar.'
                    : 'Propuesta funcional. Origen, disponibilidad y cobertura pendientes de confirmar con el cliente.',
            ];
            if (!$reuse) {
                $byName[mb_strtolower($name)] = [$sourceKey];
                $usedKeys[$sourceKey] = true;
            }
            $suggestions[] = [
                'need_key' => $key,
                'need_label' => (string) ($need['label'] ?? $key),
                'action' => $reuse ? 'reuse_requirement' : 'create_requirement',
                'source' => $source,
                'link' => [
                    'need_key' => $key,
                    'source_keys' => [$sourceKey],
                    'deliverable_refs' => [$matches[0]],
                    'notes' => $diagnosticMode
                        ? 'Vínculo preliminar entre necesidad y matriz de correspondencia del diagnóstico gratuito; validar con Admin LAUDA.'
                        : 'Relación parcial sugerida mediante regla explícita del catálogo; validar con Admin LAUDA.',
                ],
                'coverage' => 'partial',
                'requires_human_review' => true,
                'source_verified' => false,
            ];
        }
        return [
            'schema_version' => 1,
            'rules_version' => 1,
            'mode' => 'preview_only',
            'suggestions' => $suggestions,
            'skipped' => $skipped,
            'summary' => [
                'total_needs' => count($needs),
                'suggested' => count($suggestions),
                'not_suggested' => count($skipped),
            ],
        ];
    }
}
