<?php

namespace App\Services\Diagnosis;

use App\Models\TransformationImplementationRequest;
use Illuminate\Validation\ValidationException;

/**
 * Server-owned lifecycle gate for the operational Data BI workspace.
 *
 * Recommendation alone never opens the workspace.
 * Request existence may make the process visible, but source delivery
 * and intake operations become available only after the functional
 * Definition has been explicitly agreed.
 *
 * state() provides the Tenant-facing projection.
 * assertCanManage() is the shared operational guard for both
 * Tenant Admin and LAUDA Admin.
 *
 * Browser visibility never grants operational access.
 */
final class DataTransformationBiTenantSourceWorkspaceGate
{
    /**
     * @return array{
     *     visible:bool,
     *     can_manage:bool,
     *     state:string,
     *     message:string|null
     * }
     */
    public function state(
        ?string $requestStatus
    ): array {
        $status =
            $requestStatus !== null
                ? trim($requestStatus)
                : null;

        if (
            $status === null
            || $status === ''
        ) {
            return [
                'visible' => false,
                'can_manage' => false,
                'state' => 'not_requested',
                'message' => null,
            ];
        }

        if (
            $status
            === TransformationImplementationRequestContract::STATUS_CANCELLED
        ) {
            return [
                'visible' => false,
                'can_manage' => false,
                'state' => 'cancelled',
                'message' => null,
            ];
        }

        $canManage =
            in_array(
                $status,
                [
                    TransformationImplementationRequestContract::STATUS_DEFINITION_AGREED,
                    TransformationImplementationRequestContract::STATUS_READY_FOR_COMMERCIAL,
                ],
                true
            );

        if ($canManage) {
            return [
                'visible' => true,
                'can_manage' => true,
                'state' => 'enabled',
                'message' =>
                    'La entrega de fuentes está habilitada para esta implementación.',
            ];
        }

        return [
            'visible' => true,
            'can_manage' => false,
            'state' => 'pending_definition_agreement',
            'message' =>
                'La solicitud ya fue recibida. La entrega de fuentes se habilitará cuando la definición funcional quede acordada.',
        ];
    }

    public function assertCanManage(
        TransformationImplementationRequest $implementationRequest
    ): void {
        $state =
            $this->state(
                (string) $implementationRequest->status
            );

        if ($state['can_manage']) {
            return;
        }

        throw ValidationException::withMessages([
            'implementation_request' => [
                $state['message']
                ?? 'La entrega de fuentes todavía no está habilitada para esta solicitud.',
            ],
        ]);
    }
}
