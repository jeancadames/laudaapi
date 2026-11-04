<?php

namespace App\Services\Diagnosis;

/** Narrative interpretation only: never changes scores or the official ranges. */
class DiagnosisInterpretation
{
    public const DECLARATION_NOTICE = 'Lectura basada en respuestas y notas declaradas por la empresa; no constituye una verificación técnica independiente.';

    public function bands(): array
    {
        return [
            'maturity' => config('lauda360_diagnosis.maturity_levels', []),
            'capacity' => array_map(fn (array $band): array => [
                'min' => $band['min'],
                'max' => $band['max'],
                'label' => match ($band['modality']) {
                    'guided' => 'Capacidad interna alta',
                    'assisted' => 'Capacidad interna media',
                    'managed' => 'Capacidad interna limitada',
                },
            ], config('lauda360_diagnosis.capacity_recommendations', [])),
            'urgency' => config('lauda360_diagnosis.urgency_levels', []),
        ];
    }

    public function level(string $indicator, mixed $score): string
    {
        if (is_numeric($score)) {
            foreach ($this->bands()[$indicator] ?? [] as $band) {
                if ($score >= $band['min'] && $score <= $band['max']) {
                    return $band['label'];
                }
            }
        }

        return 'Pendiente de interpretación';
    }

    public function classification(float $score): string
    {
        foreach (config('lauda360_diagnosis.maturity_levels', []) as $index => $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                // Preserve the existing critical band (0–20). Higher bands
                // distinguish initial gaps, improvement and advanced strengths.
                return ['critical', 'gap', 'opportunity', 'opportunity', 'strength'][$index];
            }
        }

        return 'unknown';
    }

    public function classificationLabel(float $score): string
    {
        return match ($this->classification($score)) {
            'critical' => 'Brecha crítica declarada',
            'gap' => 'Brecha de nivel inicial',
            'opportunity' => 'Oportunidad de mejora',
            'strength' => 'Fortaleza que conviene mantener',
            default => 'Pendiente de interpretación',
        };
    }

    public function evidence(array $questionIds, array $answers, array $notes): array
    {
        $items = [];
        foreach ($questionIds as $id) {
            $value = $answers[$id] ?? null;
            $note = is_string($notes[$id] ?? null) ? trim($notes[$id]) : '';
            if (is_string($value) && preg_match('/^[1-5]$/D', $value)) {
                $value = (int) $value;
            }
            if (! is_int($value) || $value < 1 || $value > 5) {
                $value = null;
            }
            if ($value !== null || $note !== '') {
                $items[] = ['question_id' => $id, 'answer' => $value, 'note' => $note];
            }
        }

        return $items;
    }

    public function dimensionEvidence(string $key, array $answers, array $notes): array
    {
        return $this->evidence(config("lauda360_diagnosis.dimensions.{$key}.questions", []), $answers, $notes);
    }

    public function evidenceText(array $items): string
    {
        if ($items === []) {
            return ' Sustento: puntuación agregada; sin respuestas o notas asociadas disponibles.';
        }
        usort($items, fn (array $a, array $b): int => ($a['answer'] ?? 6) <=> ($b['answer'] ?? 6));
        $refs = array_map(function (array $item): string {
            $ref = $item['question_id'].($item['answer'] !== null ? '='.$item['answer'].'/5' : '=sin respuesta');
            if ($item['note'] !== '') {
                $ref .= ' (nota declarada: '.\Illuminate\Support\Str::limit($item['note'], 60).')';
            }

            return $ref;
        }, array_slice($items, 0, 2));

        return ' Sustento declarado: '.implode('; ', $refs).'.';
    }
}
