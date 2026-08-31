<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransformationCapabilityNeedEvaluation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DRAFT_GENERATED = 'draft_generated';
    public const STATUS_EVALUATED = 'evaluated';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DRAFT_GENERATED,
        self::STATUS_EVALUATED,
    ];

    /*
     * Resultados que el generador puede sugerir.
     *
     * insufficient_information es deliberadamente válido
     * únicamente como sugerencia automática.
     */
    public const SUGGESTED_REQUIRES_ATTENTION =
        'requires_attention';

    public const SUGGESTED_ADEQUATE =
        'adequate';

    public const SUGGESTED_NOT_APPLICABLE =
        'not_applicable';

    public const SUGGESTED_INSUFFICIENT_INFORMATION =
        'insufficient_information';

    public const SUGGESTED_RESULTS = [
        self::SUGGESTED_REQUIRES_ATTENTION,
        self::SUGGESTED_ADEQUATE,
        self::SUGGESTED_NOT_APPLICABLE,
        self::SUGGESTED_INSUFFICIENT_INFORMATION,
    ];

    /*
     * Decisiones finales permitidas al Admin.
     */
    public const RESULT_REQUIRES_ATTENTION =
        'requires_attention';

    public const RESULT_ADEQUATE =
        'adequate';

    public const RESULT_NOT_APPLICABLE =
        'not_applicable';

    public const RESULTS = [
        self::RESULT_REQUIRES_ATTENTION,
        self::RESULT_ADEQUATE,
        self::RESULT_NOT_APPLICABLE,
    ];

    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_LOW = 'low';

    public const PRIORITIES = [
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
    ];

    protected $fillable = [
        'transformation_capability_need_id',
        'status',

        'suggested_result',
        'suggested_findings',
        'suggested_recommendation',
        'suggested_priority',
        'suggested_questions',
        'generation_context',
        'generation_version',
        'generated_at',

        'result',
        'findings',
        'recommendation',
        'priority',
        'evaluated_by_user_id',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'suggested_questions' => 'array',
            'generation_context' => 'array',
            'generation_version' => 'integer',
            'generated_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(
            TransformationCapabilityNeed::class,
            'transformation_capability_need_id'
        );
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'evaluated_by_user_id'
        );
    }

    public function isEvaluated(): bool
    {
        return $this->status === self::STATUS_EVALUATED
            && $this->evaluated_at !== null;
    }
}
