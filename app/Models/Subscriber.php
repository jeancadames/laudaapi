<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subscriber extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriber_user')
            ->withPivot(['role', 'active'])
            ->withTimestamps();
    }

    // 1:1 por ahora (companies.subscriber_id)
    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'subscriber_id');
    }

    // subscriptions.subscriber_id
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscriber_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    // Subscribers a los que este user tiene acceso (pivot activo)
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('users', function ($q) use ($userId) {
            $q->where('users.id', $userId)
                ->where('subscriber_user.active', true);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS (opcionales, pero prácticos)
    |--------------------------------------------------------------------------
    */

    public function owner()
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    public function admins()
    {
        return $this->users()->whereIn('subscriber_user.role', ['owner', 'admin']);
    }
}
