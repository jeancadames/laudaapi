<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalParty extends Model
{
    use HasFactory;

    protected $table = 'fiscal_parties';

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
        'meta'   => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function documentsAsBuyer()
    {
        return $this->hasMany(FiscalDocument::class, 'buyer_party_id');
    }
}
