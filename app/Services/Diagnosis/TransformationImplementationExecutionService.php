<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCapabilityExecution;
use App\Models\TransformationImplementationPhase;
use App\Models\TransformationImplementationPhaseCapability;
use App\Models\TransformationImplementationPhaseExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationExecutionService
{
    public function initializePhase(
        TransformationImplementationPhase $phase,
        ?int $userId = null
    ): TransformationImplementationPhaseExecution {
        $phase->loadMissing('plan', 'capabilities');

        if (!$phase->plan || $phase->plan->status !== 'accepted' || !$phase->plan->accepted_at) {
            throw ValidationException::withMessages([
                'execution' =>
                    'La ejecución solo puede inicializarse desde un Plan de Implementación aceptado.',
            ]);
        }

        if ($phase->capabilities->isEmpty()) {
            throw ValidationException::withMessages([
                'capabilities' =>
                    'La fase debe contener al menos una capacidad antes de iniciar su ejecución.',
            ]);
        }

        return DB::transaction(function () use ($phase, $userId) {
            $execution = TransformationImplementationPhaseExecution::query()->firstOrCreate(
                [
                    'transformation_implementation_phase_id' => $phase->id,
                ],
                [
                    'status' => TransformationImplementationPhaseExecution::STATUS_PENDING,
                    'progress_percentage' => 0,
                    'created_by_user_id' => $userId,
                    'updated_by_user_id' => $userId,
                ]
            );

            foreach ($phase->capabilities as $capability) {
                TransformationImplementationCapabilityExecution::query()->firstOrCreate(
                    [
                        'transformation_implementation_phase_capability_id' => $capability->id,
                    ],
                    [
                        'status' => TransformationImplementationCapabilityExecution::STATUS_PENDING,
                        'progress_percentage' => 0,
                        'created_by_user_id' => $userId,
                        'updated_by_user_id' => $userId,
                    ]
                );
            }

            return $execution->refresh();
        });
    }

    public function startCapability(
        TransformationImplementationPhaseCapability $capability,
        ?int $assignedUserId = null,
        ?int $userId = null
    ): TransformationImplementationCapabilityExecution {
        $execution = $this->capabilityExecution($capability);

        if ($execution->status === TransformationImplementationCapabilityExecution::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Una capacidad completada no puede reiniciarse.',
            ]);
        }

        $execution->forceFill([
            'status' => TransformationImplementationCapabilityExecution::STATUS_IN_PROGRESS,
            'assigned_user_id' => $assignedUserId ?? $execution->assigned_user_id,
            'started_at' => $execution->started_at ?? now(),
            'blocked_at' => null,
            'blocking_reason' => null,
            'updated_by_user_id' => $userId,
        ])->save();

        $this->refreshPhase($capability->phase);

        return $execution->refresh();
    }

    public function updateCapabilityProgress(
        TransformationImplementationPhaseCapability $capability,
        float $progressPercentage,
        ?int $userId = null,
        ?array $evidenceSnapshot = null,
        ?string $internalNotes = null
    ): TransformationImplementationCapabilityExecution {
        if ($progressPercentage < 0 || $progressPercentage > 100) {
            throw ValidationException::withMessages([
                'progress_percentage' => 'El avance debe estar entre 0 y 100.',
            ]);
        }

        $execution = $this->capabilityExecution($capability);

        if (in_array(
            $execution->status,
            [
                TransformationImplementationCapabilityExecution::STATUS_COMPLETED,
                TransformationImplementationCapabilityExecution::STATUS_CANCELLED,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' => 'No se puede modificar el avance de una capacidad cerrada.',
            ]);
        }

        $execution->forceFill([
            'status' => $progressPercentage > 0
                ? TransformationImplementationCapabilityExecution::STATUS_IN_PROGRESS
                : $execution->status,
            'progress_percentage' => round($progressPercentage, 2),
            'started_at' => $progressPercentage > 0
                ? ($execution->started_at ?? now())
                : $execution->started_at,
            'evidence_snapshot' => $evidenceSnapshot ?? $execution->evidence_snapshot,
            'internal_notes' => $internalNotes !== null
                ? trim($internalNotes)
                : $execution->internal_notes,
            'updated_by_user_id' => $userId,
        ])->save();

        $this->refreshPhase($capability->phase);

        return $execution->refresh();
    }

    public function blockCapability(
        TransformationImplementationPhaseCapability $capability,
        string $reason,
        ?int $userId = null
    ): TransformationImplementationCapabilityExecution {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'blocking_reason' => 'Debe indicar la razón del bloqueo.',
            ]);
        }

        $execution = $this->capabilityExecution($capability);

        if ($execution->status === TransformationImplementationCapabilityExecution::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => 'Una capacidad completada no puede bloquearse.',
            ]);
        }

        $execution->forceFill([
            'status' => TransformationImplementationCapabilityExecution::STATUS_BLOCKED,
            'blocked_at' => now(),
            'blocking_reason' => $reason,
            'updated_by_user_id' => $userId,
        ])->save();

        $this->refreshPhase($capability->phase);

        return $execution->refresh();
    }

    public function unblockCapability(
        TransformationImplementationPhaseCapability $capability,
        ?int $userId = null
    ): TransformationImplementationCapabilityExecution {
        $execution = $this->capabilityExecution($capability);

        if ($execution->status !== TransformationImplementationCapabilityExecution::STATUS_BLOCKED) {
            throw ValidationException::withMessages([
                'status' => 'Solo una capacidad bloqueada puede desbloquearse.',
            ]);
        }

        $execution->forceFill([
            'status' => $execution->progress_percentage > 0
                ? TransformationImplementationCapabilityExecution::STATUS_IN_PROGRESS
                : TransformationImplementationCapabilityExecution::STATUS_PENDING,
            'blocked_at' => null,
            'blocking_reason' => null,
            'updated_by_user_id' => $userId,
        ])->save();

        $this->refreshPhase($capability->phase);

        return $execution->refresh();
    }

    public function completeCapability(
        TransformationImplementationPhaseCapability $capability,
        ?int $userId = null,
        ?array $evidenceSnapshot = null
    ): TransformationImplementationCapabilityExecution {
        $execution = $this->capabilityExecution($capability);

        if ($execution->status === TransformationImplementationCapabilityExecution::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'Una capacidad cancelada no puede completarse.',
            ]);
        }

        $execution->forceFill([
            'status' => TransformationImplementationCapabilityExecution::STATUS_COMPLETED,
            'progress_percentage' => 100,
            'started_at' => $execution->started_at ?? now(),
            'blocked_at' => null,
            'blocking_reason' => null,
            'completed_at' => $execution->completed_at ?? now(),
            'evidence_snapshot' => $evidenceSnapshot ?? $execution->evidence_snapshot,
            'updated_by_user_id' => $userId,
        ])->save();

        $this->refreshPhase($capability->phase);

        return $execution->refresh();
    }

    public function refreshPhase(
        TransformationImplementationPhase $phase
    ): TransformationImplementationPhaseExecution {
        $phase->loadMissing('capabilities');

        $execution = TransformationImplementationPhaseExecution::query()
            ->where('transformation_implementation_phase_id', $phase->id)
            ->first();

        if (!$execution) {
            throw ValidationException::withMessages([
                'execution' => 'La ejecución de la fase todavía no ha sido inicializada.',
            ]);
        }

        $capabilityExecutions = TransformationImplementationCapabilityExecution::query()
            ->whereIn(
                'transformation_implementation_phase_capability_id',
                $phase->capabilities->pluck('id')
            )
            ->get();

        if ($capabilityExecutions->isEmpty()) {
            $execution->forceFill([
                'status' => TransformationImplementationPhaseExecution::STATUS_PENDING,
                'progress_percentage' => 0,
            ])->save();

            return $execution->refresh();
        }

        $progress = round(
            (float) $capabilityExecutions->avg(
                fn (TransformationImplementationCapabilityExecution $item) =>
                    (float) $item->progress_percentage
            ),
            2
        );

        $allCompleted = $capabilityExecutions->every(
            fn (TransformationImplementationCapabilityExecution $item) =>
                $item->status === TransformationImplementationCapabilityExecution::STATUS_COMPLETED
        );

        $hasBlocked = $capabilityExecutions->contains(
            fn (TransformationImplementationCapabilityExecution $item) =>
                $item->status === TransformationImplementationCapabilityExecution::STATUS_BLOCKED
        );

        $hasStarted = $capabilityExecutions->contains(
            fn (TransformationImplementationCapabilityExecution $item) =>
                in_array(
                    $item->status,
                    [
                        TransformationImplementationCapabilityExecution::STATUS_IN_PROGRESS,
                        TransformationImplementationCapabilityExecution::STATUS_COMPLETED,
                    ],
                    true
                )
        );

        $status = TransformationImplementationPhaseExecution::STATUS_PENDING;

        if ($allCompleted) {
            $status = TransformationImplementationPhaseExecution::STATUS_COMPLETED;
        } elseif ($hasBlocked) {
            $status = TransformationImplementationPhaseExecution::STATUS_BLOCKED;
        } elseif ($hasStarted) {
            $status = TransformationImplementationPhaseExecution::STATUS_IN_PROGRESS;
        }

        $execution->forceFill([
            'status' => $status,
            'progress_percentage' => $progress,
            'started_at' => $hasStarted || $allCompleted
                ? ($execution->started_at ?? now())
                : $execution->started_at,
            'blocked_at' => $hasBlocked
                ? ($execution->blocked_at ?? now())
                : null,
            'completed_at' => $allCompleted
                ? ($execution->completed_at ?? now())
                : null,
        ])->save();

        return $execution->refresh();
    }

    public function assertPhaseCompleted(
        TransformationImplementationPhase $phase
    ): TransformationImplementationPhaseExecution {
        $execution = $this->refreshPhase($phase);

        if ($execution->status !== TransformationImplementationPhaseExecution::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'execution' =>
                    'La fase solo puede cerrarse cuando todas sus capacidades estén completadas.',
            ]);
        }

        return $execution;
    }

    private function capabilityExecution(
        TransformationImplementationPhaseCapability $capability
    ): TransformationImplementationCapabilityExecution {
        $execution = TransformationImplementationCapabilityExecution::query()
            ->where('transformation_implementation_phase_capability_id', $capability->id)
            ->first();

        if (!$execution) {
            throw ValidationException::withMessages([
                'execution' =>
                    'La ejecución de la capacidad todavía no ha sido inicializada.',
            ]);
        }

        return $execution;
    }
}
