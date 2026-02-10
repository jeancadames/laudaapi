<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ServiceActivationRequest extends Pivot
{
    protected $table = 'activation_request_service';

    protected $casts = [
        'meta' => 'array',
    ];

    protected $fillable = [
        'activation_request_id',
        'service_id',
        'meta',
        'status',
    ];
}
