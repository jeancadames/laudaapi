<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ActivationRequestService extends Pivot
{
    protected $table = 'activation_request_service';

    protected $fillable = [
        'activation_request_id',
        'service_id',
        'meta',
        'status',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
