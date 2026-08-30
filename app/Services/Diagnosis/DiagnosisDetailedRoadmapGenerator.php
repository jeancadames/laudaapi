<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;
use App\Models\DiagnosisExpandedReport;

class DiagnosisDetailedRoadmapGenerator
{
    private const DIMENSIONS = [
        'strategy' => ['label' => 'Estrategia y Liderazgo', 'code' => 'STR'],
        'people' => ['label' => 'Personas y Cultura Digital', 'code' => 'PPL'],
        'presence' => ['label' => 'Presencia y Experiencia Digital', 'code' => 'PRS'],
        'commercial' => ['label' => 'Gestión Comercial y Clientes', 'code' => 'COM'],
        'operations' => ['label' => 'Procesos y Operación', 'code' => 'OPS'],
        'technology' => ['label' => 'Tecnología e Integración', 'code' => 'TEC'],
        'data' => ['label' => 'Datos e Inteligencia', 'code' => 'DAT'],
        'governance' => ['label' => 'Gobierno, Seguridad y Control', 'code' => 'GOV'],
    ];

    public function generate(
        DiagnosisAssessment $assessment,
        ?DiagnosisExpandedReport $report = null
    ): array {
        $dimensions = $this->dimensions($assessment->dimension_scores ?? []);
        $initiatives = [];

        foreach ($dimensions as $dimension) {
            $initiatives[] = $this->dimensionInitiative(
                $assessment,
                $dimension
            );
        }

        $business = $this->businessInitiative($assessment);

        if ($business !== null) {
            $initiatives[] = $business;
        }

        $initiatives[] = $this->continuousImprovement($assessment);
        $initiatives = $this->sequence($initiatives);

        return [
            'source_snapshot' => [
                'assessment' => [
                    'id' => $assessment->id,
                    'organization_name' => $assessment->organization_name,
                    'methodology_version' => $assessment->methodology_version,
                    'published_at' => $assessment->published_at?->toISOString(),
                    'maturity_score' => $assessment->maturity_score,
                    'capacity_score' => $assessment->capacity_score,
                    'urgency_score' => $assessment->urgency_score,
                    'dimension_scores' => $assessment->dimension_scores ?? [],
                    'review_summary' => $assessment->review_summary,
                    'review_priorities' => $assessment->review_priorities ?? [],
                    'business_profile' => [
                        'business_activity_type' => $assessment->business_activity_type,
                        'business_sector' => $assessment->business_sector,
                        'customer_market' => $assessment->customer_market,
                        'sales_channels' => $assessment->sales_channels ?? [],
                        'logistics_operation_types' =>
                            $assessment->logistics_operation_types ?? [],
                        'business_activity_description' =>
                            $assessment->business_activity_description,
                    ],
                ],
                'expanded_report' => $report ? [
                    'id' => $report->id,
                    'version' => $report->version,
                    'status' => $report->status,
                    'published_at' => $report->published_at?->toISOString(),
                    'sections' => $report->sections ?? [],
                ] : null,
            ],
            'roadmap' => [
                'executive_direction' => [
                    'title' => 'Dirección ejecutiva de transformación',
                    'starting_point' => data_get(
                        $report?->sections ?? [],
                        'executive_summary.body',
                        $assessment->review_summary
                    ),
                    'objective' => $report
                        ? 'Convertir los hallazgos del Diagnóstico y del Informe Ampliado en una secuencia ejecutable de iniciativas, responsables, dependencias e indicadores.'
                        : 'Convertir los hallazgos del Diagnóstico oficial en una secuencia ejecutable de iniciativas, responsables, dependencias e indicadores.',
                ],
                'planning_principles' => [
                    'Priorizar impacto de negocio antes que herramientas.',
                    'Estabilizar y estandarizar antes de automatizar.',
                    'Asignar responsable e indicador a cada iniciativa.',
                    'Resolver dependencias antes de ampliar alcance.',
                    'Medir adopción y resultado, no solo instalación.',
                ],
                'horizons' => [
                    ['code' => 'H1', 'label' => '0-30 días'],
                    ['code' => 'H2', 'label' => '31-90 días'],
                    ['code' => 'H3', 'label' => '91-180 días'],
                    ['code' => 'H4', 'label' => '181-365 días'],
                ],
                'transformation_capabilities' =>
                    $this->transformationCapabilities(
                        $assessment
                    ),
                'service_capabilities' =>
                    TransformationServiceCapabilityCatalog::all(),
                'phases' => $this->phases($initiatives),
                'initiatives' => $initiatives,
                'governance' => [
                    'weekly' => 'Seguimiento operativo de iniciativas activas.',
                    'monthly' =>
                        'Comité de transformación: avance, riesgos, decisiones y dependencias.',
                    'quarterly' =>
                        'Repriorización del roadmap y medición de beneficios.',
                    'required_roles' => [
                        'Patrocinador ejecutivo',
                        'Líder de transformación',
                        'Responsable funcional por iniciativa',
                        'Responsable tecnológico cuando aplique',
                        'Responsable de datos/KPI',
                    ],
                ],
                'success_framework' => [
                    'business_outcomes' => [
                        'crecimiento o protección de ingresos',
                        'mejora de experiencia del cliente',
                        'reducción de tiempos y reprocesos',
                        'mayor control y trazabilidad',
                        'decisiones basadas en datos',
                    ],
                    'execution_controls' => [
                        '% iniciativas en fecha',
                        '% iniciativas con responsable',
                        '% dependencias resueltas antes de ejecución',
                        '% iniciativas con KPI y línea base',
                    ],
                    'baseline' => [
                        'maturity_score' => $assessment->maturity_score,
                        'capacity_score' => $assessment->capacity_score,
                        'urgency_score' => $assessment->urgency_score,
                    ],
                ],
                'scope_note' => [
                    'title' => 'Alcance del Roadmap Detallado',
                    'body' =>
                        'El Roadmap define qué transformar, en qué secuencia, con qué responsables, dependencias, esfuerzo e indicadores. También identifica capacidades de Transformación Detallada como la Guía de Procesos y Procedimientos y, cuando corresponda, Branding e Identidad Digital. La ejecución técnica, elaboración e implantación de procedimientos, branding, parametrización, desarrollo, integración y gestión del cambio se cotizan y planifican por separado.',
                ],
            ],
        ];
    }

    public function generateFromAssessment(
        DiagnosisAssessment $assessment
    ): array {
        return $this->generate($assessment, null);
    }

    private function transformationCapabilities(
        DiagnosisAssessment $assessment
    ): array {
        $description = mb_strtolower(
            trim(
                (string)
                    $assessment
                        ->business_activity_description
            )
        );

        $brandingSignals = [
            'branding',
            'marca',
            'identidad',
            'imagen corporativa',
            'rebranding',
        ];

        $brandingRecommended = false;

        foreach ($brandingSignals as $signal) {
            if (
                $description !== ''
                && str_contains(
                    $description,
                    $signal
                )
            ) {
                $brandingRecommended = true;

                break;
            }
        }

        return [
            'title' =>
                'Capacidades de Transformación Detallada',

            'procedures_guide' => [
                'title' =>
                    'Guía de Procesos y Procedimientos LAUDA 360',
                'type' =>
                    'structural',
                'recommended' =>
                    true,
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
                'lifecycle' => [
                    'Borrador',
                    'Revisión',
                    'Aprobado',
                    'Vigente',
                    'Sustituido',
                ],
                'commercial_note' =>
                    'El Roadmap identifica y estructura esta capacidad. La elaboración detallada, validación e implantación de las guías se define y cotiza en la fase de ejecución de la transformación.',
            ],

            'branding_identity' => [
                'title' =>
                    'Branding e Identidad Digital',
                'type' =>
                    'optional',
                'recommended' =>
                    $brandingRecommended,
                'requires_lauda_review' =>
                    true,
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
                'recommendation_basis' =>
                    $brandingRecommended
                    ? 'El cliente declaró una necesidad relacionada con marca o identidad en su contexto de negocio. LAUDA debe validarla antes de incluir ejecución.'
                    : 'Capacidad opcional. Se activa cuando la revisión LAUDA confirma que la identidad o el posicionamiento limitan la transformación.',
                'commercial_note' =>
                    'Branding no modifica el scoring del diagnóstico. Su ejecución se cotiza como parte de la transformación cuando corresponda.',
            ],

            'score_note' =>
                'Las capacidades de procedimientos y branding son contexto de ejecución y no modifican la puntuación del Diagnóstico LAUDA 360.',
        ];
    }

    private function dimensions(array $scores): array
    {
        $result = [];

        foreach (self::DIMENSIONS as $key => $definition) {
            $value = $scores[$key] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'label' => $definition['label'],
                'code' => $definition['code'],
                'score' => round((float) $value, 1),
            ];
        }

        usort(
            $result,
            fn (array $a, array $b): int =>
                $a['score'] <=> $b['score']
        );

        return $result;
    }

    private function dimensionInitiative(
        DiagnosisAssessment $assessment,
        array $dimension
    ): array {
        $key = $dimension['key'];
        $score = (float) $dimension['score'];
        $priority = $this->priority($score);

        $definitions = [
            'strategy' => [
                'title' => 'Formalizar gobierno y agenda de transformación',
                'objective' =>
                    'Convertir la transformación en una agenda priorizada, medible y patrocinada por dirección.',
                'owner' => 'Dirección / líder de transformación',
                'effort' => 'medium',
                'dependencies' => [],
                'actions' => [
                    'Definir objetivos de transformación vinculados al negocio.',
                    'Asignar patrocinador ejecutivo y responsables.',
                    'Priorizar portafolio por impacto, esfuerzo, riesgo y dependencia.',
                    'Establecer revisión ejecutiva mensual.',
                ],
                'metrics' => [
                    '% iniciativas con responsable',
                    '% hitos cumplidos en fecha',
                ],
            ],
            'people' => [
                'title' => 'Desarrollar capacidad interna y adopción',
                'objective' =>
                    'Asegurar roles, capacidades y disciplina para sostener los cambios.',
                'owner' => 'Dirección + responsables funcionales',
                'effort' => 'medium',
                'dependencies' => ['STR-01'],
                'actions' => [
                    'Definir usuarios clave y responsables.',
                    'Identificar brechas de capacidad.',
                    'Preparar plan de adopción por iniciativa.',
                    'Medir uso real y cumplimiento.',
                ],
                'metrics' => [
                    '% usuarios clave capacitados',
                    '% adopción',
                ],
            ],
            'presence' => [
                'title' => 'Ordenar experiencia y canales digitales',
                'objective' =>
                    'Construir una experiencia coherente entre presencia, captación, servicio y conversión.',
                'owner' => 'Comercial / Marketing / Servicio',
                'effort' => 'medium',
                'dependencies' => ['STR-01'],
                'actions' => [
                    'Inventariar puntos de contacto digitales.',
                    'Definir recorrido y estándares de respuesta.',
                    'Alinear captación y datos de contacto.',
                    'Medir conversión y respuesta por canal.',
                ],
                'metrics' => [
                    'tasa de conversión digital',
                    'tiempo de respuesta',
                ],
            ],
            'commercial' => [
                'title' => 'Digitalizar el ciclo comercial y de clientes',
                'objective' =>
                    'Dar trazabilidad a oportunidades, cotizaciones, ventas, seguimiento y postventa.',
                'owner' => 'Dirección Comercial',
                'effort' => 'medium',
                'dependencies' => ['STR-01', 'PPL-01'],
                'actions' => [
                    'Definir etapas y reglas del ciclo comercial.',
                    'Centralizar datos y actividad del cliente.',
                    'Estandarizar seguimiento y próximos pasos.',
                    'Medir conversión y duración del ciclo.',
                ],
                'metrics' => [
                    'tasa de conversión',
                    'duración del ciclo comercial',
                ],
            ],
            'operations' => [
                'title' => 'Estandarizar y digitalizar procesos críticos',
                'objective' =>
                    'Reducir reprocesos, errores y tareas informales antes de automatizar.',
                'owner' => 'Operaciones',
                'effort' => 'high',
                'dependencies' => ['STR-01', 'PPL-01'],
                'actions' => [
                    'Mapear procesos de punta a punta.',
                    'Definir estados, responsables y excepciones.',
                    'Eliminar duplicidad y doble digitación.',
                    'Digitalizar evidencias y controles.',
                ],
                'metrics' => [
                    'tiempo de ciclo',
                    'reprocesos',
                    '% trazabilidad digital',
                ],
            ],
            'technology' => [
                'title' => 'Definir arquitectura e integración prioritaria',
                'objective' =>
                    'Reducir silos tecnológicos y conectar procesos sobre fuentes maestras.',
                'owner' => 'Tecnología / Dirección',
                'effort' => 'high',
                'dependencies' => ['OPS-01', 'COM-01'],
                'actions' => [
                    'Identificar sistemas maestros.',
                    'Definir integraciones prioritarias.',
                    'Reducir intercambios manuales.',
                    'Documentar continuidad de componentes críticos.',
                ],
                'metrics' => [
                    '# integraciones críticas',
                    '% procesos sin doble digitación',
                ],
            ],
            'data' => [
                'title' => 'Construir datos confiables e inteligencia de gestión',
                'objective' =>
                    'Convertir la operación digital en indicadores recurrentes para decisión.',
                'owner' => 'Dirección + responsables de datos',
                'effort' => 'medium',
                'dependencies' => ['OPS-01', 'TEC-01'],
                'actions' => [
                    'Definir diccionario mínimo de datos.',
                    'Seleccionar KPIs por frente.',
                    'Construir tableros sobre fuentes confiables.',
                    'Establecer cadencia de revisión.',
                ],
                'metrics' => [
                    '% KPIs con fuente definida',
                    '% KPIs actualizados',
                ],
            ],
            'governance' => [
                'title' => 'Fortalecer seguridad, continuidad y control',
                'objective' =>
                    'Reducir exposición operativa y establecer gobierno digital mínimo.',
                'owner' => 'Dirección / Tecnología / Administración',
                'effort' => 'medium',
                'dependencies' => ['STR-01'],
                'actions' => [
                    'Formalizar accesos y roles.',
                    'Validar respaldos y recuperación.',
                    'Definir controles sobre datos críticos.',
                    'Mantener registro de riesgos.',
                ],
                'metrics' => [
                    '% accesos revisados',
                    '% respaldos verificados',
                    '# riesgos críticos abiertos',
                ],
            ],
        ];

        $d = $definitions[$key];

        return [
            'id' => $dimension['code'] . '-01',
            'dimension' => $key,
            'dimension_label' => $dimension['label'],
            'source_score' => $score,
            'priority' => $priority,
            'title' => $d['title'],
            'objective' => $d['objective'],
            'actions' => $d['actions'],
            'owner_role' => $d['owner'],
            'dependencies' => $d['dependencies'],
            'impact' => $this->impact(
                $score,
                (float) ($assessment->urgency_score ?? 0)
            ),
            'effort' => $d['effort'],
            'success_metrics' => $d['metrics'],
            'phase' => $this->phaseFor($key, $priority),
            'horizon' => $this->horizonFor($key, $priority),
            'rationale' => sprintf(
                '%s obtuvo %s/100 en el diagnóstico.',
                $dimension['label'],
                $this->number($score)
            ),
        ];
    }

    private function businessInitiative(
        DiagnosisAssessment $assessment
    ): ?array {
        if ($assessment->business_sector === 'logistics') {
            return [
                'id' => 'BUS-01',
                'dimension' => 'business',
                'dimension_label' => 'Flujo logístico',
                'source_score' => null,
                'priority' => 'high',
                'title' => 'Conectar el flujo logístico de punta a punta',
                'objective' =>
                    'Unificar trazabilidad física y documental desde recepción hasta facturación/cobranza.',
                'actions' => [
                    'Conectar recepción, almacenamiento e inventario.',
                    'Trazar preparación/picking, empaque y despacho.',
                    'Vincular transporte, entrega/POD e incidencias.',
                    'Incluir devoluciones y logística inversa.',
                    'Conectar estados con facturación y cobranza.',
                    'Definir SLAs y KPIs.',
                ],
                'owner_role' => 'Operaciones / Logística',
                'dependencies' => ['OPS-01', 'TEC-01'],
                'impact' => 'high',
                'effort' => 'high',
                'success_metrics' => [
                    '% trazabilidad punta a punta',
                    'tiempo de ciclo recepción-entrega',
                    'cumplimiento SLA',
                ],
                'phase' => 3,
                'horizon' => '91-180 días',
                'rationale' =>
                    'El perfil comercial identifica una operación logística.',
            ];
        }

        if ($assessment->business_sector === 'transportation') {
            return [
                'id' => 'BUS-01',
                'dimension' => 'business',
                'dimension_label' => 'Operación de transporte',
                'source_score' => null,
                'priority' => 'high',
                'title' => 'Digitalizar la operación de transporte',
                'objective' =>
                    'Conectar planificación, asignación, ejecución, seguimiento, evidencia y liquidación.',
                'actions' => [
                    'Estandarizar planificación y asignación.',
                    'Trazar ejecución e incidencias.',
                    'Digitalizar evidencia de entrega.',
                    'Conectar liquidación y facturación.',
                ],
                'owner_role' => 'Operaciones / Transporte',
                'dependencies' => ['OPS-01', 'TEC-01'],
                'impact' => 'high',
                'effort' => 'high',
                'success_metrics' => [
                    '% viajes trazados',
                    'cumplimiento de entrega',
                ],
                'phase' => 3,
                'horizon' => '91-180 días',
                'rationale' =>
                    'El perfil comercial identifica transporte como sector principal.',
            ];
        }

        $activity = $assessment->business_activity_type;

        $map = [
            'goods' => [
                'title' => 'Conectar el ciclo comercial y operativo de bienes',
                'objective' =>
                    'Alinear catálogo, precios, inventario, pedido, despacho, facturación y cobranza.',
                'actions' => [
                    'Alinear catálogo y precios.',
                    'Conectar disponibilidad e inventario con ventas.',
                    'Trazar pedido, preparación y despacho.',
                    'Relacionar cumplimiento con facturación y cobranza.',
                ],
                'metrics' => [
                    'exactitud de inventario',
                    'fill rate',
                    'margen',
                ],
            ],
            'services' => [
                'title' => 'Conectar el ciclo completo de servicios',
                'objective' =>
                    'Dar trazabilidad desde captación hasta ejecución, facturación y renovación.',
                'actions' => [
                    'Estandarizar captación y cotización.',
                    'Conectar agenda/proyecto y recursos.',
                    'Trazar ejecución e incidencias.',
                    'Relacionar ejecución con facturación y cobranza.',
                ],
                'metrics' => [
                    'tasa de conversión',
                    'utilización de capacidad',
                    'renovación',
                ],
            ],
            'mixed' => [
                'title' => 'Integrar bienes y servicios en un solo flujo',
                'objective' =>
                    'Evitar procesos y datos separados cuando forman parte de una misma relación comercial.',
                'actions' => [
                    'Definir ciclo común de cliente y venta.',
                    'Relacionar inventario/despacho con ejecución.',
                    'Unificar facturación y cobranza.',
                    'Medir rentabilidad por componente y cliente.',
                ],
                'metrics' => [
                    '% operaciones integradas',
                    'margen por cliente',
                ],
            ],
        ];

        if (! isset($map[$activity])) {
            return null;
        }

        $d = $map[$activity];

        return [
            'id' => 'BUS-01',
            'dimension' => 'business',
            'dimension_label' => 'Modelo de negocio',
            'source_score' => null,
            'priority' => 'high',
            'title' => $d['title'],
            'objective' => $d['objective'],
            'actions' => $d['actions'],
            'owner_role' => 'Comercial / Operaciones',
            'dependencies' => ['COM-01', 'OPS-01', 'TEC-01'],
            'impact' => 'high',
            'effort' => 'high',
            'success_metrics' => $d['metrics'],
            'phase' => 3,
            'horizon' => '91-180 días',
            'rationale' =>
                'La iniciativa contextualiza el Roadmap al modelo comercial declarado.',
        ];
    }

    private function continuousImprovement(
        DiagnosisAssessment $assessment
    ): array {
        return [
            'id' => 'IMP-01',
            'dimension' => 'continuous_improvement',
            'dimension_label' => 'Mejora continua',
            'source_score' => null,
            'priority' => 'medium',
            'title' => 'Institucionalizar mejora continua e inteligencia',
            'objective' =>
                'Mantener el roadmap vivo y escalar automatización e inteligencia sobre procesos estables.',
            'actions' => [
                'Revisar prioridades, riesgos y dependencias trimestralmente.',
                'Medir beneficios contra línea base.',
                'Automatizar procesos ya estandarizados.',
                'Incorporar inteligencia donde exista dato confiable y caso de negocio.',
            ],
            'owner_role' => 'Dirección / líder de transformación',
            'dependencies' => ['DAT-01', 'TEC-01'],
            'impact' => 'medium',
            'effort' => 'medium',
            'success_metrics' => [
                '% iniciativas con beneficio medido',
                '# mejoras cerradas por trimestre',
            ],
            'phase' => 4,
            'horizon' => '181-365 días',
            'rationale' => sprintf(
                'La madurez actual es %s/100.',
                $this->number((float) ($assessment->maturity_score ?? 0))
            ),
        ];
    }

    private function sequence(array $initiatives): array
    {
        usort(
            $initiatives,
            fn (array $a, array $b): int =>
                ((int) $a['phase'] <=> (int) $b['phase'])
                ?: ($this->priorityWeight((string) $a['priority'])
                    <=> $this->priorityWeight((string) $b['priority']))
        );

        foreach ($initiatives as $index => &$initiative) {
            $initiative['sequence'] = $index + 1;
        }

        unset($initiative);

        return $initiatives;
    }

    private function phases(array $initiatives): array
    {
        $definitions = [
            1 => ['title' => 'Fase 1 · Alinear y estabilizar', 'horizon' => '0-30 días'],
            2 => ['title' => 'Fase 2 · Digitalizar el núcleo', 'horizon' => '31-90 días'],
            3 => ['title' => 'Fase 3 · Conectar y medir', 'horizon' => '91-180 días'],
            4 => ['title' => 'Fase 4 · Automatizar y escalar', 'horizon' => '181-365 días'],
        ];

        $result = [];

        foreach ($definitions as $number => $definition) {
            $ids = array_values(array_map(
                fn (array $item): string => (string) $item['id'],
                array_filter(
                    $initiatives,
                    fn (array $item): bool => (int) $item['phase'] === $number
                )
            ));

            $result[] = [
                'number' => $number,
                ...$definition,
                'initiative_ids' => $ids,
            ];
        }

        return $result;
    }

    private function priority(float $score): string
    {
        return match (true) {
            $score <= 40 => 'critical',
            $score <= 60 => 'high',
            $score <= 80 => 'medium',
            default => 'sustain',
        };
    }

    private function impact(float $score, float $urgency): string
    {
        if ($score <= 40 || $urgency >= 75) return 'high';
        if ($score <= 70 || $urgency >= 50) return 'medium';
        return 'low';
    }

    private function phaseFor(string $key, string $priority): int
    {
        if (in_array($key, ['strategy', 'governance', 'people'], true)) {
            return 1;
        }

        if (
            $priority === 'critical'
            && in_array($key, ['commercial', 'operations', 'presence'], true)
        ) {
            return 1;
        }

        if (in_array($key, ['commercial', 'operations', 'presence'], true)) {
            return 2;
        }

        if (in_array($key, ['technology', 'data'], true)) {
            return 3;
        }

        return 4;
    }

    private function horizonFor(string $key, string $priority): string
    {
        return match ($this->phaseFor($key, $priority)) {
            1 => '0-30 días',
            2 => '31-90 días',
            3 => '91-180 días',
            default => '181-365 días',
        };
    }

    private function priorityWeight(string $priority): int
    {
        return match ($priority) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'sustain' => 4,
            default => 5,
        };
    }

    private function number(float $value): string
    {
        $number = round($value, 1);

        return floor($number) === $number
            ? (string) (int) $number
            : number_format($number, 1, '.', '');
    }
}
