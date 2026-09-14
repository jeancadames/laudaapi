<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiFieldProfile extends Model
{
    protected $table =
        'data_transformation_bi_field_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'metrics' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiProcessingRun::class,
            'data_transformation_bi_processing_run_id'
        );
    }
}
