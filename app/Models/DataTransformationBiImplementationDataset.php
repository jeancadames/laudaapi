<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiImplementationDataset extends Model
{
    public const STATUS_BUILDING = 'building';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $table =
        'data_transformation_bi_implementation_datasets';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'canonical_registry_version' =>
                'integer',

            'mapping_version' =>
                'integer',

            'source_profile_version' =>
                'integer',

            'source_sheet_index' =>
                'integer',

            'row_count' =>
                'integer',

            'materialized_by_user_id' =>
                'integer',

            'materialized_at' =>
                'datetime',
        ];
    }

    public function request(): BelongsTo
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

    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiSourceAsset::class,
            'data_transformation_bi_source_asset_id'
        );
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiSourceAssetFile::class,
            'data_transformation_bi_source_asset_file_id'
        );
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiSourceAssetMapping::class,
            'data_transformation_bi_source_asset_mapping_id'
        );
    }

    public function canonicalRegistryVersion(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalRegistryVersion::class,
            'data_transformation_bi_canonical_registry_version_id'
        );
    }

    public function materializedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'materialized_by_user_id'
        );
    }

    public function rows(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiImplementationRow::class,
            'data_transformation_bi_implementation_dataset_id'
        );
    }
}
