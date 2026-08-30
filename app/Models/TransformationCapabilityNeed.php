<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationCapabilityNeed extends Model
{
    public const STATUS_IDENTIFIED = 'identified';

    protected $fillable = [
        'transformation_capability_activation_id',
        'sequence',
        'need_key',
        'title',
        'description',
        'source_type',
        'source_snapshot',
        'status',
        'identified_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'source_snapshot' => 'array',
            'identified_at' => 'datetime',
        ];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(
            TransformationCapabilityActivation::class,
            'transformation_capability_activation_id'
        );
    }
}
