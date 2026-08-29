<?php

namespace App\Services\Diagnosis;

class TransformationImplementationCommercialCalculator
{
    public function quotePlan(
        array $phases,
        array $matrix,
        array $modalityKeys
    ): array {
        $version = trim(
            (string) ($matrix['version'] ?? '')
        );

        $currency = strtoupper(
            trim(
                (string) ($matrix['currency'] ?? 'DOP')
            )
        );

        $results = [];
        $allMissing = [];

        if ($phases === []) {
            $allMissing[] = 'plan.phases';
        }

        foreach ($modalityKeys as $modality) {
            $scenario = [
                'modality' => $modality,
                'complete' => true,
                'price_amount' => 0.0,
                'currency' => $currency,
                'duration_days' => 0,
                'phases' => [],
                'missing' => [],
            ];

            foreach ($phases as $phase) {
                $quote = $this->quotePhase(
                    $phase,
                    $modality,
                    $matrix
                );

                $scenario['phases'][] = $quote;

                if (! $quote['complete']) {
                    $scenario['complete'] = false;
                }

                $scenario['price_amount'] +=
                    $quote['price_amount'];

                $scenario['duration_days'] +=
                    $quote['duration_days'];

                $scenario['missing'] = array_merge(
                    $scenario['missing'],
                    $quote['missing']
                );
            }

            $scenario['price_amount'] = round(
                $scenario['price_amount'],
                2
            );

            $scenario['missing'] = array_values(
                array_unique(
                    $scenario['missing']
                )
            );

            if ($scenario['missing'] !== []) {
                $scenario['complete'] = false;
            }

            $allMissing = array_merge(
                $allMissing,
                $scenario['missing']
            );

            $results[$modality] = $scenario;
        }

        $allMissing = array_values(
            array_unique($allMissing)
        );

        return [
            'ready' =>
                $phases !== []
                && $results !== []
                && $allMissing === []
                && collect($results)->every(
                    fn (array $scenario): bool =>
                        $scenario['complete'] === true
                ),

            'version' => $version,
            'currency' => $currency,
            'modalities' => $results,
            'missing' => $allMissing,
        ];
    }

    public function quotePhase(
        array $phase,
        string $modality,
        array $matrix
    ): array {
        $phaseId =
            $phase['id']
            ?? $phase['sequence']
            ?? 'unknown';

        $phaseName =
            trim(
                (string) (
                    $phase['name']
                    ?? "Fase {$phaseId}"
                )
            );

        $initiatives =
            array_values(
                $phase['initiatives']
                ?? []
            );

        $professionalCapabilities =
            array_values(
                $phase['professional_capabilities']
                ?? []
            );

        $price = 0.0;
        $durationDays = 0;
        $missing = [];
        $breakdown = [];

        if ($initiatives === []) {
            $missing[] =
                "phase.{$phaseId}.initiatives";
        }

        foreach ($initiatives as $index => $initiative) {
            $initiativeId =
                trim(
                    (string) (
                        $initiative['id']
                        ?? 'initiative-'.($index + 1)
                    )
                );

            $effort =
                strtolower(
                    trim(
                        (string) (
                            $initiative['effort']
                            ?? ''
                        )
                    )
                );

            if ($effort === '') {
                $missing[] =
                    "phase.{$phaseId}.initiative."
                    ."{$initiativeId}.effort";

                continue;
            }

            $definition =
                data_get(
                    $matrix,
                    "modalities.{$modality}."
                    ."initiative_effort.{$effort}"
                );

            if (! is_array($definition)) {
                $missing[] =
                    "modalities.{$modality}."
                    ."initiative_effort.{$effort}";

                continue;
            }

            $component = $this->component(
                $definition,
                "modalities.{$modality}."
                ."initiative_effort.{$effort}",
                $missing
            );

            if (! $component['complete']) {
                continue;
            }

            $price += $component['price_amount'];
            $durationDays += $component['duration_days'];

            $breakdown[] = [
                'type' => 'initiative',
                'initiative_id' => $initiativeId,
                'effort' => $effort,
                'price_amount' =>
                    $component['price_amount'],
                'duration_days' =>
                    $component['duration_days'],
            ];
        }

        foreach (
            $professionalCapabilities
            as $capability
        ) {
            $key =
                trim(
                    (string) (
                        $capability['key']
                        ?? ''
                    )
                );

            if ($key === '') {
                $missing[] =
                    "phase.{$phaseId}."
                    ."professional_capability.key";

                continue;
            }

            $path =
                "modalities.{$modality}."
                ."professional_capabilities.{$key}";

            $definition =
                data_get(
                    $matrix,
                    $path
                );

            if (! is_array($definition)) {
                $missing[] = $path;

                continue;
            }

            $component = $this->component(
                $definition,
                $path,
                $missing
            );

            if (! $component['complete']) {
                continue;
            }

            $price += $component['price_amount'];
            $durationDays += $component['duration_days'];

            $breakdown[] = [
                'type' =>
                    'professional_capability',

                'capability_key' =>
                    $key,

                'price_amount' =>
                    $component['price_amount'],

                'duration_days' =>
                    $component['duration_days'],
            ];
        }

        $missing = array_values(
            array_unique($missing)
        );

        return [
            'phase_id' => $phaseId,
            'phase_sequence' =>
                $phase['sequence'] ?? null,
            'phase_name' => $phaseName,
            'complete' => $missing === [],
            'price_amount' => round($price, 2),
            'currency' => strtoupper(
                trim(
                    (string) (
                        $matrix['currency']
                        ?? 'DOP'
                    )
                )
            ),
            'duration_days' => $durationDays,
            'breakdown' => $breakdown,
            'missing' => $missing,
        ];
    }

    private function component(
        array $definition,
        string $path,
        array &$missing
    ): array {
        $price =
            $definition['price_amount']
            ?? null;

        $duration =
            $definition['duration_days']
            ?? null;

        $complete = true;

        if (
            ! is_numeric($price)
            || (float) $price < 0
        ) {
            $missing[] =
                "{$path}.price_amount";

            $complete = false;
        }

        if (
            ! is_numeric($duration)
            || (int) $duration < 1
        ) {
            $missing[] =
                "{$path}.duration_days";

            $complete = false;
        }

        return [
            'complete' => $complete,

            'price_amount' =>
                $complete
                    ? round((float) $price, 2)
                    : 0.0,

            'duration_days' =>
                $complete
                    ? (int) $duration
                    : 0,
        ];
    }
}
