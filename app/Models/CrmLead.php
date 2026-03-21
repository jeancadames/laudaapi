<?php

namespace App\Models;

use App\Models\CrmActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'business_name',
        'document_type',
        'document_number',
        'email',
        'phone',
        'mobile',
        'source',
        'status',
        'estimated_value',
        'score',
        'assigned_user_id',
        'created_by',
        'qualified_at',
        'converted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'score' => 'integer',
            'qualified_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }
}
