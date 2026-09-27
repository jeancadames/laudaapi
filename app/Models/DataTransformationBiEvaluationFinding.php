<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class DataTransformationBiEvaluationFinding extends Model
{
    public const TYPE_WEAKNESS =
        'weakness';

    public const TYPE_OPPORTUNITY =
        'opportunity';

    public const TYPE_OBSERVATION =
        'observation';

    public const TYPES = [
        self::TYPE_WEAKNESS,
        self::TYPE_OPPORTUNITY,
        self::TYPE_OBSERVATION,
    ];

    public const PRIORITY_HIGH =
        'high';

    public const PRIORITY_MEDIUM =
        'medium';

    public const PRIORITY_LOW =
        'low';

    public const PRIORITIES = [
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
    ];

    protected $fillable = [
        'data_transformation_bi_evaluation_id',
        'company_id',
        'finding_type',
        'title',
        'details',
        'recommendation',
        'priority',
        'evidence_version',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data_transformation_bi_evaluation_id' =>
                'integer',

            'company_id' =>
                'integer',

            'evidence_version' =>
                'integer',

            'sort_order' =>
                'integer',

            'created_by_user_id' =>
                'integer',

            'updated_by_user_id' =>
                'integer',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(
            DataTransformationBiEvaluation::class,
            'data_transformation_bi_evaluation_id'
        );
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            DataTransformationBiSourceAsset::class,
            'data_transformation_bi_evaluation_finding_sources',
            'data_transformation_bi_evaluation_finding_id',
            'data_transformation_bi_source_asset_id'
        )->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id'
        );
    }
}
