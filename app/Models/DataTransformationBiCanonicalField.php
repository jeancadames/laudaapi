<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiCanonicalField extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_TEXT = 'text';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';

    protected $table =
        'data_transformation_bi_canonical_fields';

    protected $fillable = [
        'canonical_entity_id',
        'field_key',
        'label',
        'data_type',
        'required',
        'is_identity',
        'description',
        'status',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'canonical_entity_id' => 'integer',
            'required' => 'boolean',
            'is_identity' => 'boolean',
            'sort_order' => 'integer',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiCanonicalEntity::class,
            'canonical_entity_id'
        );
    }
}
