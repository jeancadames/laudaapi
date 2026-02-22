<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    use HasFactory;

    protected $table = 'api_idempotency_keys';

    protected $guarded = ['id'];

    protected $casts = [
        'response_headers' => 'array',
        'response_body'    => 'array',
        'response_code'    => 'integer',
        'locked_at'        => 'datetime',
        'completed_at'     => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
