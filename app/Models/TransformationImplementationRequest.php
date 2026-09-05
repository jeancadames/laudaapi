<?php

namespace App\Models;

use App\Services\Diagnosis\TransformationImplementationRequestContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TransformationImplementationRequest extends Model
{
    protected $fillable = [
        'company_id',
        'diagnosis_assessment_id',
        'transformation_implementation_plan_id',
        'transformation_implementation_phase_capability_id',
        'capability_key',
        'attempt',
        'source_type',
        'status',
        'source_snapshot',
        'tenant_note',
        'internal_notes',
        'requested_by_user_id',
        'assigned_to_user_id',
        'status_changed_by_user_id',
        'requested_at',
        'review_started_at',
        'definition_started_at',
        'tenant_review_requested_at',
        'changes_requested_at',
        'definition_agreed_at',
        'ready_for_commercial_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'source_snapshot' => 'array',
            'requested_at' => 'datetime',
            'review_started_at' => 'datetime',
            'definition_started_at' => 'datetime',
            'tenant_review_requested_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'definition_agreed_at' => 'datetime',
            'ready_for_commercial_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosisAssessment::class,
            'diagnosis_assessment_id'
        );
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPlan::class,
            'transformation_implementation_plan_id'
        );
    }

    public function phaseCapability(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationPhaseCapability::class,
            'transformation_implementation_phase_capability_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to_user_id'
        );
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'status_changed_by_user_id'
        );
    }


    public function definitions(): HasMany
    {
        return $this->hasMany(
            TransformationImplementationDefinition::class,
            'transformation_implementation_request_id'
        )->orderBy('version')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            TransformationImplementationRequestEvent::class,
            'transformation_implementation_request_id'
        )->orderBy('occurred_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn(
            'status',
            TransformationImplementationRequestContract::TERMINAL_STATUSES
        );
    }

    public function isTerminal(): bool
    {
        return TransformationImplementationRequestContract::isTerminal(
            (string) $this->status
        );
    }
}
