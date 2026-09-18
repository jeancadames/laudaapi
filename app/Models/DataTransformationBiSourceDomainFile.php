<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiSourceDomainFile extends Model
{
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_PROFILING = 'profiling';
    public const STATUS_PROFILED = 'profiled';
    public const STATUS_MAPPING = 'mapping';
    public const STATUS_READY = 'ready';
    public const STATUS_TRANSFORMING = 'transforming';
    public const STATUS_TRANSFORMED = 'transformed';
    public const STATUS_FAILED = 'failed';

    public const FORMAT_CSV = 'csv';
    public const FORMAT_XLSX = 'xlsx';

    protected $table =
        'data_transformation_bi_source_domain_files';

    protected $fillable = [
        'data_transformation_bi_intake_domain_delivery_id',
        'company_id',
        'domain_key',
        'status',
        'source_disk',
        'source_path',
        'original_filename',
        'source_format',
        'source_mime_type',
        'source_size_bytes',
        'source_sha256',
        'reader_configuration',
        'source_structure_snapshot',
        'profiling_snapshot',
        'source_row_count',
        'uploaded_by_user_id',
        'uploaded_at',
        'profiled_at',
        'mapping_ready_at',
        'transformed_at',
        'failure_code',
        'failure_message',
    ];

    /*
     * La ruta privada del archivo nunca debe salir serializada
     * accidentalmente hacia una respuesta HTTP.
     */
    protected $hidden = [
        'source_path',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_intake_domain_delivery_id' =>
                'integer',

            'company_id' =>
                'integer',

            'source_size_bytes' =>
                'integer',

            'reader_configuration' =>
                'array',

            'source_structure_snapshot' =>
                'array',

            'profiling_snapshot' =>
                'array',

            'source_row_count' =>
                'integer',

            'uploaded_by_user_id' =>
                'integer',

            'uploaded_at' =>
                'datetime',

            'profiled_at' =>
                'datetime',

            'mapping_ready_at' =>
                'datetime',

            'transformed_at' =>
                'datetime',
        ];
    }

    public function domainDelivery(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeDomainDelivery::class,
            'data_transformation_bi_intake_domain_delivery_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id'
        );
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiSourceFieldMapping::class,
            'data_transformation_bi_source_domain_file_id'
        );
    }
}
