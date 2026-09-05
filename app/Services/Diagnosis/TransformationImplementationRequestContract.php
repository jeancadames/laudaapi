<?php

namespace App\Services\Diagnosis;

/**
 * Contrato funcional del proceso de Solicitud de Implementación LAUDA 360.
 *
 * Esta clase NO persiste datos y NO ejecuta implementación.
 *
 * La solicitud representa únicamente la decisión explícita de un
 * Tenant Admin de pedir a LAUDA que revise una capacidad profesional
 * incluida en un Plan de Implementación.
 *
 * Recomendado != solicitado.
 * Solicitado != contratado.
 * Contratado != implementado.
 */
final class TransformationImplementationRequestContract
{
    public const SOURCE_TENANT_ADMIN = 'tenant_admin';

    public const REQUIRED_CAPABILITY_KIND = 'professional_service';

    public const REQUIRED_ACTIVATION_POLICY = 'implementation_only';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_UNDER_LAUDA_REVIEW = 'under_lauda_review';

    public const STATUS_DEFINITION_PREPARATION =
        'definition_preparation';

    public const STATUS_AWAITING_TENANT_REVIEW =
        'awaiting_tenant_review';

    public const STATUS_CHANGES_REQUESTED =
        'changes_requested';

    public const STATUS_DEFINITION_AGREED =
        'definition_agreed';

    public const STATUS_READY_FOR_COMMERCIAL =
        'ready_for_commercial';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_UNDER_LAUDA_REVIEW,
        self::STATUS_DEFINITION_PREPARATION,
        self::STATUS_AWAITING_TENANT_REVIEW,
        self::STATUS_CHANGES_REQUESTED,
        self::STATUS_DEFINITION_AGREED,
        self::STATUS_READY_FOR_COMMERCIAL,
        self::STATUS_CANCELLED,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_READY_FOR_COMMERCIAL,
        self::STATUS_CANCELLED,
    ];

    /**
     * Transiciones que pertenecen al Tenant Admin.
     */
    private const TENANT_TRANSITIONS = [
        self::STATUS_REQUESTED => [
            self::STATUS_CANCELLED,
        ],

        self::STATUS_UNDER_LAUDA_REVIEW => [
            self::STATUS_CANCELLED,
        ],

        self::STATUS_DEFINITION_PREPARATION => [
            self::STATUS_CANCELLED,
        ],

        self::STATUS_AWAITING_TENANT_REVIEW => [
            self::STATUS_CHANGES_REQUESTED,
            self::STATUS_DEFINITION_AGREED,
            self::STATUS_CANCELLED,
        ],

        self::STATUS_CHANGES_REQUESTED => [
            self::STATUS_CANCELLED,
        ],

        self::STATUS_DEFINITION_AGREED => [
            self::STATUS_CANCELLED,
        ],
    ];

    /**
     * Transiciones que pertenecen al Admin LAUDA.
     */
    private const LAUDA_TRANSITIONS = [
        self::STATUS_REQUESTED => [
            self::STATUS_UNDER_LAUDA_REVIEW,
        ],

        self::STATUS_UNDER_LAUDA_REVIEW => [
            self::STATUS_DEFINITION_PREPARATION,
        ],

        self::STATUS_DEFINITION_PREPARATION => [
            self::STATUS_AWAITING_TENANT_REVIEW,
        ],

        self::STATUS_CHANGES_REQUESTED => [
            self::STATUS_DEFINITION_PREPARATION,
        ],

        self::STATUS_DEFINITION_AGREED => [
            self::STATUS_READY_FOR_COMMERCIAL,
        ],
    ];

    /**
     * Reglas para crear una solicitud.
     *
     * No equivalen a activación.
     */
    public static function requestability(): array
    {
        return [
            'initiated_by' =>
                self::SOURCE_TENANT_ADMIN,

            'requires_company' => true,

            'requires_assessment' => true,

            'requires_presented_plan' => true,

            'requires_capability_in_plan' => true,

            'required_capability_kind' =>
                self::REQUIRED_CAPABILITY_KIND,

            'required_activation_policy' =>
                self::REQUIRED_ACTIVATION_POLICY,

            'requires_company_assessment_plan_alignment' =>
                true,

            /*
             * Un doble submit no debe crear dos solicitudes activas
             * para la misma empresa + Plan + capability.
             */
            'active_request_idempotency_scope' =>
                'company_plan_capability',

            /*
             * Si una solicitud anterior fue cancelada, una nueva
             * solicitud explícita crea un nuevo intento/histórico.
             */
            'resubmission_after_cancel_creates_new_attempt' =>
                true,

            /*
             * La Definition NO nace en el POST del tenant.
             * Su preparación es una acción posterior de Admin LAUDA.
             */
            'definition_creation_is_lauda_action' =>
                true,

            /*
             * La Definition originada por una solicitud debe
             * limitarse a la capability solicitada, aunque el Plan
             * contenga otras capabilities.
             */
            'definition_must_be_scoped_to_requested_capability' =>
                true,
        ];
    }

    /**
     * Boundary absoluto de la solicitud.
     */
    public static function sideEffectBoundary(): array
    {
        return [
            'request_is_activation' => false,

            'creates_transformation_capability_activation' =>
                false,

            'creates_definition_automatically' => false,

            'starts_execution' => false,

            'ready_for_execution' => false,

            'commercial_acceptance' => false,

            'creates_price' => false,

            'creates_order' => false,

            'creates_invoice' => false,

            'creates_payment' => false,

            'creates_subscription' => false,

            'creates_subscription_item' => false,

            'creates_go_live' => false,
        ];
    }

    public static function canTenantTransition(
        string $from,
        string $to
    ): bool {
        return in_array(
            $to,
            self::TENANT_TRANSITIONS[$from] ?? [],
            true
        );
    }

    public static function canLaudaTransition(
        string $from,
        string $to
    ): bool {
        return in_array(
            $to,
            self::LAUDA_TRANSITIONS[$from] ?? [],
            true
        );
    }

    public static function isTerminal(string $status): bool
    {
        return in_array(
            $status,
            self::TERMINAL_STATUSES,
            true
        );
    }
}
