<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationImplementationPhaseEstimate extends Model
{
    protected $fillable = [
        'transformation_implementation_phase_id',
        'modality',
        'modality_label',
        'price_amount',
        'currency',
        'estimated_duration_value',
        'estimated_duration_unit',
        'scope_snapshot',
        'internal_notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'estimated_duration_value' => 'integer',
            'scope_snapshot' => 'array',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhase::class,
            'transformation_implementation_phase_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
