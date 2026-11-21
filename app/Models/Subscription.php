<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class Subscription extends Model
{
    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'meta' => 'array',

        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        $protect = function (Subscription $subscription): void {
            if ($subscription->exists && Schema::hasTable('business_transition_subscriptions')
                && DB::table('business_transition_subscriptions')->where('subscription_id', $subscription->id)->where('retained', false)->exists()) {
                throw ValidationException::withMessages(['subscription' => 'Esta suscripción fue consolidada en Business; su historial se conserva.']);
            }
        };
        self::updating($protect);
        self::deleting($protect);
    }

    /** Keep the retained contract visible even when a newer source subscription was retired. */
    public function scopePreferBusinessContract(Builder $query): Builder
    {
        if (Schema::hasTable('business_contracts')) {
            $query->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM business_contracts WHERE business_contracts.subscription_id = subscriptions.id) THEN 0 ELSE 1 END');
        }

        return $query;
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class, 'subscription_id');
    }

    // Scopes útiles
    public function scopeForSubscriber($query, int $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function bundleDiscountApplications(): HasMany
    {
        return $this->hasMany(
            SubscriptionBundleDiscountApplication::class,
            'subscription_id'
        );
    }
}
