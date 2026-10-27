<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versiones inmutables de borradores declarativos (no son intake ni Definition V1). */
final class DataBiInformationRequest extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent'; // reservado para la futura acción de envío

    protected $table = 'data_bi_information_requests';

    protected $fillable = [
        'transformation_implementation_request_id',
        'company_id',
        'version',
        'status',
        'review_snapshot',
        'content_sha256',
        'created_by_user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'company_id' => 'integer',
            'transformation_implementation_request_id' => 'integer',
            'created_by_user_id' => 'integer',
            'review_snapshot' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function implementationRequest(): BelongsTo
    {
        return $this->belongsTo(TransformationImplementationRequest::class, 'transformation_implementation_request_id');
    }
}
