<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationPhaseCapability extends Model
{
    protected $fillable = [
        'transformation_implementation_phase_id',
        'sequence',
        'capability_key',
        'capability_label',
        'capability_summary',
        'source_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'source_snapshot' => 'array',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhase::class,
            'transformation_implementation_phase_id'
        );
    }

    public function execution(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            TransformationImplementationCapabilityExecution::class,
            'transformation_implementation_phase_capability_id'
        );
    }


    public function goLives(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            TransformationImplementationCapabilityGoLive::class,
            'transformation_implementation_phase_capability_id'
        )->orderByDesc('attempt');
    }


    public function latestGoLive(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            TransformationImplementationCapabilityGoLive::class,
            'transformation_implementation_phase_capability_id'
        )->latestOfMany('attempt');
    }

}
