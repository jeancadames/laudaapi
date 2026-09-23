<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataTransformationBiCanonicalRegistryVersion extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRED = 'retired';

    protected $table =
        'data_transformation_bi_canonical_registry_versions';

    protected $fillable = [
        'company_id',
        'source_transformation_implementation_request_id',
        'version',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'published_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'source_transformation_implementation_request_id' =>
                'integer',
            'version' => 'integer',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
            'published_by_user_id' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function entities(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiCanonicalEntity::class,
            'canonical_registry_version_id'
        );
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(
            DataTransformationBiCanonicalRelationship::class,
            'canonical_registry_version_id'
        );
    }
}
