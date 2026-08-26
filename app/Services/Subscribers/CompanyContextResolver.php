<?php

namespace App\Services\Subscribers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CompanyContextResolver
{
    public function __construct(
        private readonly SubscriberResolver $subscriberResolver,
    ) {
    }

    public function resolve(
        User $user,
        ?int $subscriberId = null,
        ?int $preferredCompanyId = null
    ): ?Company {
        $subscriberId = (int) (
            $subscriberId
            ?: $this->subscriberResolver->resolve($user)
            ?: 0
        );

        $preferredCompanyId = (int) ($preferredCompanyId ?: 0);

        if ($preferredCompanyId > 0) {
            $company = $this->baseQuery($subscriberId)
                ->whereKey($preferredCompanyId)
                ->first();

            if ($company) {
                return $company;
            }
        }

        $userCompanyId = (int) ($user->company_id ?? 0);

        if ($userCompanyId > 0) {
            $company = $this->baseQuery($subscriberId)
                ->whereKey($userCompanyId)
                ->first();

            if ($company) {
                return $company;
            }
        }

        $owned = $this->baseQuery($subscriberId)
            ->where('owner_user_id', $user->id)
            ->limit(2)
            ->get();

        if ($owned->count() === 1) {
            return $owned->first();
        }

        if ($owned->count() > 1) {
            return null;
        }

        if ($subscriberId <= 0) {
            return null;
        }

        $subscriberCompanies = Company::query()
            ->where('subscriber_id', $subscriberId)
            ->where('active', true)
            ->limit(2)
            ->get();

        return $subscriberCompanies->count() === 1
            ? $subscriberCompanies->first()
            : null;
    }

    private function baseQuery(int $subscriberId): Builder
    {
        return Company::query()
            ->where('active', true)
            ->when(
                $subscriberId > 0,
                fn (Builder $query) => $query->where(
                    'subscriber_id',
                    $subscriberId
                )
            );
    }
}
