<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiCanonicalRelationship extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_ONE_TO_ONE = 'one_to_one';
    public const TYPE_ONE_TO_MANY = 'one_to_many';
    public const TYPE_MANY_TO_ONE = 'many_to_one';
    public const TYPE_MANY_TO_MANY = 'many_to_many';

    protected $table =
        'data_transformation_bi_canonical_relationships';

    protected $fillable = [
        'canonical_registry_version_id',
        'company_id',
        'from_canonical_entity_id',
        'from_canonical_field_id',
        'to_canonical_entity_id',
        'to_canonical_field_id',
        'relationship_type',
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
            'from_canonical_entity_id' => 'integer',
            'from_canonical_field_id' => 'integer',
            'to_canonical_entity_id' => 'integer',
            'to_canonical_field_id' => 'integer',
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

    public function fromEntity(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalEntity::class,
            'from_canonical_entity_id'
        );
    }

    public function fromField(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalField::class,
            'from_canonical_field_id'
        );
    }

    public function toEntity(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalEntity::class,
            'to_canonical_entity_id'
        );
    }

    public function toField(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalField::class,
            'to_canonical_field_id'
        );
    }
}
