<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class ErrorLog extends Model
{
    protected $fillable = [
        'level',
        'type',
        'fingerprint',
        'message',
        'file',
        'line',
        'code',
        'trace',
        'method',
        'url',
        'route',
        'request_id',
        'status_code',
        'user_id',
        'ip',
        'user_agent',
        'context',
        'tags',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'context' => 'array',
        'tags' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
