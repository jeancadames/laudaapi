<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxAuthority extends Model
{
    use SoftDeletes;

    protected $table = 'tax_authorities';

    protected $fillable = [
        'country_code',
        'code',
        'name',
        'active',
        'sort_order',
        'official_url',
        'meta',
    ];

    protected $casts = [
        'active' => 'bool',
        'sort_order' => 'int',
        'meta' => 'array',
    ];

    // ----------------------------
    // Normalización (evita DGII vs dgii)
    // ----------------------------
    public function setCountryCodeAttribute($value): void
    {
        $v = strtoupper(trim((string) ($value ?: 'DO')));
        $this->attributes['country_code'] = substr($v, 0, 2);
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = trim((string) $value);
    }
}
