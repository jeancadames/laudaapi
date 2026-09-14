<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiDomainProfile extends Model
{
    protected $table =
        'data_transformation_bi_domain_profiles';

    protected $guarded = [];

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiProcessingRun::class,
            'data_transformation_bi_processing_run_id'
        );
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeBatch::class,
            'data_transformation_bi_intake_batch_id'
        );
    }
}
