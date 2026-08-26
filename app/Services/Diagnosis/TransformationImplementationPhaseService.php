<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationPhaseService
{
    public function createPhaseFromRoadmap(
        TransformationImplementationPlan $plan,
        array $data,
        ?int $userId = null
    ): TransformationImplementationPhase {
        $sequence = (int) ($data['sequence'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $objective = isset($data['objective']) ? trim((string) $data['objective']) : null;
        $capabilities = $data['capabilities'] ?? [];

        if ($sequence < 1) {
            throw ValidationException::withMessages([
                'sequence' => 'La fase debe tener una secuencia mayor o igual a 1.',
            ]);
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'La fase debe tener un nombre.',
            ]);
        }

        if (!is_array($capabilities) || $capabilities === []) {
            throw ValidationException::withMessages([
                'capabilities' => 'La fase debe contener al menos una capacidad del Roadmap.',
            ]);
        }

        $snapshot = $this->normalizeSnapshot($plan->source_snapshot);

        if ($snapshot === []) {
            throw ValidationException::withMessages([
                'source_snapshot' => 'El Plan de Implementación no contiene snapshot del Roadmap.',
            ]);
        }

        $normalizedCapabilities = [];

        foreach ($capabilities as $index => $capability) {
            if (!is_array($capability)) {
                throw ValidationException::withMessages([
                    "capabilities.$index" => 'La capacidad debe ser una estructura válida.',
                ]);
            }

            $key = trim((string) ($capability['capability_key'] ?? ''));
            $label = trim((string) ($capability['capability_label'] ?? ''));

            if ($key === '') {
                throw ValidationException::withMessages([
                    "capabilities.$index.capability_key" => 'La capacidad debe tener capability_key.',
                ]);
            }

            if ($label === '') {
                throw ValidationException::withMessages([
                    "capabilities.$index.capability_label" => 'La capacidad debe tener etiqueta.',
                ]);
            }

            if (!$this->snapshotContainsToken($snapshot, $key)) {
                throw ValidationException::withMessages([
                    "capabilities.$index.capability_key" =>
                        "La capacidad [$key] no pertenece al snapshot del Roadmap de este plan.",
                ]);
            }

            $normalizedCapabilities[] = [
                'sequence' => max(1, (int) ($capability['sequence'] ?? ($index + 1))),
                'capability_key' => $key,
                'capability_label' => $label,
                'capability_summary' => isset($capability['capability_summary'])
                    ? trim((string) $capability['capability_summary'])
                    : null,
                'source_snapshot' => $capability['source_snapshot'] ?? [
                    'capability_key' => $key,
                    'capability_label' => $label,
                ],
            ];
        }

        $duplicates = collect($normalizedCapabilities)
            ->groupBy('capability_key')
            ->filter(fn ($items) => $items->count() > 1)
            ->keys();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'capabilities' => 'Una misma capacidad no puede repetirse dentro de la fase: '.$duplicates->implode(', '),
            ]);
        }

        return DB::transaction(function () use (
            $plan,
            $sequence,
            $name,
            $objective,
            $normalizedCapabilities,
            $userId
        ) {
            $phase = TransformationImplementationPhase::query()->create([
                'transformation_implementation_plan_id' => $plan->id,
                'sequence' => $sequence,
                'name' => $name,
                'objective' => $objective ?: null,
                'source_snapshot' => [
                    'diagnosis_assessment_id' => $plan->diagnosis_assessment_id,
                    'diagnosis_detailed_roadmap_id' => $plan->diagnosis_detailed_roadmap_id,
                    'plan_version' => $plan->version,
                ],
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ]);

            foreach ($normalizedCapabilities as $capability) {
                $phase->capabilities()->create($capability);
            }

            return $phase->load('capabilities');
        });
    }

    private function normalizeSnapshot(mixed $snapshot): array
    {
        if (is_array($snapshot)) {
            return $snapshot;
        }

        if (is_string($snapshot) && trim($snapshot) !== '') {
            $decoded = json_decode($snapshot, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function snapshotContainsToken(mixed $node, string $needle): bool
    {
        $needle = mb_strtolower(trim($needle));

        if ($needle === '') {
            return false;
        }

        if (is_array($node)) {
            foreach ($node as $key => $value) {
                if (is_string($key) && mb_strtolower(trim($key)) === $needle) {
                    return true;
                }

                if ($this->snapshotContainsToken($value, $needle)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($node) || is_numeric($node)) {
            return mb_strtolower(trim((string) $node)) === $needle;
        }

        return false;
    }
}
