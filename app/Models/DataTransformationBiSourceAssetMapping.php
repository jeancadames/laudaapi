<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiSourceAssetMapping extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_STALE = 'stale';

    protected $table =
        'data_transformation_bi_source_asset_mappings';

    protected $fillable = [
        'data_transformation_bi_source_asset_id',
        'data_transformation_bi_source_asset_file_id',
        'data_transformation_bi_intake_session_id',
        'company_id',
        'canonical_entity_key',
        'canonical_registry_version',
        'source_sha256',
        'source_profile_version',
        'source_sheet_index',
        'source_sheet_name',
        'mapping_version',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'validated_by_user_id',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_source_asset_id' =>
                'integer',

            'data_transformation_bi_source_asset_file_id' =>
                'integer',

            'data_transformation_bi_intake_session_id' =>
                'integer',

            'company_id' =>
                'integer',

            'canonical_registry_version' =>
                'integer',

            'source_profile_version' =>
                'integer',

            'source_sheet_index' =>
                'integer',

            'mapping_version' =>
                'integer',

            'created_by_user_id' =>
                'integer',

            'updated_by_user_id' =>
                'integer',

            'validated_by_user_id' =>
                'integer',

            'validated_at' =>
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

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiSourceAssetFile::class,
            'data_transformation_bi_source_asset_file_id'
        );
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

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiSourceAssetFieldMapping::class,
            'data_transformation_bi_source_asset_mapping_id'
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

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id'
        );
    }
}
