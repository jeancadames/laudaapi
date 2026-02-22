<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalDocumentEvent extends Model
{
    use HasFactory;

    protected $table = 'fiscal_document_events';

    protected $guarded = ['id'];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(FiscalDocument::class, 'document_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
