<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransformationImplementationPhase extends Model
{
    protected $fillable = [
        'transformation_implementation_plan_id',
        'sequence',
        'name',
        'objective',
        'source_snapshot',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'source_snapshot' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransformationImplementationPlan::class, 'transformation_implementation_plan_id');
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(
            TransformationImplementationPhaseCapability::class,
            'transformation_implementation_phase_id'
        )->orderBy('sequence')->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function estimates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            TransformationImplementationPhaseEstimate::class,
            'transformation_implementation_phase_id'
        )->orderBy('modality');
    }


    public function milestones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            TransformationImplementationMilestone::class,
            'transformation_implementation_phase_id'
        )->orderBy('sequence')->orderBy('id');
    }


    public function execution(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            TransformationImplementationPhaseExecution::class,
            'transformation_implementation_phase_id'
        );
    }

}
