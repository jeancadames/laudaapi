<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObligationInstance extends Model
{
    use HasFactory;

    protected $table = 'obligation_instances';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start'          => 'date',
        'period_end'            => 'date',
        'due_date'              => 'date',
        'due_at'                => 'datetime',
        'filed_at'              => 'datetime',
        'paid_at'               => 'datetime',
        'completed_at'          => 'datetime',
        'external_refs'         => 'array',
        'meta'                  => 'array',
    ];

    public function tenantObligation()
    {
        return $this->belongsTo(TenantObligation::class, 'tenant_obligation_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function evidence()
    {
        return $this->hasMany(ObligationEvidence::class, 'instance_id');
    }
}
