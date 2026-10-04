<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Human-authored implementation implication derived from the
 * professional evaluation of one Data BI delivery.
 *
 * This is deliberately separate from evaluation findings:
 *
 * - findings describe diagnostic conclusions;
 * - implementation challenges describe professional implications
 *   that must be considered during a future implementation.
 *
 * Challenges are never generated automatically by diagnostic
 * analysis and do not represent a readiness, risk or confidence
 * score.
 */
final class DataTransformationBiEvaluationImplementationChallenge
    extends Model
{
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

    protected $table =
        'data_transformation_bi_evaluation_implementation_challenges';

    protected $fillable = [
        'data_transformation_bi_evaluation_id',
        'company_id',
        'title',
        'details',
        'recommended_response',
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

    /**
     * Optional professional traceability.
     *
     * A challenge may be supported by zero, one or several already
     * human-authored findings from the same evaluation.
     *
     * The application layer will validate evaluation/evidence
     * consistency before these links can be persisted.
     */
    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(
            DataTransformationBiEvaluationFinding::class,
            'data_transformation_bi_impl_challenge_findings',
            'data_transformation_bi_evaluation_implementation_challenge_id',
            'data_transformation_bi_evaluation_finding_id'
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
