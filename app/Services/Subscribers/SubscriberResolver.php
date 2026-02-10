<?php

namespace App\Services\Subscribers;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

class SubscriberResolver
{
    public function resolve($user): ?int
    {
        if (!$user) return null;

        $sid = (int) ($user->subscriber_id ?? 0);
        if ($sid > 0) return $sid;

        $sid = (int) DB::table('subscriber_user')
            ->where('user_id', $user->id)
            ->where('active', 1)
            ->orderByDesc('id')
            ->value('subscriber_id');

        if ($sid > 0) return $sid;

        if (!empty($user->company_id)) {
            $sid = (int) Company::query()
                ->where('id', (int) $user->company_id)
                ->value('subscriber_id');

            if ($sid > 0) return $sid;
        }

        $sid = (int) Company::query()
            ->where('owner_user_id', $user->id)
            ->value('subscriber_id');

        return $sid > 0 ? $sid : null;
    }
}
