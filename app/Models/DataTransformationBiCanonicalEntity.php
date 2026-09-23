<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiCanonicalEntity extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $table =
        'data_transformation_bi_canonical_entities';

    protected $fillable = [
        'canonical_registry_version_id',
        'company_id',
        'entity_key',
        'label',
        'description',
        'status',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'canonical_registry_version_id' => 'integer',
            'company_id' => 'integer',
            'sort_order' => 'integer',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    public function registryVersion(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalRegistryVersion::class,
            'canonical_registry_version_id'
        );
    }

    public function fields(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiCanonicalField::class,
            'canonical_entity_id'
        );
    }
}
