<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\AuditService;
use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'billing:expire-trials';
    protected $description = 'Marca como expired las suscripciones trialing cuyo trial_ends_at ya venció';

    public function handle(): int
    {
        $now = now();

        $subs = Subscription::query()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        $count = 0;

        foreach ($subs as $sub) {
            $sub->status = 'expired';
            $sub->ends_at = $sub->ends_at ?? $now;
            $sub->save();

            AuditService::log('subscription_trial_expired', $sub, [
                'subscription_id' => $sub->id,
                'subscriber_id' => $sub->subscriber_id,
                'trial_ends_at' => $sub->trial_ends_at?->toISOString(),
                'expired_at' => $now->toISOString(),
            ], [
                'user_id' => null, // es batch
                'user_agent' => 'console:billing:expire-trials',
            ]);

            $count++;
        }

        $this->info("Expired trials: {$count}");
        return self::SUCCESS;
    }
}
