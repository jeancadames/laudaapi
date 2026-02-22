<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplianceObligationTemplate extends Model
{
    use HasFactory;

    protected $table = 'compliance_obligation_templates';

    protected $guarded = ['id'];

    protected $casts = [
        'due_rule'             => 'array',
        'applicability_rule'   => 'array',
        'default_reminders'    => 'array',
        'active'               => 'boolean',
        'version'              => 'integer',
        'effective_from'       => 'date',
        'effective_to'         => 'date',
        'meta'                 => 'array',
    ];

    public function authority()
    {
        return $this->belongsTo(TaxAuthority::class, 'authority_id');
    }

    public function tenantObligations()
    {
        return $this->hasMany(TenantObligation::class, 'template_id');
    }

    public function dueOverrides()
    {
        return $this->hasMany(ComplianceDueOverride::class, 'template_id');
    }
}
