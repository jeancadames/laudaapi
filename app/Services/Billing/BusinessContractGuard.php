<?php

namespace App\Services\Billing;

use App\Models\BusinessContract;
use App\Models\SubscriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Keep existing mutation paths from repricing/cancelling a package as individual services. */
final class BusinessContractGuard
{
    public static function assertSubscriberAvailable(int $subscriberId): void
    {
        if (Schema::hasTable('business_contracts') && BusinessContract::where('subscriber_id', $subscriberId)->exists()) {
            self::reject();
        }
    }

    public static function assertItemMutable(SubscriptionItem $item): void
    {
        if (! Schema::hasTable('business_contracts')) {
            return;
        }
        if ($item->exists && Schema::hasTable('business_transition_items')
            && DB::table('business_transition_items')->where('subscription_item_id', $item->id)->exists()) {
            self::reject();
        }
        if (Schema::hasTable('business_transition_subscriptions') && DB::table('business_transition_subscriptions')
            ->whereIn('subscription_id', array_filter([$item->subscription_id, $item->getOriginal('subscription_id')]))->where('retained', false)->exists()) {
            self::reject();
        }
        // Also guard inserts into a subscription already owned by a Business contract.
        if (BusinessContract::whereIn('subscription_id', array_filter([$item->subscription_id, $item->getOriginal('subscription_id')]))->exists()
            || ($item->exists && DB::table('business_contract_items')->where('subscription_item_id', $item->getKey())->exists())) {
            self::reject();
        }
    }

    private static function reject(): never
    {
        throw ValidationException::withMessages(['business_contract' => 'La suscripción tiene un contrato Business. Solicita revisar su transición antes de cambiar servicios o facturar por separado.']);
    }
}
