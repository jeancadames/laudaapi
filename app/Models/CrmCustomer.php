<?php

namespace App\Models;

use App\Models\CrmContact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCustomer extends Model
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
        'industry',
        'source',
        'status',
        'address',
        'city',
        'region',
        'country',
        'assigned_user_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [];
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

    public function contacts()
    {
        return $this->hasMany(CrmContact::class, 'crm_customer_id');
    }

    public function opportunities()
    {
        return $this->hasMany(CrmOpportunity::class, 'crm_customer_id');
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class, 'crm_customer_id');
    }
}
