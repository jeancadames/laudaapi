<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiSourceAssetFieldMapping extends Model
{
    public const TYPE_DIRECT = 'direct';
    public const TYPE_DEFAULT = 'default';
    public const TYPE_TRANSFORM = 'transform';
    public const TYPE_UNMAPPED = 'unmapped';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_BLOCKED = 'blocked';

    protected $table =
        'data_transformation_bi_source_asset_field_mappings';

    protected $fillable = [
        'data_transformation_bi_source_asset_mapping_id',
        'company_id',
        'canonical_field_key',
        'source_column_key',
        'source_column_index',
        'source_header',
        'mapping_type',
        'default_value',
        'transformation_key',
        'configuration_snapshot',
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
            'data_transformation_bi_source_asset_mapping_id' =>
                'integer',

            'company_id' =>
                'integer',

            'source_column_index' =>
                'integer',

            'configuration_snapshot' =>
                'array',

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

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiSourceAssetMapping::class,
            'data_transformation_bi_source_asset_mapping_id'
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

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id'
        );
    }
}
