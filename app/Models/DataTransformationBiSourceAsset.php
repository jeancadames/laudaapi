<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DataTransformationBiSourceAsset extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ARCHIVED = 'archived';

    public const STRUCTURE_PENDING = 'pending';
    public const STRUCTURE_PROVIDED = 'provided';
    public const STRUCTURE_ANALYZED = 'analyzed';

    public const DATA_PENDING = 'pending';
    public const DATA_RECEIVED = 'received';
    public const DATA_ANALYZED = 'analyzed';

    public const PROFILING_IDLE = 'idle';
    public const PROFILING_QUEUED = 'queued';
    public const PROFILING_PROCESSING = 'processing';
    public const PROFILING_COMPLETED = 'completed';
    public const PROFILING_FAILED = 'failed';

    public const DELIVERY_CSV = 'csv';
    public const DELIVERY_XLSX = 'xlsx';

    public const STRUCTURE_FORMAT_FIELD_TYPE_LIST =
        'field_type_list';

    public const STRUCTURE_FORMAT_SQL_SERVER_DDL =
        'sql_server_ddl';

    public const STRUCTURE_FORMAT_OTHER =
        'other';

    protected $table =
        'data_transformation_bi_source_assets';

    protected $fillable = [
        'data_transformation_bi_intake_session_id',
        'company_id',
        'display_name',
        'source_object_name',
        'description',
        'origin_system',
        'owner',
        'structure_format',
        'structure_text',
        'delivery_format',
        'status',
        'structure_status',
        'data_status',
        'structure_snapshot',
        'profiling_snapshot',
        'profiling_status',
        'profiling_job_uuid',
        'profiling_queued_at',
        'profiling_started_at',
        'profiling_finished_at',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
        'structure_analyzed_at',
        'data_received_at',
        'profiled_at',
        'archived_at',
        'failure_code',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_intake_session_id' =>
                'integer',

            'company_id' =>
                'integer',

            'structure_snapshot' =>
                'array',

            'profiling_snapshot' =>
                'array',

            'profiling_queued_at' =>
                'datetime',

            'profiling_started_at' =>
                'datetime',

            'profiling_finished_at' =>
                'datetime',

            'sort_order' =>
                'integer',

            'created_by_user_id' =>
                'integer',

            'updated_by_user_id' =>
                'integer',

            'structure_analyzed_at' =>
                'datetime',

            'data_received_at' =>
                'datetime',

            'profiled_at' =>
                'datetime',

            'archived_at' =>
                'datetime',
        ];
    }

    public function intakeSession(): BelongsTo
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id'
        );
    }

    public function dataFile(): HasOne
    {
        return $this->hasOne(
            DataTransformationBiSourceAssetFile::class,
            'data_transformation_bi_source_asset_id'
        );
    }

}
