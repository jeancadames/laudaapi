<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationPlan;
use Illuminate\Validation\ValidationException;

class TransformationImplementationModalityService
{
    public function __construct(
        private readonly TransformationImplementationModalityCatalog $catalog
    ) {
    }

    public function options(): array
    {
        return array_values($this->catalog->all());
    }

    public function select(
        TransformationImplementationPlan $plan,
        string $modality,
        ?int $userId = null
    ): TransformationImplementationPlan {
        $modality = trim($modality);

        if (!$this->catalog->exists($modality)) {
            throw ValidationException::withMessages([
                'selected_modality' => 'La modalidad seleccionada no es válida.',
            ]);
        }

        if (!in_array($plan->status, ['draft', 'presented'], true)) {
            throw ValidationException::withMessages([
                'selected_modality' =>
                    'La modalidad solo puede cambiarse antes de aceptar el Plan de Implementación.',
            ]);
        }

        $plan->forceFill([
            'selected_modality' => $modality,
            'selected_modality_label' => $this->catalog->label($modality),
            'updated_by_user_id' => $userId,
        ])->save();

        return $plan->refresh();
    }

    public function clearSelection(
        TransformationImplementationPlan $plan,
        ?int $userId = null
    ): TransformationImplementationPlan {
        if (!in_array($plan->status, ['draft', 'presented'], true)) {
            throw ValidationException::withMessages([
                'selected_modality' =>
                    'La modalidad solo puede cambiarse antes de aceptar el Plan de Implementación.',
            ]);
        }

        $plan->forceFill([
            'selected_modality' => null,
            'selected_modality_label' => null,
            'updated_by_user_id' => $userId,
        ])->save();

        return $plan->refresh();
    }

    public function recommendationFor(TransformationImplementationPlan $plan): ?array
    {
        if (!$plan->recommended_modality) {
            return null;
        }

        if (!$this->catalog->exists($plan->recommended_modality)) {
            return null;
        }

        return $this->catalog->get($plan->recommended_modality);
    }

    public function selectionFor(TransformationImplementationPlan $plan): ?array
    {
        if (!$plan->selected_modality) {
            return null;
        }

        if (!$this->catalog->exists($plan->selected_modality)) {
            return null;
        }

        return $this->catalog->get($plan->selected_modality);
    }
}
