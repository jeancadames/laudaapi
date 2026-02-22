<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalDocumentType extends Model
{
    protected $table = 'fiscal_document_types';

    protected $fillable = [
        'country_code',
        'document_class',
        'code',
        'name',
        'active',
        'meta',
    ];

    protected $casts = [
        'active' => 'bool',
        'meta' => 'array',
    ];

    public function setCountryCodeAttribute($value): void
    {
        $this->attributes['country_code'] = strtoupper(trim((string) ($value ?: 'DO')));
    }

    public function setDocumentClassAttribute($value): void
    {
        $this->attributes['document_class'] = strtoupper(trim((string) ($value ?: 'ECF')));
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }
}
