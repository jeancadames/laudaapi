<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Proyección mutable únicamente mediante una transición validada; identidad inmutable. */
final class DataBiDeclarationClarification extends Model
{
    protected $table = 'data_bi_declaration_clarifications';

    protected $guarded = ['*'];

    // B1 necesita el ID para firmar antes del INSERT: entero aleatorio generado por el servidor.
    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['id' => 'integer', 'transformation_implementation_request_id' => 'integer',
            'company_id' => 'integer', 'data_bi_information_request_id' => 'integer',
            'data_bi_tenant_declaration_id' => 'integer', 'revision' => 'integer',
            'clarification_snapshot' => 'array', 'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $row): void {
            if ($row->isDirty(['id', 'transformation_implementation_request_id', 'company_id',
                'data_bi_information_request_id', 'data_bi_tenant_declaration_id', 'requirement_key',
                'question_sha256', 'created_by_user_id', 'created_at'])
                || in_array($row->getRawOriginal('status'), ['resolved', 'cancelled'], true)
                || $row->revision !== (int) $row->getRawOriginal('revision') + 1) {
                throw new LogicException('Identidad e historial de aclaración inmutables; requiere una nueva transición.');
            }
        });
        self::deleting(function (): void {
            throw new LogicException('Las aclaraciones conservan su historia; no se eliminan.');
        });
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(DataBiTenantDeclaration::class, 'data_bi_tenant_declaration_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DataBiDeclarationClarificationEvent::class, 'data_bi_declaration_clarification_id')->orderBy('sequence');
    }
}
