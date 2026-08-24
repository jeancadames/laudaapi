<?php

namespace App\Services\Diagnosis;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DiagnosisExecutiveSummaryGenerator
{
    /**
     * Genera un borrador editable para revisión humana de LAUDA.
     *
     * No publica, no modifica scoring y no usa servicios externos.
     *
     * @return array{
     *     summary: string,
     *     priorities: array<int, string>,
     *     modality: string|null,
     *     modality_label: string|null
     * }
     */
    public function generate(array $result, array $profile): array
    {
        $dimensions = $this->normalizedDimensions(
            $result['dimension_scores'] ?? []
        );

        $gaps = array_slice($dimensions, 0, 3);

        $summary = $this->buildSummary(
            $result,
            $profile,
            $gaps
        );

        $priorities = $this->buildPriorities(
            $profile,
            $gaps
        );

        return [
            'summary' => $summary,
            'priorities' => $priorities,
            'modality' =>
                $result['recommended_modality'] ?? null,
            'modality_label' =>
                $result['recommended_modality_label'] ?? null,
        ];
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     score: float
     * }>
     */
    private function normalizedDimensions(array $scores): array
    {
        $dimensions = [];

        foreach ($scores as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $dimensions[] = [
                'key' => (string) $key,
                'label' => $this->dimensionLabel(
                    (string) $key
                ),
                'score' => round((float) $value, 1),
            ];
        }

        usort(
            $dimensions,
            fn (array $a, array $b): int =>
                $a['score'] <=> $b['score']
        );

        return $dimensions;
    }

    private function buildSummary(
        array $result,
        array $profile,
        array $gaps
    ): string {
        $maturityScore = $this->number(
            $result['maturity_score'] ?? null
        );

        $capacityScore = $this->number(
            $result['capacity_score'] ?? null
        );

        $urgencyScore = $this->number(
            $result['urgency_score'] ?? null
        );

        $maturityLevel =
            trim((string) (
                $result['maturity_level']
                ?? 'nivel pendiente'
            ));

        $urgencyLevel =
            trim((string) (
                $result['urgency_level']
                ?? 'nivel pendiente'
            ));

        $modality =
            trim((string) (
                $result['recommended_modality_label']
                ?? 'modalidad pendiente de revisión'
            ));

        $context = $this->businessContextSentence(
            $profile
        );

        $gapSentence = '';

        if ($gaps !== []) {
            $gapParts = array_map(
                fn (array $gap): string =>
                    sprintf(
                        '%s (%s/100)',
                        $gap['label'],
                        $this->number($gap['score'])
                    ),
                $gaps
            );

            $gapSentence = sprintf(
                ' Las brechas de menor puntuación se concentran en %s.',
                $this->joinSpanish($gapParts)
            );
        }

        return trim(
            sprintf(
                'El Diagnóstico LAUDA 360 ubica a la empresa en el nivel %s con una madurez digital de %s/100. %s%s La capacidad interna alcanza %s/100 y la urgencia %s/100 (%s). Con estos resultados, la modalidad sugerida es %s. La recomendación debe ser validada por LAUDA antes de convertirse en el resultado oficial.',
                $maturityLevel,
                $maturityScore,
                $context,
                $gapSentence,
                $capacityScore,
                $urgencyScore,
                $urgencyLevel,
                $modality
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildPriorities(
        array $profile,
        array $gaps
    ): array {
        $priorities = [];

        foreach (array_slice($gaps, 0, 2) as $gap) {
            $priorities[] = $this->priorityForDimension(
                $gap['key'],
                $gap['label'],
                $gap['score']
            );
        }

        $businessPriority = $this->businessPriority(
            $profile
        );

        if ($businessPriority !== null) {
            $priorities[] = $businessPriority;
        }

        foreach ($gaps as $gap) {
            if (count($priorities) >= 4) {
                break;
            }

            $candidate = $this->priorityForDimension(
                $gap['key'],
                $gap['label'],
                $gap['score']
            );

            if (! in_array(
                $candidate,
                $priorities,
                true
            )) {
                $priorities[] = $candidate;
            }
        }

        if ($priorities === []) {
            $priorities[] =
                'Definir un plan de transformación con responsables, metas, indicadores y revisión periódica de avances.';
        }

        return array_values(
            array_slice(
                array_unique($priorities),
                0,
                5
            )
        );
    }

    private function priorityForDimension(
        string $key,
        string $label,
        float $score
    ): string {
        $normalized = Str::lower(
            Str::ascii($key . ' ' . $label)
        );

        $prefix = sprintf(
            'Priorizar %s (%s/100): ',
            $label,
            $this->number($score)
        );

        return match (true) {
            Str::contains(
                $normalized,
                ['strategy', 'estrateg', 'leadership', 'lider']
            ) =>
                $prefix .
                'definir objetivos digitales, responsables, indicadores y una agenda de transformación vinculada al negocio.',

            Str::contains(
                $normalized,
                ['people', 'persona', 'culture', 'cultura']
            ) =>
                $prefix .
                'asignar responsables, desarrollar capacidades internas y formalizar la adopción de nuevos procesos y herramientas.',

            Str::contains(
                $normalized,
                ['presence', 'presencia', 'experience', 'experiencia']
            ) =>
                $prefix .
                'fortalecer los puntos de contacto digitales y asegurar una experiencia coherente para clientes y prospectos.',

            Str::contains(
                $normalized,
                ['commercial', 'comercial', 'client', 'cliente', 'sale', 'venta']
            ) =>
                $prefix .
                'estandarizar el ciclo comercial, seguimiento de oportunidades, cotización, conversión, postventa y trazabilidad del cliente.',

            Str::contains(
                $normalized,
                ['process', 'proceso', 'operation', 'operacion']
            ) =>
                $prefix .
                'documentar, estandarizar y digitalizar los procesos críticos antes de automatizarlos o integrarlos.',

            Str::contains(
                $normalized,
                ['technology', 'tecnolog', 'integration', 'integracion']
            ) =>
                $prefix .
                'reducir silos tecnológicos, definir sistemas maestros e integrar los flujos que hoy dependen de reprocesos manuales.',

            Str::contains(
                $normalized,
                ['data', 'dato', 'intelligence', 'inteligencia']
            ) =>
                $prefix .
                'definir datos confiables, KPIs de gestión y tableros que permitan decisiones recurrentes basadas en evidencia.',

            Str::contains(
                $normalized,
                ['governance', 'gobierno', 'security', 'seguridad', 'control']
            ) =>
                $prefix .
                'formalizar accesos, respaldos, seguridad, controles, responsables y políticas mínimas de gobierno digital.',

            default =>
                $prefix .
                'establecer un plan de mejora con responsables, entregables, indicadores y seguimiento periódico.',
        };
    }

    private function businessContextSentence(
        array $profile
    ): string {
        $activity = $this->profileLabel(
            'activity_types',
            $profile['business_activity_type'] ?? null
        );

        $sector = $this->profileLabel(
            'sectors',
            $profile['business_sector'] ?? null
        );

        if (
            ($profile['business_sector'] ?? null) === 'other'
            && filled($profile['business_sector_other'] ?? null)
        ) {
            $sector = trim(
                (string) $profile['business_sector_other']
            );
        }

        $market = $this->profileLabel(
            'customer_markets',
            $profile['customer_market'] ?? null
        );

        $parts = [];

        if ($activity !== null) {
            $parts[] = 'actividad ' . $activity;
        }

        if ($sector !== null) {
            $parts[] = 'sector ' . $sector;
        }

        if ($market !== null) {
            $parts[] = 'mercado ' . $market;
        }

        $description = trim(
            (string) (
                $profile['business_activity_description']
                ?? ''
            )
        );

        if ($parts === [] && $description === '') {
            return 'El análisis se interpreta como contexto general de la empresa.';
        }

        $sentence =
            'El contexto comercial declarado corresponde a ' .
            $this->joinSpanish($parts) .
            '.';

        if ($description !== '') {
            $sentence .= ' La actividad se describe como: ' .
                $description . '.';
        }

        return $sentence;
    }

    private function businessPriority(
        array $profile
    ): ?string {
        $sector =
            $profile['business_sector'] ?? null;

        $activity =
            $profile['business_activity_type'] ?? null;

        if ($sector === 'logistics') {
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
                        $profile['logistics_operation_types'] ?? []
                    )
                )
            );

            $operationText = $operations !== []
                ? ' con foco inicial en ' .
                    $this->joinSpanish($operations)
                : '';

            return
                'Alinear la transformación al flujo logístico de punta a punta: recepción, almacenamiento, inventario, preparación, empaque, despacho, transporte, entrega/POD, devoluciones y facturación/cobranza' .
                $operationText .
                '.';
        }

        if ($sector === 'transportation') {
            return
                'Digitalizar el ciclo operativo de transporte: planificación, asignación, ejecución, seguimiento, evidencia de entrega, incidencias, liquidación y facturación.';
        }

        return match ($activity) {
            'goods' =>
                'Conectar el ciclo de bienes: catálogo y precios, inventario, pedido, preparación, despacho, facturación, cobranza y análisis de rotación.',

            'services' =>
                'Conectar el ciclo de servicios: captación, cotización, agenda o proyecto, asignación de recursos, ejecución, facturación, cobranza, seguimiento y renovación.',

            'mixed' =>
                'Diseñar un flujo integrado para bienes y servicios, evitando que pedidos, inventario, ejecución del servicio, facturación y cobranza operen como procesos separados.',

            default => null,
        };
    }

    private function profileLabel(
        string $group,
        mixed $value
    ): ?string {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $configured = config(
            "lauda360_business_profile.{$group}.{$value}"
        );

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return Str::headline($value);
    }

    private function dimensionLabel(
        string $key
    ): string {
        $explicit = [
            'strategy_leadership' =>
                'Estrategia y liderazgo',
            'people_culture' =>
                'Personas y cultura',
            'presence_experience' =>
                'Presencia y experiencia digital',
            'commercial_clients' =>
                'Gestión comercial y clientes',
            'processes_operations' =>
                'Procesos y operación',
            'technology_integration' =>
                'Tecnología e integración',
            'data_intelligence' =>
                'Datos e inteligencia',
            'governance_security_control' =>
                'Gobierno, seguridad y control',
        ];

        if (isset($explicit[$key])) {
            return $explicit[$key];
        }

        $configLabel = $this->dimensionLabelFromConfig(
            $key
        );

        if ($configLabel !== null) {
            return $configLabel;
        }

        return Str::headline(
            str_replace(
                ['-', '.'],
                '_',
                $key
            )
        );
    }

    private function dimensionLabelFromConfig(
        string $key
    ): ?string {
        $dimensions = config(
            'lauda360_diagnosis.dimensions',
            []
        );

        if (! is_array($dimensions)) {
            return null;
        }

        $entry = $dimensions[$key] ?? null;

        if (is_string($entry) && $entry !== '') {
            return $entry;
        }

        if (is_array($entry)) {
            foreach (['label', 'name', 'title'] as $field) {
                $value = Arr::get(
                    $entry,
                    $field
                );

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        foreach ($dimensions as $dimension) {
            if (! is_array($dimension)) {
                continue;
            }

            $dimensionKey =
                $dimension['key']
                ?? $dimension['id']
                ?? $dimension['slug']
                ?? null;

            if ($dimensionKey !== $key) {
                continue;
            }

            foreach (['label', 'name', 'title'] as $field) {
                $value = $dimension[$field] ?? null;

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function number(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        $number = round((float) $value, 1);

        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return number_format(
            $number,
            1,
            '.',
            ''
        );
    }

    /**
     * @param array<int, string> $items
     */
    private function joinSpanish(array $items): string
    {
        $items = array_values(
            array_filter(
                array_map(
                    fn (string $item): string =>
                        trim($item),
                    $items
                ),
                fn (string $item): bool =>
                    $item !== ''
            )
        );

        return match (count($items)) {
            0 => '',
            1 => $items[0],
            2 => $items[0] . ' y ' . $items[1],
            default =>
                implode(
                    ', ',
                    array_slice($items, 0, -1)
                ) .
                ' y ' .
                $items[array_key_last($items)],
        };
    }
}
