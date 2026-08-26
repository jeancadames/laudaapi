<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationCapabilityExecution;
use App\Models\TransformationImplementationCapabilityGoLive;
use App\Models\TransformationImplementationPhaseCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransformationImplementationGoLiveService
{
    public function createAttempt(
        TransformationImplementationPhaseCapability $capability,
        ?int $userId = null
    ): TransformationImplementationCapabilityGoLive {
        $capability->loadMissing('phase.plan', 'execution');

        $plan = $capability->phase?->plan;
        $execution = $capability->execution;

        if (!$plan || $plan->status !== 'accepted' || !$plan->accepted_at) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'El Go-Live solo puede prepararse desde un Plan de Implementación aceptado.',
            ]);
        }

        if (!$execution ||
            $execution->status !== TransformationImplementationCapabilityExecution::STATUS_COMPLETED ||
            (float) $execution->progress_percentage < 100
        ) {
            throw ValidationException::withMessages([
                'execution' =>
                    'La capacidad debe estar completada al 100% antes de preparar su Go-Live.',
            ]);
        }

        $openAttempt = TransformationImplementationCapabilityGoLive::query()
            ->where('transformation_implementation_phase_capability_id', $capability->id)
            ->whereIn('status', [
                TransformationImplementationCapabilityGoLive::STATUS_DRAFT,
                TransformationImplementationCapabilityGoLive::STATUS_READY,
                TransformationImplementationCapabilityGoLive::STATUS_SCHEDULED,
                TransformationImplementationCapabilityGoLive::STATUS_LIVE,
            ])
            ->first();

        if ($openAttempt) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'La capacidad ya tiene un intento de Go-Live abierto o activo.',
            ]);
        }

        $attempt = ((int) TransformationImplementationCapabilityGoLive::query()
            ->where('transformation_implementation_phase_capability_id', $capability->id)
            ->max('attempt')) + 1;

        return TransformationImplementationCapabilityGoLive::query()->create([
            'transformation_implementation_phase_capability_id' => $capability->id,
            'transformation_implementation_capability_execution_id' => $execution->id,
            'attempt' => $attempt,
            'status' => TransformationImplementationCapabilityGoLive::STATUS_DRAFT,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ]);
    }

    public function markReady(
        TransformationImplementationCapabilityGoLive $goLive,
        array $readinessSnapshot,
        ?int $userId = null,
        ?array $evidenceSnapshot = null
    ): TransformationImplementationCapabilityGoLive {
        $this->assertExecutionStillCompleted($goLive);

        if ($readinessSnapshot === []) {
            throw ValidationException::withMessages([
                'readiness_snapshot' =>
                    'Debe registrar la validación de readiness antes del Go-Live.',
            ]);
        }

        if (!in_array(
            $goLive->status,
            [
                TransformationImplementationCapabilityGoLive::STATUS_DRAFT,
                TransformationImplementationCapabilityGoLive::STATUS_READY,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' =>
                    'Solo un intento draft/ready puede marcarse como listo.',
            ]);
        }

        $goLive->forceFill([
            'status' => TransformationImplementationCapabilityGoLive::STATUS_READY,
            'readiness_snapshot' => $readinessSnapshot,
            'evidence_snapshot' => $evidenceSnapshot ?? $goLive->evidence_snapshot,
            'ready_at' => $goLive->ready_at ?? now(),
            'updated_by_user_id' => $userId,
        ])->save();

        return $goLive->refresh();
    }

    public function schedule(
        TransformationImplementationCapabilityGoLive $goLive,
        mixed $scheduledAt,
        ?int $userId = null
    ): TransformationImplementationCapabilityGoLive {
        $this->assertExecutionStillCompleted($goLive);

        if ($goLive->status !== TransformationImplementationCapabilityGoLive::STATUS_READY) {
            throw ValidationException::withMessages([
                'status' =>
                    'El intento debe estar ready antes de programar el Go-Live.',
            ]);
        }

        $goLive->forceFill([
            'status' => TransformationImplementationCapabilityGoLive::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
            'updated_by_user_id' => $userId,
        ])->save();

        return $goLive->refresh();
    }

    public function goLive(
        TransformationImplementationCapabilityGoLive $goLive,
        ?int $userId = null,
        ?array $evidenceSnapshot = null
    ): TransformationImplementationCapabilityGoLive {
        $this->assertExecutionStillCompleted($goLive);
        $this->assertPlanStillAccepted($goLive);

        if (!in_array(
            $goLive->status,
            [
                TransformationImplementationCapabilityGoLive::STATUS_READY,
                TransformationImplementationCapabilityGoLive::STATUS_SCHEDULED,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' =>
                    'El Go-Live solo puede ejecutarse desde ready o scheduled.',
            ]);
        }

        if (!$goLive->readiness_snapshot) {
            throw ValidationException::withMessages([
                'readiness_snapshot' =>
                    'No se puede ejecutar el Go-Live sin readiness validado.',
            ]);
        }

        return DB::transaction(function () use ($goLive, $userId, $evidenceSnapshot) {
            $alreadyLive = TransformationImplementationCapabilityGoLive::query()
                ->where(
                    'transformation_implementation_phase_capability_id',
                    $goLive->transformation_implementation_phase_capability_id
                )
                ->where('status', TransformationImplementationCapabilityGoLive::STATUS_LIVE)
                ->whereKeyNot($goLive->id)
                ->exists();

            if ($alreadyLive) {
                throw ValidationException::withMessages([
                    'go_live' =>
                        'La capacidad ya tiene otro Go-Live activo.',
                ]);
            }

            $goLive->forceFill([
                'status' => TransformationImplementationCapabilityGoLive::STATUS_LIVE,
                'went_live_at' => $goLive->went_live_at ?? now(),
                'went_live_by_user_id' => $userId,
                'evidence_snapshot' => $evidenceSnapshot ?? $goLive->evidence_snapshot,
                'updated_by_user_id' => $userId,
            ])->save();

            return $goLive->refresh();
        });
    }

    public function rollback(
        TransformationImplementationCapabilityGoLive $goLive,
        string $reason,
        ?int $userId = null,
        ?array $evidenceSnapshot = null
    ): TransformationImplementationCapabilityGoLive {
        $reason = trim($reason);

        if ($goLive->status !== TransformationImplementationCapabilityGoLive::STATUS_LIVE) {
            throw ValidationException::withMessages([
                'status' =>
                    'Solo un Go-Live activo puede revertirse.',
            ]);
        }

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rollback_reason' =>
                    'Debe indicar la razón del rollback.',
            ]);
        }

        $goLive->forceFill([
            'status' => TransformationImplementationCapabilityGoLive::STATUS_ROLLED_BACK,
            'rolled_back_at' => now(),
            'rollback_reason' => $reason,
            'evidence_snapshot' => $evidenceSnapshot ?? $goLive->evidence_snapshot,
            'updated_by_user_id' => $userId,
        ])->save();

        return $goLive->refresh();
    }

    public function cancel(
        TransformationImplementationCapabilityGoLive $goLive,
        ?int $userId = null
    ): TransformationImplementationCapabilityGoLive {
        if (in_array(
            $goLive->status,
            [
                TransformationImplementationCapabilityGoLive::STATUS_LIVE,
                TransformationImplementationCapabilityGoLive::STATUS_ROLLED_BACK,
                TransformationImplementationCapabilityGoLive::STATUS_CANCELLED,
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'status' =>
                    'Este intento de Go-Live ya no puede cancelarse.',
            ]);
        }

        $goLive->forceFill([
            'status' => TransformationImplementationCapabilityGoLive::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'updated_by_user_id' => $userId,
        ])->save();

        return $goLive->refresh();
    }

    public function isLive(
        TransformationImplementationPhaseCapability $capability
    ): bool {
        return TransformationImplementationCapabilityGoLive::query()
            ->where('transformation_implementation_phase_capability_id', $capability->id)
            ->where('status', TransformationImplementationCapabilityGoLive::STATUS_LIVE)
            ->whereNotNull('went_live_at')
            ->exists();
    }

    private function assertExecutionStillCompleted(
        TransformationImplementationCapabilityGoLive $goLive
    ): void {
        $goLive->loadMissing('execution');

        if (!$goLive->execution ||
            $goLive->execution->status !== TransformationImplementationCapabilityExecution::STATUS_COMPLETED ||
            (float) $goLive->execution->progress_percentage < 100
        ) {
            throw ValidationException::withMessages([
                'execution' =>
                    'La capacidad debe permanecer completada al 100% para continuar con el Go-Live.',
            ]);
        }
    }

    private function assertPlanStillAccepted(
        TransformationImplementationCapabilityGoLive $goLive
    ): void {
        $goLive->loadMissing('capability.phase.plan');

        $plan = $goLive->capability?->phase?->plan;

        if (!$plan || $plan->status !== 'accepted' || !$plan->accepted_at) {
            throw ValidationException::withMessages([
                'go_live' =>
                    'El Plan de Implementación debe permanecer aceptado para ejecutar el Go-Live.',
            ]);
        }
    }
}
