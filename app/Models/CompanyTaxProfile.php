<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class CompanyTaxProfile extends Model
{
    protected $table = 'company_tax_profiles';

    /**
     * Recomendación: usa fillable en vez de guarded=[].
     * Así evitas que un payload inesperado te escriba columnas sensibles.
     */
    protected $fillable = [
        'company_id',

        // base
        'legal_name',
        'trade_name',
        'country_code',
        'tax_id',
        'tax_id_type',

        // address
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',

        // billing
        'billing_email',
        'billing_phone',
        'billing_contact_name',

        // tax config
        'tax_exempt',
        'default_itbis_rate',

        // DGII improvements
        'taxpayer_type', // persona_fisica | persona_juridica
        'tax_regime',    // general | rst | special
        'rst_modality',  // ingresos | compras
        'rst_category',

        'economic_activity_primary_code',
        'economic_activity_primary_name',
        'economic_activities_secondary', // json

        'invoicing_mode',     // ncf | ecf | both
        'dgii_status',
        'dgii_registered_on', // date

        'meta',
    ];

    /**
     * Defaults útiles para evitar nulls en la UI y lógica.
     */
    protected $attributes = [
        'country_code' => 'DO',
        'tax_id_type' => 'RNC',
        'tax_exempt' => false,
        'default_itbis_rate' => 18.000,

        'tax_regime' => 'general',
        'economic_activities_secondary' => '[]',
        'meta' => '[]',
    ];

    protected $casts = [
        // existing
        'meta' => 'array',
        'tax_exempt' => 'boolean',
        'default_itbis_rate' => 'decimal:3',

        // DGII
        'economic_activities_secondary' => 'array',
        'dgii_registered_on' => 'date',

        // opcional (útil en UI/logs)
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // -------------------------
    // Relations
    // -------------------------
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    // -------------------------
    // Query scopes (opcional)
    // -------------------------
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActiveCompany($query)
    {
        return $query->whereHas('company', fn($q) => $q->where('active', true));
    }

    // -------------------------
    // Helpers (DGII / UI)
    // -------------------------
    public function isRst(): bool
    {
        return ($this->tax_regime ?? null) === 'rst';
    }

    public function isRstIngresos(): bool
    {
        return $this->isRst() && ($this->rst_modality ?? null) === 'ingresos';
    }

    public function isRstCompras(): bool
    {
        return $this->isRst() && ($this->rst_modality ?? null) === 'compras';
    }

    public function isPersonaFisica(): bool
    {
        return ($this->taxpayer_type ?? null) === 'persona_fisica';
    }

    public function isPersonaJuridica(): bool
    {
        return ($this->taxpayer_type ?? null) === 'persona_juridica';
    }

    public function usesEcf(): bool
    {
        $mode = $this->invoicing_mode ?? null;
        return $mode === 'ecf' || $mode === 'both';
    }

    public function usesNcf(): bool
    {
        $mode = $this->invoicing_mode ?? null;
        return $mode === 'ncf' || $mode === 'both';
    }

    /**
     * Retorna actividad principal como array consistente.
     */
    public function primaryEconomicActivity(): array
    {
        return [
            'code' => $this->economic_activity_primary_code,
            'name' => $this->economic_activity_primary_name,
        ];
    }

    /**
     * Retorna secundarias normalizadas (sin entradas vacías).
     */
    public function secondaryEconomicActivities(): array
    {
        $list = $this->economic_activities_secondary ?? [];
        if (!is_array($list)) return [];

        return collect($list)->map(function ($row) {
            return [
                'code' => isset($row['code']) ? trim((string) $row['code']) : null,
                'name' => isset($row['name']) ? trim((string) $row['name']) : null,
            ];
        })->filter(fn($row) => !empty($row['code']) || !empty($row['name']))
            ->values()
            ->all();
    }

    // -------------------------
    // Cache busting
    // -------------------------
    protected static function booted(): void
    {
        static::saved(function (self $m) {
            Cache::forget('admin.dashboard.stats');

            // si usas stats por company/user en subscriber, puedes limpiar aquí también
            // (no siempre es posible sin user_id, por eso solo por company)
            Cache::forget("subscriber.dashboard.stats.company.{$m->company_id}");
        });

        static::deleted(function (self $m) {
            Cache::forget('admin.dashboard.stats');
            Cache::forget("subscriber.dashboard.stats.company.{$m->company_id}");
        });
    }
}
