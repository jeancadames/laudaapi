<?php

namespace App\Http\Controllers\LaudaErp\Concerns;

use App\Models\Company;
use App\Services\Subscribers\SubscriberResolver;
use Illuminate\Http\Request;

trait ResolvesCompanyFromErp
{
    private function companyFromErp(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user, 403);

        $subscriberId = (int) $request->attributes->get('resolved_subscriber_id', 0);
        if ($subscriberId <= 0) {
            $subscriberId = (int) app(SubscriberResolver::class)->resolve($user);
        }

        abort_unless($subscriberId > 0, 403);

        return Company::where('subscriber_id', $subscriberId)->firstOrFail();
    }
}