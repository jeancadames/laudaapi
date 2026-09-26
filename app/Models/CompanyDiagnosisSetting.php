<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDiagnosisSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'new_requests_blocked' => 'boolean',
        'blocked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'blocked_by_user_id'
        );
    }
}
