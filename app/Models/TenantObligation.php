<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TenantObligation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tenant_obligations';

    // ✅ Mejor que guarded si vas a usar upserts/DTOs
    protected $fillable = [
        'public_id',
        'company_id',
        'template_id',
        'owner_user_id',
        'enabled',
        'starts_on',
        'ends_on',
        'last_synced_at',
        'overrides',
        'reminders',
        'meta',
    ];

    protected $casts = [
        'enabled'        => 'boolean',
        'starts_on'      => 'date',
        'ends_on'        => 'date',
        'last_synced_at' => 'datetime',
        'overrides'      => 'array',
        'reminders'      => 'array',
        'meta'           => 'array',
        'deleted_at'     => 'datetime',
    ];

    // ✅ Defaults (evita nulls raros)
    protected $attributes = [
        'enabled'   => true,
        'overrides' => '[]',
        'reminders' => '[]',
        'meta'      => '[]',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->public_id)) {
                $m->public_id = (string) Str::ulid();
            }
        });
    }

    // ✅ Si quieres rutas tipo /tenant-obligations/{public_id}
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    // -------------------------
    // Relations
    // -------------------------
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function template()
    {
        return $this->belongsTo(ComplianceObligationTemplate::class, 'template_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function instances()
    {
        return $this->hasMany(ObligationInstance::class, 'tenant_obligation_id');
    }

    // -------------------------
    // Scopes (para sync)
    // -------------------------
    public function scopeForCompany(Builder $q, int $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    public function scopeEnabled(Builder $q): Builder
    {
        return $q->where('enabled', 1);
    }

    /**
     * Activa si:
     * - starts_on <= $date (o null)
     * - ends_on   >= $date (o null)
     */
    public function scopeActiveOn(Builder $q, string|\DateTimeInterface $date): Builder
    {
        $d = is_string($date) ? $date : $date->format('Y-m-d');

        return $q
            ->where(function ($w) use ($d) {
                $w->whereNull('starts_on')->orWhere('starts_on', '<=', $d);
            })
            ->where(function ($w) use ($d) {
                $w->whereNull('ends_on')->orWhere('ends_on', '>=', $d);
            });
    }
}
