<?php

namespace App\Services\Diagnosis;

use Illuminate\Validation\ValidationException;

/** Content rules for the existing initiative/dependency/phase JSON contract. */
class DiagnosisRoadmapDependencies
{
    public const HORIZONS = [
        1 => '0-30 días',
        2 => '31-90 días',
        3 => '91-180 días',
        4 => '181-365 días',
    ];

    /** Only the generator arranges content. Publication validates without rewriting it. */
    public function sequence(array $initiatives): array
    {
        $pending = $this->index($initiatives);
        $ordered = [];

        while ($pending !== []) {
            $ready = [];
            foreach ($pending as $id => $initiative) {
                if (array_diff($initiative['dependencies'], array_keys($ordered)) !== []) {
                    continue;
                }
                foreach ($initiative['dependencies'] as $dependency) {
                    $initiative['phase'] = max($initiative['phase'], $ordered[$dependency]['phase']);
                }
                $ready[$id] = $initiative;
            }
            if ($ready === []) {
                $this->reject('Se detectó un ciclo de dependencias. Iniciativas sin resolver: '.implode(', ', array_keys($pending)).'.');
            }

            // Dependencies prevail. Existing priority and impact scales only
            // break ties between initiatives whose prerequisites are satisfied.
            uasort($ready, fn (array $a, array $b): int =>
                ($a['phase'] <=> $b['phase'])
                ?: ($this->priorityWeight($a['priority']) <=> $this->priorityWeight($b['priority']))
                ?: ($this->impactWeight($a['impact']) <=> $this->impactWeight($b['impact']))
            );
            $id = array_key_first($ready);
            $initiative = $ready[$id];
            $initiative['horizon'] = self::HORIZONS[$initiative['phase']];
            $initiative['sequence'] = count($ordered) + 1;
            $ordered[$id] = $initiative;
            unset($pending[$id]);
        }

        return array_values($ordered);
    }

    public function assertValid(mixed $payload): void
    {
        if (! is_array($payload)) {
            $this->reject('El contenido del Roadmap debe ser un objeto estructurado.');
        }
        $initiatives = $payload['initiatives'] ?? null;
        if (! is_array($initiatives)) {
            $this->reject('Las iniciativas deben ser una lista no vacía.');
        }
        $indexed = $this->index($initiatives);
        $this->sequence($initiatives); // Also rejects cycles; never stores the arranged copy.
        $previousPhase = 0;
        foreach ($initiatives as $position => $initiative) {
            $id = $initiative['id'];
            if (($initiative['sequence'] ?? null) !== $position + 1) {
                $this->reject("La secuencia de {$id} debe coincidir con su posición y ser consecutiva.");
            }
            if ($initiative['phase'] < $previousPhase) {
                $this->reject("El orden de fases retrocede en {$id}.");
            }
            $previousPhase = $initiative['phase'];
            if (($initiative['horizon'] ?? null) !== self::HORIZONS[$initiative['phase']]) {
                $this->reject("El horizonte de {$id} no corresponde a su fase.");
            }
            foreach ($initiative['dependencies'] as $dependency) {
                if (($indexed[$dependency]['sequence'] ?? PHP_INT_MAX) >= $initiative['sequence']) {
                    $this->reject("{$id} debe aparecer después de su prerrequisito {$dependency}.");
                }
                if ($indexed[$dependency]['phase'] > $initiative['phase']) {
                    $this->reject("La fase de {$id} precede a la de su prerrequisito {$dependency}.");
                }
            }
        }

        $phases = $payload['phases'] ?? null;
        if (! is_array($phases) || ! array_is_list($phases) || count($phases) !== 4) {
            $this->reject('El Roadmap debe conservar sus cuatro fases.');
        }
        foreach ($phases as $position => $phase) {
            $number = $position + 1;
            $expected = array_values(array_map(
                fn (array $item): string => $item['id'],
                array_filter($initiatives, fn (array $item): bool => $item['phase'] === $number)
            ));
            if (! is_array($phase)
                || ($phase['number'] ?? null) !== $number
                || ($phase['horizon'] ?? null) !== self::HORIZONS[$number]
                || ($phase['initiative_ids'] ?? null) !== $expected) {
                $this->reject("Las referencias u orden de la fase {$number} no coinciden con sus iniciativas.");
            }
        }
    }

    private function index(array $initiatives): array
    {
        if ($initiatives === [] || ! array_is_list($initiatives)) {
            $this->reject('El Roadmap requiere una lista no vacía de iniciativas.');
        }
        $indexed = [];
        foreach ($initiatives as $initiative) {
            $id = is_array($initiative) ? ($initiative['id'] ?? null) : null;
            if (! is_string($id) || trim($id) === '' || $id !== trim($id)) {
                $this->reject('Cada iniciativa requiere un ID válido.');
            }
            if (array_key_exists($id, $indexed)) {
                $this->reject("El ID de iniciativa {$id} está duplicado.");
            }
            $dependencies = $initiative['dependencies'] ?? null;
            if (! is_array($dependencies) || ! array_is_list($dependencies)) {
                $this->reject("Las dependencias de {$id} deben ser una lista de IDs.");
            }
            $seen = [];
            foreach ($dependencies as $dependency) {
                if (! is_string($dependency) || trim($dependency) === '' || $dependency !== trim($dependency)) {
                    $this->reject("Hay una referencia inválida en {$id}.");
                }
                if ($dependency === $id) {
                    $this->reject("La iniciativa {$id} no puede depender de sí misma.");
                }
                if (isset($seen[$dependency])) {
                    $this->reject("La referencia {$dependency} está duplicada en {$id}.");
                }
                $seen[$dependency] = true;
            }
            if (! is_int($initiative['phase'] ?? null) || ! isset(self::HORIZONS[$initiative['phase']])) {
                $this->reject("La fase de {$id} debe ser un entero entre 1 y 4.");
            }
            if (! in_array($initiative['priority'] ?? null, ['critical', 'high', 'medium', 'sustain'], true)
                || ! in_array($initiative['impact'] ?? null, ['high', 'medium', 'low'], true)) {
                $this->reject("La prioridad o el impacto de {$id} no pertenece a las escalas existentes.");
            }
            $indexed[$id] = $initiative;
        }
        foreach ($indexed as $id => $initiative) {
            foreach ($initiative['dependencies'] as $dependency) {
                if (! array_key_exists($dependency, $indexed)) {
                    $this->reject("El prerrequisito {$dependency} de {$id} no existe en el Roadmap.");
                }
            }
        }

        return $indexed;
    }

    private function priorityWeight(string $priority): int
    {
        return ['critical' => 1, 'high' => 2, 'medium' => 3, 'sustain' => 4][$priority];
    }

    private function impactWeight(string $impact): int
    {
        return ['high' => 1, 'medium' => 2, 'low' => 3][$impact];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['roadmap' => [$message]]);
    }
}
