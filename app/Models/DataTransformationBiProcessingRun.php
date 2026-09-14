<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiProcessingRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table =
        'data_transformation_bi_processing_runs';

    protected $fillable = [
        'data_transformation_bi_intake_batch_id',
        'company_id',
        'transformation_implementation_request_id',
        'transformation_implementation_definition_id',
        'definition_version',
        'schema_version',
        'profiling_version',
        'normalization_version',
        'status',
        'source_row_count',
        'profiled_row_count',
        'normalized_row_count',
        'issue_count',
        'blocking_issue_count',
        'warning_issue_count',
        'created_by_user_id',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_code',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_intake_batch_id' => 'integer',
            'company_id' => 'integer',
            'transformation_implementation_request_id' => 'integer',
            'transformation_implementation_definition_id' => 'integer',
            'definition_version' => 'integer',
            'schema_version' => 'integer',
            'profiling_version' => 'integer',
            'normalization_version' => 'integer',
            'source_row_count' => 'integer',
            'profiled_row_count' => 'integer',
            'normalized_row_count' => 'integer',
            'issue_count' => 'integer',
            'blocking_issue_count' => 'integer',
            'warning_issue_count' => 'integer',
            'created_by_user_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeBatch::class,
            'data_transformation_bi_intake_batch_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function implementationRequest(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationRequest::class,
            'transformation_implementation_request_id'
        );
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(
            TransformationImplementationDefinition::class,
            'transformation_implementation_definition_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function domainProfiles(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiDomainProfile::class,
            'data_transformation_bi_processing_run_id'
        );
    }

    public function fieldProfiles(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiFieldProfile::class,
            'data_transformation_bi_processing_run_id'
        );
    }

    public function qualityIssues(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiQualityIssue::class,
            'data_transformation_bi_processing_run_id'
        );
    }

    public function normalizedRows(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiNormalizedRow::class,
            'data_transformation_bi_processing_run_id'
        );
    }
}
