<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DgiiCompanyEndpoint extends Model
{
    protected $table = 'dgii_company_endpoints';

    protected $fillable = [
        'company_id',
        'endpoint_id',
        'base_url',
        'path',
        'method',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function endpoint(): BelongsTo
    {
        // asumiendo que tienes App\Models\DgiiEndpointCatalog
        return $this->belongsTo(DgiiEndpointCatalog::class, 'endpoint_id');
    }
}
