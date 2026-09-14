<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiIntakeBatchDomain extends Model
{
    public const STATUS_PENDING =
        'pending';

    public const STATUS_PROCESSING =
        'processing';

    public const STATUS_COMPLETED =
        'completed';

    public const STATUS_FAILED =
        'failed';

    protected $table =
        'data_transformation_bi_intake_batch_domains';

    protected $fillable = [
        'data_transformation_bi_intake_batch_id',
        'company_id',
        'domain_key',
        'status',
        'source_row_count',
        'staged_row_count',
        'rejected_row_count',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_intake_batch_id' =>
                'integer',

            'company_id' =>
                'integer',

            'source_row_count' =>
                'integer',

            'staged_row_count' =>
                'integer',

            'rejected_row_count' =>
                'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiIntakeBatch::class,
            'data_transformation_bi_intake_batch_id'
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }
}
