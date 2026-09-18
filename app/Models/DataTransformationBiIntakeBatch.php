<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiIntakeBatch extends Model
{
    public const STATUS_PENDING =
        'pending';

    public const STATUS_PROCESSING =
        'processing';

    public const STATUS_COMPLETED =
        'completed';

    public const STATUS_FAILED =
        'failed';

    public const STATUS_PURGED =
        'purged';

    public const FORMAT_XLSX =
        'xlsx';

    public const FORMAT_CSV_ZIP =
        'csv_zip';

    public const FORMAT_DOMAIN_SESSION_MANIFEST =
        'domain_session_manifest';

    protected $table =
        'data_transformation_bi_intake_batches';

    protected $fillable = [
        'company_id',
        'transformation_implementation_request_id',
        'transformation_implementation_definition_id',
        'definition_version',
        'schema_version',
        'source_disk',
        'source_path',
        'original_filename',
        'source_format',
        'source_mime_type',
        'source_size_bytes',
        'source_sha256',
        'validation_snapshot',
        'status',
        'domain_count',
        'source_row_count',
        'staged_row_count',
        'rejected_row_count',
        'created_by_user_id',
        'source_retention_until',
        'source_deleted_at',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_code',
        'failure_message',
        'purged_at',
    ];

    protected $hidden = [
        'source_path',
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

            'source_size_bytes' =>
                'integer',

            'validation_snapshot' =>
                'array',

            'domain_count' =>
                'integer',

            'source_row_count' =>
                'integer',

            'staged_row_count' =>
                'integer',

            'rejected_row_count' =>
                'integer',

            'created_by_user_id' =>
                'integer',

            'source_retention_until' =>
                'datetime',

            'source_deleted_at' =>
                'datetime',

            'started_at' =>
                'datetime',

            'completed_at' =>
                'datetime',

            'failed_at' =>
                'datetime',

            'purged_at' =>
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

    public function domains(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiIntakeBatchDomain::class,
            'data_transformation_bi_intake_batch_id'
        );
    }

    public function rows(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiIntakeRow::class,
            'data_transformation_bi_intake_batch_id'
        );
    }
}
