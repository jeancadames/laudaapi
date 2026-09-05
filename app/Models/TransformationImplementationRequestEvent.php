<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransformationImplementationRequestEvent extends Model
{
    protected $fillable = [
        'transformation_implementation_request_id',
        'event_type',
        'from_status',
        'to_status',
        'actor_type',
        'actor_user_id',
        'notes',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationRequest::class,
            'transformation_implementation_request_id'
        );
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id'
        );
    }
}
