<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** D2F-T4C. Una declaración vigente por cuestionario; la revisión no es su versión. */
final class DataBiTenantDeclaration extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    protected $table = 'data_bi_tenant_declarations';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'data_bi_information_request_id' => 'integer',
            'company_id' => 'integer',
            'revision' => 'integer',
            'declaration_snapshot' => 'array',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $declaration): void {
            if ($declaration->getRawOriginal('status') === self::STATUS_SUBMITTED
                || $declaration->isDirty(['data_bi_information_request_id', 'company_id', 'created_by_user_id'])) {
                throw new LogicException('La declaración enviada y su identidad son inmutables.');
            }
        });
        static::deleting(function (): void {
            throw new LogicException('Las declaraciones conservan su histórico; no se eliminan.');
        });
    }

    public function informationRequest(): BelongsTo
    {
        return $this->belongsTo(DataBiInformationRequest::class, 'data_bi_information_request_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
