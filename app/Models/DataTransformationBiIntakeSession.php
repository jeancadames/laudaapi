<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiIntakeSession extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_FINALIZING = 'finalizing';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table =
        'data_transformation_bi_intake_sessions';

    protected $fillable = [
        'company_id',
        'transformation_implementation_request_id',
        'transformation_implementation_definition_id',
        'definition_version',
        'schema_version',
        'status',
        'created_by_user_id',
        'resulting_intake_batch_id',
        'resolved_manifest_sha256',
        'relational_validation_snapshot',
        'failure_code',
        'failure_message',
        'started_at',
        'ready_at',
        'finalized_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' =>
                'integer',

            'transformation_implementation_request_id' =>
                'integer',

            'transformation_implementation_definition_id' =>
                'integer',

            'definition_version' =>
                'integer',

            'schema_version' =>
                'integer',

            'created_by_user_id' =>
                'integer',

            'resulting_intake_batch_id' =>
                'integer',

            'relational_validation_snapshot' =>
                'array',

            'started_at' =>
                'datetime',

            'ready_at' =>
                'datetime',

            'finalized_at' =>
                'datetime',

            'cancelled_at' =>
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

    public function resultingBatch(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeBatch::class,
            'resulting_intake_batch_id'
        );
    }

    public function domainDeliveries(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiIntakeDomainDelivery::class,
            'data_transformation_bi_intake_session_id'
        );
    }

    public function sourceAssets(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiSourceAsset::class,
            'data_transformation_bi_intake_session_id'
        );
    }

}
