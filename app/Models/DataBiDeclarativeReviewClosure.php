<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Un cierre inmutable por declaración. Una publicación posterior no lo hereda. */
final class DataBiDeclarativeReviewClosure extends Model
{
    protected $table = 'data_bi_declarative_review_closures';

    protected $guarded = ['*'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['id' => 'integer', 'transformation_implementation_request_id' => 'integer', 'company_id' => 'integer',
            'data_bi_information_request_id' => 'integer', 'data_bi_tenant_declaration_id' => 'integer',
            'declared_gaps' => 'array', 'declared_limitations' => 'array', 'clarification_references' => 'array',
            'closure_snapshot' => 'array', 'closed_by_user_id' => 'integer', 'closed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException('El cierre declarativo es inmutable.');
        });
        self::deleting(function (): void {
            throw new LogicException('El cierre declarativo histórico no se elimina.');
        });
    }

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(DataBiTenantDeclaration::class, 'data_bi_tenant_declaration_id');
    }
}
