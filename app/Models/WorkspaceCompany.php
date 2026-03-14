<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceCompany extends Model
{
    protected $fillable = [
        'external_company_id',
        'subscriber_id',
        'name',
        'slug',
        'mode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'workspace_company_id');
    }
}
