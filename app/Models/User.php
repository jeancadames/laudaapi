<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'subscriber_role',
        'must_change_password',
        'password_changed_at',
        'external_user_id',
        'workspace_company_id',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /*
    |--------------------------------------------------------------------------
    | Multi-subscriber (multi-empresa) via pivot subscriber_user
    |--------------------------------------------------------------------------
    */

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class, 'subscriber_user')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    /**
     * Subscribers activos a los que el user tiene acceso
     */
    public function activeSubscribers(): BelongsToMany
    {
        return $this->subscribers()->wherePivot('active', true);
    }

    /**
     * ✅ Validación rápida de acceso a un subscriber
     */
    public function canAccessSubscriber(int $subscriberId): bool
    {
        return $this->subscribers()
            ->where('subscribers.id', $subscriberId)
            ->wherePivot('active', true)
            ->exists();
    }

    /**
     * ✅ Helper para obtener el "rol" dentro de un subscriber
     */
    public function subscriberRole(int $subscriberId): ?string
    {
        $row = $this->subscribers()
            ->where('subscribers.id', $subscriberId)
            ->first();

        return $row?->pivot?->role;
    }

    /*
    |--------------------------------------------------------------------------
    | Subscriptions (NO relación directa por user_id)
    |--------------------------------------------------------------------------
    | subscriptions cuelga de subscriber_id
    */

    /**
     * ✅ Todas las subscriptions accesibles por este usuario (vía subscribers activos)
     * (query builder, no relación)
     */
    public function accessibleSubscriptionsQuery()
    {
        $subscriberIds = $this->activeSubscribers()
            ->pluck('subscribers.id')
            ->unique()
            ->values()
            ->all();

        return Subscription::query()->whereIn('subscriber_id', $subscriberIds);
    }

    /**
     * ✅ Subscriptions de un subscriber específico (si el user tiene acceso)
     */
    public function subscriptionsForSubscriber(int $subscriberId)
    {
        if (!$this->canAccessSubscriber($subscriberId)) {
            return Subscription::query()->whereRaw('1 = 0'); // vacío
        }

        return Subscription::query()->where('subscriber_id', $subscriberId);
    }
}
