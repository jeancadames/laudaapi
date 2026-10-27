<?php

namespace App\Services\Diagnosis;

/**
 * D2F-T1: genera un BORRADOR editable de requerimientos de información.
 *
 * Entrada: snapshot de necesidades enviado por Admin Tenant.
 * Salida: categorías sugeridas, deduplicadas y trazables a sus objetivos.
 * No consulta datos, no escribe, no confirma disponibilidad, no envía solicitudes.
 */
final class DataBiInformationRequirementDraftGenerator
{
    /** @var array<string, array{label:string, guidance:string}> */
    private const INFORMATION_CATEGORIES = [
        'procesos_actividades' => [
            'label' => 'Procesos y actividades',
            'guidance' => 'Registros o listados sobre actividades, servicios y procesos de la empresa.',
        ],
        'proyectos_trabajos' => [
            'label' => 'Proyectos, trabajos y eventos',
            'guidance' => 'Información de proyectos o trabajos, con sus etapas y seguimiento, cuando aplique.',
        ],
        'recursos_capacidad' => [
            'label' => 'Recursos y capacidad',
            'guidance' => 'Información sobre recursos, asignaciones y capacidad de operación.',
        ],
        'tiempos_operativos' => [
            'label' => 'Tiempos de ejecución',
            'guidance' => 'Registros sobre duración, fechas, turnos o tiempos de atención, si existen.',
        ],
        'calidad_cumplimiento' => [
            'label' => 'Calidad y cumplimiento',
            'guidance' => 'Información de inspecciones, incidencias, cumplimiento o evaluaciones de calidad.',
        ],
        'inventarios_existencias' => [
            'label' => 'Inventarios y existencias',
            'guidance' => 'Información de productos, materiales, servicios catalogados o existencias, cuando aplique.',
        ],
        'inventarios_movimientos' => [
            'label' => 'Movimientos de inventario',
            'guidance' => 'Registros de entradas, salidas, ajustes o consumos, cuando aplique.',
        ],
        'compras_suplidores' => [
            'label' => 'Compras y suplidores',
            'guidance' => 'Información de suplidores, órdenes, compras, abastecimiento o entregas.',
        ],
        'clientes_usuarios' => [
            'label' => 'Clientes y usuarios',
            'guidance' => 'Información de clientes, usuarios, segmentos o relaciones comerciales.',
        ],
        'ventas_servicios' => [
            'label' => 'Ventas y servicios prestados',
            'guidance' => 'Historial de ventas, servicios, operaciones comerciales o facturación.',
        ],
        'metas_indicadores' => [
            'label' => 'Metas e indicadores',
            'guidance' => 'Registros de metas, mediciones, indicadores y seguimiento de resultados.',
        ],
        'planificacion_actividades' => [
            'label' => 'Planificación y seguimiento',
            'guidance' => 'Planes, tareas, compromisos, cronogramas o avances de actividades.',
        ],
        'riesgos_incidencias' => [
            'label' => 'Riesgos e incidencias',
            'guidance' => 'Registros de incidencias, alertas, riesgos o situaciones relevantes.',
        ],
        'resultados_historicos' => [
            'label' => 'Resultados e históricos',
            'guidance' => 'Resultados históricos, reportes internos o registros para observar tendencias.',
        ],
        'ingresos_costos_gastos' => [
            'label' => 'Ingresos, costos y gastos',
            'guidance' => 'Información contable o administrativa de ingresos, costos, gastos y resultados.',
        ],
        'presupuestos' => [
            'label' => 'Presupuestos',
            'guidance' => 'Presupuestos, asignaciones, partidas o comparaciones presupuestarias.',
        ],
        'cobros_pagos' => [
            'label' => 'Cobros y pagos',
            'guidance' => 'Registros de cobros, pagos, fechas e importes.',
        ],
        'cuentas_por_cobrar_pagar' => [
            'label' => 'Cuentas por cobrar y pagar',
            'guidance' => 'Saldos, vencimientos y obligaciones pendientes.',
        ],
        'caja_bancos' => [
            'label' => 'Caja y bancos',
            'guidance' => 'Movimientos de caja, conciliaciones o saldos bancarios, cuando aplique.',
        ],
    ];

    /** @var array<string, list<string>> */
    private const NEED_REQUIREMENTS = [
        'operaciones_procesos' => ['procesos_actividades', 'tiempos_operativos'],
        'operaciones_proyectos' => ['proyectos_trabajos', 'planificacion_actividades'],
        'operaciones_recursos' => ['recursos_capacidad', 'tiempos_operativos'],
        'operaciones_calidad' => ['calidad_cumplimiento', 'procesos_actividades'],
        'operaciones_inventarios' => ['inventarios_existencias', 'inventarios_movimientos', 'compras_suplidores'],
        'gestion_clientes' => ['clientes_usuarios', 'ventas_servicios'],
        'gestion_desempeno' => ['ventas_servicios', 'procesos_actividades', 'metas_indicadores'],
        'gestion_indicadores' => ['metas_indicadores', 'resultados_historicos'],
        'gestion_planificacion' => ['planificacion_actividades', 'metas_indicadores'],
        'gestion_riesgos' => ['riesgos_incidencias', 'resultados_historicos'],
        'finanzas_ingresos' => ['ingresos_costos_gastos', 'ventas_servicios', 'resultados_historicos'],
        'finanzas_presupuestos' => ['presupuestos', 'ingresos_costos_gastos'],
        'finanzas_cobros' => ['cobros_pagos', 'cuentas_por_cobrar_pagar', 'caja_bancos'],
        'finanzas_rentabilidad' => ['ingresos_costos_gastos', 'ventas_servicios'],
        'finanzas_proyecciones' => ['presupuestos', 'ingresos_costos_gastos', 'resultados_historicos'],
    ];

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public static function preview(array $snapshot): array
    {
        $improvements = $snapshot['business_needs']['improvements'] ?? null;
        $improvements = is_array($improvements) ? $improvements : [];
        $selected = [];
        foreach ($improvements as $item) {
            $key = is_array($item) ? ($item['key'] ?? null) : $item;
            if (is_string($key) && $key !== '') {
                $selected[$key] = true;
            }
        }

        $catalog = DataBiBusinessNeedsCatalog::improvementMap();
        $unknownKeys = [];
        foreach (array_keys($selected) as $key) {
            if (!isset($catalog[$key]) || !isset(self::NEED_REQUIREMENTS[$key])) {
                $unknownKeys[] = $key;
            }
        }
        sort($unknownKeys, SORT_STRING);

        $requirements = [];
        $selectedNeeds = [];
        // Orden canónico y estable, independientemente del orden de entrada.
        foreach ($catalog as $key => $need) {
            if (!isset($selected[$key]) || !isset(self::NEED_REQUIREMENTS[$key])) {
                continue;
            }
            $selectedNeeds[] = [
                'key' => $key,
                'label' => $need['label'],
                'group' => $need['group'],
                'group_label' => $need['group_label'],
            ];
            foreach (self::NEED_REQUIREMENTS[$key] as $categoryKey) {
                if (!isset(self::INFORMATION_CATEGORIES[$categoryKey])) {
                    throw new \LogicException('Categoría de requerimiento no definida: '.$categoryKey);
                }
                if (!isset($requirements[$categoryKey])) {
                    $category = self::INFORMATION_CATEGORIES[$categoryKey];
                    $requirements[$categoryKey] = [
                        'key' => $categoryKey,
                        'label' => $category['label'],
                        'guidance' => $category['guidance'],
                        'objective_keys' => [],
                        'objective_labels' => [],
                        'business_groups' => [],
                        'availability' => null,
                        'declared_sources' => [],
                        'structure_required' => false,
                    ];
                }
                $requirements[$categoryKey]['objective_keys'][] = $key;
                $requirements[$categoryKey]['objective_labels'][] = $need['label'];
                if (!in_array($need['group'], $requirements[$categoryKey]['business_groups'], true)) {
                    $requirements[$categoryKey]['business_groups'][] = $need['group'];
                }
            }
        }

        return [
            'schema_version' => 1,
            'mode' => 'preview_only',
            'purpose' => 'information_source_declaration',
            'selected_needs' => $selectedNeeds,
            'selected_needs_count' => count($selectedNeeds),
            'requirements' => array_values($requirements),
            'requirements_count' => count($requirements),
            'unmapped_need_keys' => $unknownKeys,
            'requires_lauda_review' => true,
            'can_send_without_review' => false,
            'tenant_response_received' => false,
            'data_inspected' => false,
        ];
    }
}
