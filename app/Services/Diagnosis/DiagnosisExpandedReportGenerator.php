<?php

namespace App\Services\Diagnosis;

use App\Models\DiagnosisAssessment;

class DiagnosisExpandedReportGenerator
{
    private const DIMENSION_LABELS = [
        'strategy' => 'Estrategia y Liderazgo',
        'people' => 'Personas y Cultura Digital',
        'presence' => 'Presencia y Experiencia Digital',
        'commercial' => 'Gestión Comercial y Clientes',
        'operations' => 'Procesos y Operación',
        'technology' => 'Tecnología e Integración',
        'data' => 'Datos e Inteligencia',
        'governance' => 'Gobierno, Seguridad y Control',
    ];

    /**
     * Produce un borrador estructurado.
     *
     * No publica, no factura, no modifica el scoring y no llama
     * servicios externos.
     *
     * @return array{
     *     source_snapshot: array<string,mixed>,
     *     sections: array<string,mixed>
     * }
     */
    public function generate(
        DiagnosisAssessment $assessment
    ): array {
        $dimensions = $this->dimensions(
            $assessment->dimension_scores ?? []
        );

        $gaps = array_slice($dimensions, 0, 3);
        $strengths = array_slice(
            array_reverse($dimensions),
            0,
            2
        );

        return [
            'source_snapshot' =>
                $this->sourceSnapshot($assessment),
            'sections' => [
                'executive_summary' =>
                    $this->executiveSummary($assessment),
                'business_context' =>
                    $this->businessContext($assessment),
                'maturity_interpretation' =>
                    $this->maturityInterpretation(
                        $assessment
                    ),
                'dimension_analysis' =>
                    $this->dimensionAnalysis($dimensions),
                'critical_gaps' =>
                    $this->criticalGaps($gaps),
                'relative_strengths' =>
                    $this->relativeStrengths($strengths),
                'business_implications' =>
                    $this->businessImplications(
                        $assessment
                    ),
                'recommended_focus' =>
                    $this->recommendedFocus($assessment),
                'execution_capacity' =>
                    $this->executionCapacity(
                        $assessment
                    ),
                'next_step_note' => [
                    'title' =>
                        'Del Informe Ampliado al Roadmap Detallado',
                    'body' =>
                        'Este informe profundiza los hallazgos, implicaciones y focos recomendados del Diagnóstico LAUDA 360. No sustituye el Roadmap Detallado: la definición de iniciativas, responsables, dependencias, secuencia de ejecución, esfuerzo y calendario corresponde al siguiente entregable.',
                ],
            ],
        ];
    }

    /**
     * @return array<int,array{
     *     key:string,
     *     label:string,
     *     score:float
     * }>
     */
    private function dimensions(array $scores): array
    {
        $result = [];

        foreach (
            self::DIMENSION_LABELS as $key => $label
        ) {
            $value = $scores[$key] ?? null;

            if (! is_numeric($value)) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'label' => $label,
                'score' => round(
                    (float) $value,
                    1
                ),
            ];
        }

        usort(
            $result,
            fn (array $a, array $b): int =>
                $a['score'] <=> $b['score']
        );

        return $result;
    }

    private function sourceSnapshot(
        DiagnosisAssessment $assessment
    ): array {
        return [
            'assessment_id' => $assessment->id,
            'organization_name' =>
                $assessment->organization_name,
            'methodology_version' =>
                $assessment->methodology_version,
            'diagnosis_status' =>
                $assessment->status,
            'diagnosis_published_at' =>
                $assessment->published_at
                    ?->toISOString(),
            'scores' => [
                'maturity' =>
                    $assessment->maturity_score,
                'capacity' =>
                    $assessment->capacity_score,
                'urgency' =>
                    $assessment->urgency_score,
                'dimensions' =>
                    $assessment->dimension_scores ?? [],
            ],
            'levels' => [
                'maturity' =>
                    $assessment->maturity_level,
                'urgency' =>
                    $assessment->urgency_level,
            ],
            'official_result' => [
                'summary' =>
                    $assessment->review_summary,
                'priorities' =>
                    $assessment->review_priorities ?? [],
            ],
            'business_profile' => [
                'business_activity_type' =>
                    $assessment->business_activity_type,
                'business_sector' =>
                    $assessment->business_sector,
                'business_sector_other' =>
                    $assessment->business_sector_other,
                'customer_market' =>
                    $assessment->customer_market,
                'sales_channels' =>
                    $assessment->sales_channels ?? [],
                'sales_channel_other' =>
                    $assessment->sales_channel_other,
                'logistics_operation_types' =>
                    $assessment
                        ->logistics_operation_types
                        ?? [],
                'logistics_operation_other' =>
                    $assessment
                        ->logistics_operation_other,
                'business_activity_description' =>
                    $assessment
                        ->business_activity_description,
            ],
        ];
    }

    private function executiveSummary(
        DiagnosisAssessment $assessment
    ): array {
        $official = trim(
            (string) $assessment->review_summary
        );

        if ($official === '') {
            $official = sprintf(
                '%s presenta una madurez digital de %s/100, con capacidad interna de %s/100 y urgencia de %s/100.',
                $assessment->organization_name,
                $this->number(
                    $assessment->maturity_score
                ),
                $this->number(
                    $assessment->capacity_score
                ),
                $this->number(
                    $assessment->urgency_score
                )
            );
        }

        return [
            'title' => 'Conclusión ejecutiva',
            'body' => $official,
        ];
    }

    private function businessContext(
        DiagnosisAssessment $assessment
    ): array {
        $activity = $this->profileLabel(
            'activity_types',
            $assessment->business_activity_type
        );

        $sector = $this->profileLabel(
            'sectors',
            $assessment->business_sector
        );

        if (
            $assessment->business_sector === 'other'
            && filled(
                $assessment->business_sector_other
            )
        ) {
            $sector = trim(
                (string)
                    $assessment->business_sector_other
            );
        }

        $market = $this->profileLabel(
            'customer_markets',
            $assessment->customer_market
        );

        $channels = array_values(
            array_filter(
                array_map(
                    fn (mixed $value): ?string =>
                        is_string($value)
                            ? $this->profileLabel(
                                'sales_channels',
                                $value
                            )
                            : null,
                    $assessment->sales_channels ?? []
                )
            )
        );

        $operations = array_values(
            array_filter(
                array_map(
                    fn (mixed $value): ?string =>
                        is_string($value)
                            ? $this->profileLabel(
                                'logistics_operation_types',
                                $value
                            )
                            : null,
                    $assessment
                        ->logistics_operation_types
                        ?? []
                )
            )
        );

        return [
            'title' => 'Contexto del negocio',
            'activity_type' => $activity,
            'sector' => $sector,
            'customer_market' => $market,
            'sales_channels' => $channels,
            'logistics_operations' => $operations,
            'description' =>
                $assessment
                    ->business_activity_description,
            'interpretation' =>
                $this->businessContextInterpretation(
                    $assessment
                ),
        ];
    }

    private function maturityInterpretation(
        DiagnosisAssessment $assessment
    ): array {
        return [
            'title' =>
                'Lectura de madurez, capacidad y urgencia',
            'maturity' => [
                'score' =>
                    $assessment->maturity_score,
                'level' =>
                    $assessment->maturity_level,
                'interpretation' =>
                    $this->scoreInterpretation(
                        (float)
                            ($assessment
                                ->maturity_score ?? 0),
                        'madurez digital'
                    ),
            ],
            'capacity' => [
                'score' =>
                    $assessment->capacity_score,
                'interpretation' =>
                    $this->scoreInterpretation(
                        (float)
                            ($assessment
                                ->capacity_score ?? 0),
                        'capacidad interna para transformar'
                    ),
            ],
            'urgency' => [
                'score' =>
                    $assessment->urgency_score,
                'level' =>
                    $assessment->urgency_level,
                'interpretation' =>
                    $this->urgencyInterpretation(
                        (float)
                            ($assessment
                                ->urgency_score ?? 0)
                    ),
            ],
        ];
    }

    /**
     * @param array<int,array{
     *     key:string,
     *     label:string,
     *     score:float
     * }> $dimensions
     */
    private function dimensionAnalysis(
        array $dimensions
    ): array {
        return [
            'title' => 'Análisis por dimensión',
            'items' => array_map(
                fn (array $dimension): array => [
                    ...$dimension,
                    'band' =>
                        $this->band(
                            $dimension['score']
                        ),
                    'interpretation' =>
                        $this->dimensionInterpretation(
                            $dimension['key'],
                            $dimension['score']
                        ),
                ],
                $dimensions
            ),
        ];
    }

    /**
     * @param array<int,array{
     *     key:string,
     *     label:string,
     *     score:float
     * }> $gaps
     */
    private function criticalGaps(
        array $gaps
    ): array {
        return [
            'title' => 'Brechas críticas',
            'items' => array_map(
                fn (array $gap): array => [
                    ...$gap,
                    'impact' =>
                        $this->gapImpact(
                            $gap['key']
                        ),
                ],
                $gaps
            ),
        ];
    }

    /**
     * @param array<int,array{
     *     key:string,
     *     label:string,
     *     score:float
     * }> $strengths
     */
    private function relativeStrengths(
        array $strengths
    ): array {
        return [
            'title' =>
                'Capacidades relativamente más desarrolladas',
            'items' => array_map(
                fn (array $item): array => [
                    ...$item,
                    'note' =>
                        'Esta dimensión presenta una posición relativa superior dentro del diagnóstico actual. Debe preservarse y utilizarse como apoyo para cerrar las brechas prioritarias.',
                ],
                $strengths
            ),
        ];
    }

    private function businessImplications(
        DiagnosisAssessment $assessment
    ): array {
        $sector = $assessment->business_sector;
        $activity =
            $assessment->business_activity_type;

        if ($sector === 'logistics') {
            return [
                'title' =>
                    'Implicaciones para una operación logística',
                'items' => [
                    'La transformación debe analizar el flujo de punta a punta: recepción, almacenamiento, inventario, preparación/picking, empaque, despacho, transporte, entrega/POD, devoluciones y facturación/cobranza.',
                    'La trazabilidad de mercancías, estados operativos, responsables, tiempos y evidencias debe diseñarse como un flujo conectado y no como tareas aisladas.',
                    'Los indicadores deben permitir medir productividad, cumplimiento de servicio, incidencias, tiempos de ciclo, utilización de recursos y calidad de entrega.',
                ],
            ];
        }

        if ($sector === 'transportation') {
            return [
                'title' =>
                    'Implicaciones para una operación de transporte',
                'items' => [
                    'La digitalización debe conectar planificación, asignación, despacho, ejecución, seguimiento, incidencias, evidencia de entrega, liquidación y facturación.',
                    'La visibilidad de vehículos, conductores, rutas, cargas, tiempos y cumplimiento debe evolucionar hacia una fuente operativa común.',
                    'Los controles y KPIs deben ayudar a reducir tiempos improductivos, reprocesos, desvíos y falta de trazabilidad.',
                ],
            ];
        }

        return [
            'title' => 'Implicaciones para el modelo de negocio',
            'items' => match ($activity) {
                'goods' => [
                    'El ciclo de catálogo y precios, inventario, pedido, preparación, despacho, facturación y cobranza debe operar con datos consistentes.',
                    'La disponibilidad, rotación, margen, nivel de servicio y cumplimiento de pedidos deben convertirse en indicadores recurrentes.',
                    'Ecommerce y fuerza comercial deben consultar la misma realidad operativa para evitar promesas que inventario y despacho no pueden cumplir.',
                ],
                'services' => [
                    'El ciclo de captación, cotización, agenda o proyecto, asignación de recursos, ejecución, facturación, cobranza, seguimiento y renovación debe conectarse.',
                    'La empresa necesita trazabilidad sobre demanda, capacidad, tiempos, rentabilidad y calidad de servicio.',
                    'La digitalización debe reducir dependencia de conocimiento informal, mensajería aislada y seguimiento manual.',
                ],
                'mixed' => [
                    'La empresa debe evitar dos operaciones digitales separadas para bienes y servicios cuando forman parte de una misma experiencia comercial.',
                    'Pedido, inventario, ejecución del servicio, recursos, facturación y cobranza deben compartir contexto y trazabilidad.',
                    'Los indicadores deben distinguir rentabilidad y desempeño por componente sin perder la visión integrada del cliente.',
                ],
                default => [
                    'La transformación debe priorizar los procesos que más afectan ingresos, servicio, control y capacidad de decisión.',
                    'Las iniciativas deben diseñarse alrededor del flujo real de trabajo y no solamente alrededor de herramientas.',
                ],
            },
        ];
    }

    private function recommendedFocus(
        DiagnosisAssessment $assessment
    ): array {
        $priorities = array_values(
            array_filter(
                $assessment->review_priorities ?? [],
                fn (mixed $item): bool =>
                    is_string($item)
                    && trim($item) !== ''
            )
        );

        if ($priorities === []) {
            $priorities = [
                'Convertir las principales brechas del diagnóstico en una agenda priorizada de transformación.',
                'Definir responsables, indicadores y criterios de éxito antes de seleccionar o ampliar herramientas.',
                'Asegurar adopción y disciplina operativa antes de automatizar procesos inestables.',
            ];
        }

        return [
            'title' =>
                'Focos recomendados para profundización',
            'items' => array_slice(
                $priorities,
                0,
                5
            ),
        ];
    }

    private function executionCapacity(
        DiagnosisAssessment $assessment
    ): array {
        return [
            'title' =>
                'Capacidad interna para ejecutar el cambio',
            'capacity_score' =>
                $assessment->capacity_score,
            'body' =>
                'Este indicador ayuda a dimensionar la capacidad interna disponible para convertir los hallazgos en acciones. Debe interpretarse junto con la urgencia, las dependencias, los responsables y el contexto del negocio; no define una modalidad comercial ni implica contratación.',
        ];
    }

    private function businessContextInterpretation(
        DiagnosisAssessment $assessment
    ): string {
        if (
            $assessment->business_sector === 'logistics'
        ) {
            return
                'En logística, una misma brecha digital puede impactar simultáneamente inventario, preparación, despacho, transporte, prueba de entrega, devoluciones y facturación. Por eso el análisis debe seguir el flujo físico y documental de punta a punta.';
        }

        if (
            $assessment->business_sector
            === 'transportation'
        ) {
            return
                'En transporte, las recomendaciones deben relacionarse con planificación, asignación, ejecución, seguimiento, incidencias, evidencia de entrega y liquidación, preservando la diferencia entre transporte y operación logística integral.';
        }

        return match (
            $assessment->business_activity_type
        ) {
            'goods' =>
                'En una empresa orientada a bienes, las brechas se interpretan considerando disponibilidad, inventario, margen, pedido, despacho, facturación y cobranza.',

            'services' =>
                'En una empresa de servicios, las brechas se interpretan considerando captación, cotización, agenda o proyectos, capacidad de recursos, ejecución, facturación, seguimiento y renovación.',

            'mixed' =>
                'En una empresa mixta, las recomendaciones deben integrar el ciclo de bienes y servicios para evitar procesos, datos y experiencias fragmentadas.',

            default =>
                'El análisis se contextualiza con la actividad y operación declaradas por la empresa.',
        };
    }

    private function dimensionInterpretation(
        string $key,
        float $score
    ): string {
        $theme = match ($key) {
            'strategy' =>
                'objetivos, patrocinio ejecutivo, priorización e indicadores',
            'people' =>
                'capacidades internas, roles, adopción y cultura de cambio',
            'presence' =>
                'puntos de contacto, experiencia digital y coherencia de canales',
            'commercial' =>
                'captación, ventas, clientes, seguimiento, conversión y postventa',
            'operations' =>
                'estandarización, trazabilidad, productividad y control operativo',
            'technology' =>
                'arquitectura, sistemas maestros, integración y reducción de silos',
            'data' =>
                'calidad de datos, KPIs, analítica y decisiones basadas en evidencia',
            'governance' =>
                'seguridad, accesos, respaldos, políticas, riesgos y gobierno',
            default =>
                'capacidades digitales y disciplina de gestión',
        };

        return sprintf(
            'Con %s/100, la dimensión se encuentra en nivel %s. El análisis ampliado debe revisar %s y su impacto directo sobre el modelo operativo de la empresa.',
            $this->number($score),
            mb_strtolower($this->band($score)),
            $theme
        );
    }

    private function gapImpact(string $key): string
    {
        return match ($key) {
            'strategy' =>
                'Sin dirección y priorización claras, la inversión digital tiende a fragmentarse y a responder a urgencias en lugar de resultados empresariales.',
            'people' =>
                'La falta de responsables, habilidades o adopción puede impedir que procesos y herramientas produzcan mejoras sostenibles.',
            'presence' =>
                'Una experiencia digital débil limita captación, servicio, confianza y consistencia entre canales.',
            'commercial' =>
                'La falta de trazabilidad comercial reduce conversión, seguimiento, previsibilidad de ingresos y conocimiento del cliente.',
            'operations' =>
                'Procesos no estandarizados elevan reprocesos, tiempos, errores y riesgo al intentar automatizar.',
            'technology' =>
                'Sistemas aislados multiplican digitación, inconsistencias, dependencias manuales y dificultad para escalar.',
            'data' =>
                'Datos poco confiables impiden medir desempeño, anticipar problemas y sostener decisiones recurrentes.',
            'governance' =>
                'Controles débiles incrementan exposición operativa, seguridad, continuidad y cumplimiento.',
            default =>
                'La brecha limita la capacidad de transformar de forma predecible y medible.',
        };
    }

    private function scoreInterpretation(
        float $score,
        string $subject
    ): string {
        return sprintf(
            'La %s se ubica en %s (%s/100). Esto indica que las siguientes decisiones deben equilibrar avance, disciplina interna y capacidad real de adopción.',
            $subject,
            mb_strtolower($this->band($score)),
            $this->number($score)
        );
    }

    private function urgencyInterpretation(
        float $score
    ): string {
        return match (true) {
            $score >= 80 =>
                'La urgencia es muy alta. Conviene reducir exposición y estabilizar los puntos críticos antes de ampliar iniciativas no esenciales.',
            $score >= 60 =>
                'La urgencia es alta. Las acciones deben concentrarse en brechas con impacto inmediato sobre continuidad, cliente, ingresos o control.',
            $score >= 40 =>
                'La urgencia es moderada. Existe espacio para secuenciar cambios, pero conviene evitar que las brechas actuales se consoliden.',
            default =>
                'La urgencia es relativamente baja. Esto permite priorizar con mayor disciplina, siempre que no existan riesgos críticos específicos.',
        };
    }

    private function band(float $score): string
    {
        return match (true) {
            $score <= 20 => 'Crítico',
            $score <= 40 => 'Inicial',
            $score <= 60 => 'En desarrollo',
            $score <= 80 => 'Consolidado',
            default => 'Avanzado',
        };
    }

    private function profileLabel(
        string $group,
        mixed $value
    ): ?string {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return config(
            "lauda360_business_profile.{$group}.{$value}",
            $value
        );
    }

    private function number(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0';
        }

        $number = round((float) $value, 1);

        return fmod($number, 1.0) === 0.0
            ? (string) (int) $number
            : number_format(
                $number,
                1,
                '.',
                ''
            );
    }
}
