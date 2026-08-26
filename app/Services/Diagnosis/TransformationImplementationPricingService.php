<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPhaseEstimate;
use Illuminate\Validation\ValidationException;

class TransformationImplementationPricingService
{
    private const DURATION_UNITS = ['days', 'weeks', 'months'];

    public function __construct(
        private readonly TransformationImplementationModalityCatalog $catalog
    ) {
    }

    public function upsertEstimate(
        TransformationImplementationPhase $phase,
        array $data,
        ?int $userId = null
    ): TransformationImplementationPhaseEstimate {
        $phase->loadMissing('plan');

        if (!$phase->plan) {
            throw ValidationException::withMessages([
                'phase' => 'La fase no pertenece a un Plan de Implementación válido.',
            ]);
        }

        if (!in_array($phase->plan->status, ['draft', 'presented'], true)) {
            throw ValidationException::withMessages([
                'estimate' =>
                    'Precio y tiempo solo pueden modificarse antes de aceptar el Plan de Implementación.',
            ]);
        }

        $modality = trim((string) ($data['modality'] ?? ''));

        if (!$this->catalog->exists($modality)) {
            throw ValidationException::withMessages([
                'modality' => 'La modalidad de la estimación no es válida.',
            ]);
        }

        $price = $data['price_amount'] ?? null;

        if (!is_numeric($price) || (float) $price < 0) {
            throw ValidationException::withMessages([
                'price_amount' => 'El precio debe ser un monto mayor o igual a cero.',
            ]);
        }

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'DOP')));

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw ValidationException::withMessages([
                'currency' => 'La moneda debe utilizar un código ISO de tres letras.',
            ]);
        }

        $durationValue = (int) ($data['estimated_duration_value'] ?? 0);
        $durationUnit = trim((string) ($data['estimated_duration_unit'] ?? 'weeks'));

        if ($durationValue < 1) {
            throw ValidationException::withMessages([
                'estimated_duration_value' => 'El tiempo estimado debe ser mayor o igual a 1.',
            ]);
        }

        if (!in_array($durationUnit, self::DURATION_UNITS, true)) {
            throw ValidationException::withMessages([
                'estimated_duration_unit' => 'La unidad de tiempo estimado no es válida.',
            ]);
        }

        $definition = $this->catalog->get($modality);

        return TransformationImplementationPhaseEstimate::query()->updateOrCreate(
            [
                'transformation_implementation_phase_id' => $phase->id,
                'modality' => $modality,
            ],
            [
                'modality_label' => $definition['label'],
                'price_amount' => round((float) $price, 2),
                'currency' => $currency,
                'estimated_duration_value' => $durationValue,
                'estimated_duration_unit' => $durationUnit,
                'scope_snapshot' => $data['scope_snapshot'] ?? $this->buildScopeSnapshot($phase),
                'internal_notes' => isset($data['internal_notes'])
                    ? trim((string) $data['internal_notes'])
                    : null,
                'created_by_user_id' => $this->existingCreator($phase->id, $modality, $userId),
                'updated_by_user_id' => $userId,
            ]
        );
    }

    public function forSelectedModality(
        TransformationImplementationPhase $phase
    ): ?TransformationImplementationPhaseEstimate {
        $phase->loadMissing('plan');

        $selected = $phase->plan?->selected_modality;

        if (!$selected) {
            return null;
        }

        return TransformationImplementationPhaseEstimate::query()
            ->where('transformation_implementation_phase_id', $phase->id)
            ->where('modality', $selected)
            ->first();
    }

    public function totalForSelectedModality(iterable $phases): array
    {
        $amount = 0.0;
        $currency = null;
        $durationByUnit = [
            'days' => 0,
            'weeks' => 0,
            'months' => 0,
        ];

        foreach ($phases as $phase) {
            $estimate = $this->forSelectedModality($phase);

            if (!$estimate) {
                continue;
            }

            if ($currency !== null && $currency !== $estimate->currency) {
                throw ValidationException::withMessages([
                    'currency' => 'Las fases seleccionadas deben usar la misma moneda para totalizar.',
                ]);
            }

            $currency ??= $estimate->currency;
            $amount += (float) $estimate->price_amount;
            $durationByUnit[$estimate->estimated_duration_unit] +=
                (int) $estimate->estimated_duration_value;
        }

        return [
            'price_amount' => round($amount, 2),
            'currency' => $currency,
            'estimated_duration' => array_filter(
                $durationByUnit,
                fn (int $value) => $value > 0
            ),
        ];
    }

    private function existingCreator(
        int $phaseId,
        string $modality,
        ?int $fallbackUserId
    ): ?int {
        return TransformationImplementationPhaseEstimate::query()
            ->where('transformation_implementation_phase_id', $phaseId)
            ->where('modality', $modality)
            ->value('created_by_user_id') ?? $fallbackUserId;
    }

    private function buildScopeSnapshot(
        TransformationImplementationPhase $phase
    ): array {
        $phase->loadMissing('capabilities');

        return [
            'phase_id' => $phase->id,
            'phase_sequence' => $phase->sequence,
            'phase_name' => $phase->name,
            'capabilities' => $phase->capabilities
                ->map(fn ($capability) => [
                    'capability_key' => $capability->capability_key,
                    'capability_label' => $capability->capability_label,
                ])
                ->values()
                ->all(),
        ];
    }
}
