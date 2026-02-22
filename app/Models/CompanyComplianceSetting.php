<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyComplianceSetting extends Model
{
    protected $table = 'company_compliance_settings';

    protected $fillable = [
        'company_id',
        'timezone',
        'weekend_shift',
        'use_holidays',
        'default_reminders',
        'channels',
        'meta',
    ];

    protected $casts = [
        'use_holidays' => 'bool',
        'default_reminders' => 'array',
        'channels' => 'array',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
