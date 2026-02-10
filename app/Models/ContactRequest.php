<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'topic',
        'message',
        'terms',
        'metadata',
        'read_at',
    ];

    protected $casts = [
        'terms' => 'boolean',
        'metadata' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Relación 1:1 con ActivationRequest
     */
    public function activationRequest()
    {
        return $this->hasOne(ActivationRequest::class);
    }
}
