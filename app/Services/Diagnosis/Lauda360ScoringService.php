<?php

namespace App\Services\Diagnosis;

class Lauda360ScoringService
{
    public function calculate(array $answers): array
    {
        $dimensionScores = [];
        $weightedMaturity = 0.0;

        foreach (config('lauda360_diagnosis.dimensions', []) as $dimension => $definition) {
            $score = $this->normalizedScore(
                $this->scoresFor($definition['questions'], $answers)
            );

            $dimensionScores[$dimension] = $score;
            $weightedMaturity += $score * (((int) $definition['weight']) / 100);
        }

        $maturityScore = (int) round($weightedMaturity);
        $capacityScore = $this->normalizedScore(
            $this->scoresFor(config('lauda360_diagnosis.capacity_questions', []), $answers)
        );
        $urgencyScore = $this->normalizedScore(
            $this->scoresFor(config('lauda360_diagnosis.urgency_questions', []), $answers)
        );

        $maturityBand = $this->band(
            $maturityScore,
            config('lauda360_diagnosis.maturity_levels', [])
        );
        $capacityBand = $this->band(
            $capacityScore,
            config('lauda360_diagnosis.capacity_recommendations', [])
        );
        $urgencyBand = $this->band(
            $urgencyScore,
            config('lauda360_diagnosis.urgency_levels', [])
        );

        $modality = $capacityBand['modality'] ?? null;
        $modalityLabel = $capacityBand['label'] ?? null;
        $reviewRequired = $this->hasCriticalGovernanceRisk($answers);

        // Regla comercial: una urgencia crítica no puede salir automáticamente
        // como Guiado/autoservicio sin revisión humana.
        if (($urgencyBand['label'] ?? null) === 'Crítica' && $modality === 'guided') {
            $modality = 'assisted';
            $modalityLabel = 'LAUDA 360 Asistido';
            $reviewRequired = true;
        }

        return [
            'maturity_score' => $maturityScore,
            'capacity_score' => $capacityScore,
            'urgency_score' => $urgencyScore,
            'dimension_scores' => $dimensionScores,
            'maturity_level' => $maturityBand['label'] ?? null,
            'urgency_level' => $urgencyBand['label'] ?? null,
            'recommended_modality' => $modality,
            'recommended_modality_label' => $modalityLabel,
            'review_required' => $reviewRequired,
        ];
    }

    public function allQuestionIds(): array
    {
        $ids = [];

        foreach (config('lauda360_diagnosis.dimensions', []) as $definition) {
            $ids = [...$ids, ...$definition['questions']];
        }

        return [
            ...$ids,
            ...config('lauda360_diagnosis.capacity_questions', []),
            ...config('lauda360_diagnosis.urgency_questions', []),
        ];
    }

    private function scoresFor(array $questionIds, array $answers): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $id) => isset($answers[$id]) ? (int) $answers[$id] : null,
                $questionIds
            ),
            fn ($value) => $value !== null
        ));
    }

    private function normalizedScore(array $scores): int
    {
        if ($scores === []) {
            return 0;
        }

        $average = array_sum($scores) / count($scores);

        return (int) round((($average - 1) / 4) * 100);
    }

    private function band(int $value, array $bands): ?array
    {
        foreach ($bands as $band) {
            if ($value >= $band['min'] && $value <= $band['max']) {
                return $band;
            }
        }

        return null;
    }

    private function hasCriticalGovernanceRisk(array $answers): bool
    {
        foreach (config('lauda360_diagnosis.critical_review_questions', []) as $questionId) {
            if (isset($answers[$questionId]) && (int) $answers[$questionId] === 1) {
                return true;
            }
        }

        return false;
    }
}
