<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarFeed extends Model
{
    use HasFactory;

    protected $table = 'calendar_feeds';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled'          => 'boolean',
        'expires_at'       => 'datetime',
        'last_rotated_at'  => 'datetime',
        'last_accessed_at' => 'datetime',
        'meta'             => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
