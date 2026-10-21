<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationRequest;
use App\Models\TransformationImplementationRequestEvent;
use Illuminate\Validation\ValidationException;

/**
 * BI17: protects source evidence after functional closure.
 *
 * This guard must run inside the mutation transaction,
 * before taking the intake-session lock.
 */
final class DataTransformationBiPostClosureMutationGuard
{
    private const CLOSURE_EVENT =
        'definition_functionally_finalized_by_lauda';

    public function lockRequestAndAssertOpen(
        TransformationImplementationRequest $request
    ): void {
        $lockedRequest =
            TransformationImplementationRequest::query()
                ->whereKey((int) $request->getKey())
                ->lockForUpdate()
                ->first();

        if (
            $lockedRequest === null
            || (int) $lockedRequest->company_id
                !== (int) $request->company_id
            || (string) $lockedRequest->capability_key
                !== 'data_transformation_bi'
        ) {
            throw ValidationException::withMessages([
                'implementation_request' => [
                    'La solicitud de Datos BI no es válida.',
                ],
            ]);
        }

        $closed =
            TransformationImplementationRequestEvent::query()
                ->where(
                    'transformation_implementation_request_id',
                    (int) $lockedRequest->getKey()
                )
                ->where(
                    'event_type',
                    self::CLOSURE_EVENT
                )
                ->exists();

        if ($closed) {
            throw ValidationException::withMessages([
                'source_workspace' => [
                    'La entrega técnica ya fue utilizada en un cierre funcional y no puede modificarse.',
                ],
            ]);
        }
    }
}
