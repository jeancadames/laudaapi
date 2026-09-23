<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiSourceAssetFile extends Model
{
    public const STATUS_UPLOADED =
        'uploaded';

    public const STATUS_FAILED =
        'failed';

    public const FORMAT_CSV =
        'csv';

    public const FORMAT_XLSX =
        'xlsx';

    protected $table =
        'data_transformation_bi_source_asset_files';

    protected $fillable = [
        'data_transformation_bi_source_asset_id',
        'company_id',
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
        'source_row_count',
        'uploaded_by_user_id',
        'uploaded_at',
        'failure_code',
        'failure_message',
    ];

    /*
     * Private server path must never leak through
     * accidental model serialization.
     */
    protected $hidden = [
        'source_disk',
        'source_path',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_source_asset_id' =>
                'integer',

            'company_id' =>
                'integer',

            'source_size_bytes' =>
                'integer',

            'reader_configuration' =>
                'array',

            'source_structure_snapshot' =>
                'array',

            'source_row_count' =>
                'integer',

            'uploaded_by_user_id' =>
                'integer',

            'uploaded_at' =>
                'datetime',
        ];
    }

    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiSourceAsset::class,
            'data_transformation_bi_source_asset_id'
        );
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiSourceAssetMapping::class,
            'data_transformation_bi_source_asset_file_id'
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
}
