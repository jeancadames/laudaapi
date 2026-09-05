<?php

namespace App\Services\Diagnosis;

/**
 * Contrato de identidad para las nuevas Definitions
 * creadas desde TransformationImplementationRequest.
 *
 * Este contrato NO crea Definition.
 * NO cambia estados.
 * NO activa capabilities.
 * NO inicia ejecución.
 * NO inicia etapa comercial.
 */
final class TransformationImplementationDefinitionRequestScopeContract
{
    public const SOURCE_TYPE =
        'implementation_request';

    public const REQUIRED_REQUEST_STATUS =
        TransformationImplementationRequestContract::STATUS_DEFINITION_PREPARATION;

    public const REQUIRED_FIELDS = [
        'transformation_implementation_request_id',
        'transformation_implementation_phase_capability_id',
        'capability_key',
    ];

    public const SCOPE_MODE =
        'single_capability';

    public static function boundary(): array
    {
        return [
            'definition_scope' =>
                self::SCOPE_MODE,

            'request_required' =>
                true,

            'phase_capability_required' =>
                true,

            'capability_key_required' =>
                true,

            'request_status_required' =>
                self::REQUIRED_REQUEST_STATUS,

            'plan_wide_definition' =>
                false,

            'auto_definition' =>
                false,

            'activation' =>
                false,

            'commercial_acceptance' =>
                false,

            'execution_started' =>
                false,

            'subscription_created' =>
                false,
        ];
    }
}
