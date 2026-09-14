<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiQualityIssue extends Model
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_BLOCKING = 'blocking';

    protected $table =
        'data_transformation_bi_quality_issues';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
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
