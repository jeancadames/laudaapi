<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiImplementationRow extends Model
{
    protected $table =
        'data_transformation_bi_implementation_rows';

    protected $guarded = [];

    /*
     * Canonical business values remain private by default just like the
     * historical normalized payload. Explicit readers may expose bounded,
     * authorized projections later.
     */
    protected $hidden = [
        'canonical_payload',
        'source_row_sha256',
        'canonical_payload_sha256',
    ];

    protected function casts(): array
    {
        return [
            'source_row_number' =>
                'integer',

            'canonical_payload' =>
                'array',

            'projection_meta' =>
                'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiImplementationDataset::class,
            'data_transformation_bi_implementation_dataset_id'
        );
    }
}
