<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Selección histórica de un reto para futura revisión de planificación.
 * NO implica aprobación comercial ni inicio de ejecución.
 */
final class DataTransformationBiImplementationCandidate extends Model
{
    protected $table = 'data_transformation_bi_implementation_candidates';

    protected $fillable = [
        'company_id',
        'origin_request_id', 'origin_session_id', 'origin_evaluation_id', 'origin_challenge_id',
        'request_ref_id', 'session_ref_id', 'evaluation_ref_id', 'challenge_ref_id',
        'evidence_version', 'selected_by_user_id', 'selected_actor_id', 'selected_at',
        'origin_snapshot', 'origin_sha256',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'origin_request_id' => 'integer',
            'origin_session_id' => 'integer',
            'origin_evaluation_id' => 'integer',
            'origin_challenge_id' => 'integer',
            'evidence_version' => 'integer',
            'selected_actor_id' => 'integer',
            'selected_at' => 'immutable_datetime',
            'origin_snapshot' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }
}
