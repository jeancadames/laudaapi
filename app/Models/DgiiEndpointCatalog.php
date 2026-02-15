<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DgiiEndpointCatalog extends Model
{
    protected $table = 'dgii_endpoint_catalog';

    // Si no quieres mass assignment restringido, ok:
    protected $guarded = [];

    protected $casts = [
        'is_templated' => 'boolean',
        'is_default'   => 'boolean',
        'is_active'    => 'boolean',
        'meta'         => 'array',   // ✅ clave: ya no decodificas a mano
    ];
}
