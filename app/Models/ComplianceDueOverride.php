<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplianceDueOverride extends Model
{
    use HasFactory;

    protected $table = 'compliance_due_overrides';

    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'meta'     => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(ComplianceObligationTemplate::class, 'template_id');
    }
}
