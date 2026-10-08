<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiImplementationMaterializationRun
    extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $table =
        'data_transformation_bi_implementation_materialization_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'selected_mapping_count' =>
                'integer',

            'materialized_dataset_count' =>
                'integer',

            'reused_dataset_count' =>
                'integer',

            'result_snapshot' =>
                'array',

            'queued_at' =>
                'datetime',

            'started_at' =>
                'datetime',

            'finished_at' =>
                'datetime',
        ];
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

    public function intakeSession(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeSession::class,
            'data_transformation_bi_intake_session_id'
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id'
        );
    }

    public function isActive(): bool
    {
        return in_array(
            (string) $this->status,
            [
                self::STATUS_QUEUED,
                self::STATUS_PROCESSING,
            ],
            true
        );
    }
}
