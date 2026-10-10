<?php

namespace App\Services\Diagnosis;

/** Catálogo de mejoras empresariales para solicitudes BI. No es un catálogo de fuentes. */
final class DataBiBusinessNeedsCatalog
{
    public static function groups(): array
    {
        return [
            [
                'key' => 'operaciones',
                'title' => 'Operaciones',
                'purpose' => 'Ejecución, eficiencia y control de las actividades.',
                'improvements' => [
                    ['key' => 'operaciones_procesos', 'label' => 'Procesos y prestación de servicios'],
                    ['key' => 'operaciones_proyectos', 'label' => 'Proyectos, trabajos y eventos'],
                    ['key' => 'operaciones_recursos', 'label' => 'Capacidad, recursos y tiempos'],
                    ['key' => 'operaciones_calidad', 'label' => 'Calidad, cumplimiento y productividad'],
                    ['key' => 'operaciones_inventarios', 'label' => 'Inventarios y abastecimiento, cuando aplique'],
                ],
            ],
            [
                'key' => 'gestion',
                'title' => 'Gestión',
                'purpose' => 'Planificación, desempeño y toma de decisiones.',
                'improvements' => [
                    ['key' => 'gestion_clientes', 'label' => 'Clientes, usuarios y segmentos'],
                    ['key' => 'gestion_desempeno', 'label' => 'Desempeño comercial y de servicios'],
                    ['key' => 'gestion_indicadores', 'label' => 'Metas e indicadores de gestión'],
                    ['key' => 'gestion_planificacion', 'label' => 'Planificación y seguimiento de actividades'],
                    ['key' => 'gestion_riesgos', 'label' => 'Tendencias, riesgos y oportunidades'],
                ],
            ],
            [
                'key' => 'finanzas',
                'title' => 'Finanzas',
                'purpose' => 'Visibilidad, seguimiento y control financiero.',
                'improvements' => [
                    ['key' => 'finanzas_ingresos', 'label' => 'Ingresos, costos, gastos y resultados'],
                    ['key' => 'finanzas_presupuestos', 'label' => 'Presupuestos y desviaciones'],
                    ['key' => 'finanzas_cobros', 'label' => 'Cobros, pagos y flujo de caja'],
                    ['key' => 'finanzas_rentabilidad', 'label' => 'Rentabilidad por actividad o proyecto'],
                    ['key' => 'finanzas_proyecciones', 'label' => 'Proyecciones y exposición financiera'],
                ],
            ],
        ];
    }

    public static function improvementMap(): array
    {
        $map = [];
        foreach (self::groups() as $group) {
            foreach ($group['improvements'] as $improvement) {
                $map[$improvement['key']] = [
                    'key' => $improvement['key'],
                    'label' => $improvement['label'],
                    'group' => $group['key'],
                    'group_label' => $group['title'],
                ];
            }
        }
        return $map;
    }
}
