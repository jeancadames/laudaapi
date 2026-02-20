<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DgiiDocumentSequence extends Model
{
    protected $table = 'dgii_document_sequences';

    protected $fillable = [
        'company_id',
        'environment',
        'document_class',
        'document_type',
        'series',
        'start_number',
        'end_number',
        'last_number',
        'status',
        'expires_at',
        'lock_version',
        'last_issued_at',
        'meta',
    ];

    protected $casts = [
        'start_number'  => 'int',
        'end_number'    => 'int',
        'last_number'   => 'int',
        'lock_version'  => 'int',
        'expires_at'    => 'datetime',
        'last_issued_at' => 'datetime',
        'meta'          => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
