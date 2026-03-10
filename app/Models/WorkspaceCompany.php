<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceCompany extends Model
{
    protected $fillable = [
        'external_company_id',
        'subscriber_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
