<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransformationBiIntakeRow extends Model
{
    protected $table =
        'data_transformation_bi_intake_rows';

    protected $fillable = [
        'data_transformation_bi_intake_batch_id',
        'company_id',
        'domain_key',
        'source_row_number',
        'identity_hash',
        'row_sha256',
        'row_payload',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_intake_batch_id' =>
                'integer',

            'company_id' =>
                'integer',

            'source_row_number' =>
                'integer',

            'row_payload' =>
                'array',
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
