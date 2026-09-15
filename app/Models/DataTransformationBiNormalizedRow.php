<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiNormalizedRow extends Model
{
    protected $table =
        'data_transformation_bi_normalized_rows';

    protected $guarded = [];

    protected $hidden = [
        'normalized_payload',
        'canonical_identity_hash',
    ];

    protected function casts(): array
    {
        return [
            'normalized_payload' => 'array',
            'normalization_meta' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiProcessingRun::class,
            'data_transformation_bi_processing_run_id'
        );
    }

    public function sourceRow(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeRow::class,
            'data_transformation_bi_intake_row_id'
        );
    }
}
