<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;

class DgiiWsActivityLog extends Model
{
    use Prunable;

    protected $guarded = [];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'meta' => 'array',
    ];

    // ✅ Retén 30 días (ajusta)
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }
}
