<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiIntakeDomainDelivery extends Model
{
    public const MODE_UPLOADED = 'uploaded';

    public const MODE_NO_DATA = 'no_data';

    public const MODE_CARRY_FORWARD = 'carry_forward';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_VALID = 'valid';

    public const STATUS_INVALID = 'invalid';

    public const FORMAT_XLSX = 'xlsx';

    public const FORMAT_CSV = 'csv';

    protected $table =
        'data_transformation_bi_intake_domain_deliveries';

    protected $fillable = [
        'data_transformation_bi_intake_session_id',
        'company_id',
        'domain_key',
        'delivery_mode',
        'status',
        'source_disk',
        'source_path',
        'original_filename',
        'source_format',
        'source_mime_type',
        'source_size_bytes',
        'source_sha256',
        'validation_snapshot',
        'source_row_count',
        'accepted_row_count',
        'carry_forward_processing_run_id',
        'carry_forward_intake_batch_id',
        'created_by_user_id',
        'validated_at',
    ];

    protected $hidden = [
        'source_path',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_intake_session_id' =>
                'integer',

            'company_id' =>
                'integer',

            'source_size_bytes' =>
                'integer',

            'source_row_count' =>
                'integer',

            'accepted_row_count' =>
                'integer',

            'carry_forward_processing_run_id' =>
                'integer',

            'carry_forward_intake_batch_id' =>
                'integer',

            'created_by_user_id' =>
                'integer',

            'validation_snapshot' =>
                'array',

            'validated_at' =>
                'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeSession::class,
            'data_transformation_bi_intake_session_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function carryForwardProcessingRun(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiProcessingRun::class,
            'carry_forward_processing_run_id'
        );
    }

    public function carryForwardIntakeBatch(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeBatch::class,
            'carry_forward_intake_batch_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }
}
