<?php

declare(strict_types=1);

namespace App\Services\Diagnosis;

use InvalidArgumentException;

/** Identidad obtenida por B2 del servidor, nunca del payload de mensajes/conclusiones. */
final readonly class DataBiDeclarativeReviewActor
{
    private function __construct(public int $userId, public string $kind, private ?int $companyId) {}

    public static function admin(int $userId, string $role, bool $verified): self
    {
        if ($userId < 1 || $role !== 'admin' || ! $verified) {
            throw new InvalidArgumentException('Se requiere Admin LAUDA autenticado y verificado.');
        }

        return new self($userId, 'lauda_admin', null);
    }

    /**
     * activeMembership debe resolverse para userId y el subscriber real, ambos activos.
     * T4C/T4D autorizan por membresía, sin exigir un rol global Tenant específico.
     * El rol global admin pertenece a Admin LAUDA y no puede suplantar al Tenant.
     */
    public static function tenant(
        int $userId,
        bool $verified,
        string $userRole,
        int $companyId,
        int $companyOwnerId,
        int $subscriberId,
        int $companySubscriberId,
        ?string $membershipRole,
        bool $activeMembership,
    ): self {
        if (! $verified || $userRole === 'admin' || $companyId < 1 || $subscriberId < 1 || $subscriberId !== $companySubscriberId
            || ! DataBiInformationTenantAccessPolicy::mayRead($userId, $companyOwnerId, $membershipRole, $activeMembership)) {
            throw new InvalidArgumentException('Se requiere Admin Tenant con membresía activa del subscriber real.');
        }

        return new self($userId, 'tenant', $companyId);
    }

    public function assertScope(DataBiDeclarativeReviewContext $context): void
    {
        if ($this->kind === 'tenant' && $this->companyId !== $context->reference()['company_id']) {
            throw new InvalidArgumentException('Actor ajeno a la empresa de la declaración.');
        }
    }
}
