<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only. El servicio coteja cada evento con la proyección B1 y su cadena de hashes. */
final class DataBiDeclarationClarificationEvent extends Model
{
    protected $table = 'data_bi_declaration_clarification_events';

    protected $guarded = ['*'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['id' => 'integer', 'data_bi_declaration_clarification_id' => 'integer',
            'sequence' => 'integer', 'actor_user_id' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('No se permite editar eventos de aclaración.');
        });
        self::deleting(function (): void {
            throw new LogicException('No se permite eliminar eventos de aclaración.');
        });
    }

    public function clarification(): BelongsTo
    {
        return $this->belongsTo(DataBiDeclarationClarification::class, 'data_bi_declaration_clarification_id');
    }
}
