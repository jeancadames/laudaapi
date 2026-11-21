<?php

namespace App\Services\BusinessIdentity;

use App\Models\Company;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CentralMembership
{
    public function role(User $user, Company $company): ?string
    {
        if (! $user->id || ! $company->active || ! $company->subscriber_id || ! $user->hasVerifiedEmail()) {
            return null;
        }

        return $user->subscribers()->where('subscribers.id', $company->subscriber_id)
            ->where('subscribers.active', true)->wherePivot('active', true)->first()?->pivot?->role;
    }

    public function authorize(User $user, Company $company, bool $manageCompany = false): string
    {
        $role = $this->role($user, $company);
        if ($role === null || ($manageCompany && ! in_array($role, ['owner', 'admin'], true))) {
            throw new HttpException(403, 'No tienes una membresía activa con el permiso necesario en esta empresa.');
        }

        return $role;
    }
}
