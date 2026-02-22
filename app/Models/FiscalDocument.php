<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class FiscalDocument extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'fiscal_documents';

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date'          => 'date',
        'due_date'            => 'date',
        'received_at'         => 'datetime',
        'issued_at'           => 'datetime',
        'voided_at'           => 'datetime',

        'exchange_rate'       => 'decimal:6',
        'subtotal'            => 'decimal:2',
        'discount_total'      => 'decimal:2',
        'tax_total'           => 'decimal:2',
        'grand_total'         => 'decimal:2',
        'balance_due'         => 'decimal:2',

        'payload'             => 'array',
        'meta'                => 'array',
        'version'             => 'integer',
    ];

    /**
     * HasUlids: indica que el ULID es public_id (no el PK).
     */
    public function uniqueIds()
    {
        return ['public_id'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function documentType()
    {
        return $this->belongsTo(FiscalDocumentType::class, 'document_type_id');
    }

    public function dgiiSequence()
    {
        return $this->belongsTo(DgiiDocumentSequence::class, 'dgii_sequence_id');
    }

    public function buyerParty()
    {
        return $this->belongsTo(FiscalParty::class, 'buyer_party_id');
    }

    public function parent()
    {
        return $this->belongsTo(FiscalDocument::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(FiscalDocument::class, 'parent_id');
    }

    public function events()
    {
        return $this->hasMany(FiscalDocumentEvent::class, 'document_id')
            ->orderBy('occurred_at');
    }
}
