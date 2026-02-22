<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ObligationEvidence extends Model
{
    use HasFactory;

    protected $table = 'obligation_evidence';

    protected $guarded = ['id'];

    protected $casts = [
        'size' => 'integer',
        'meta' => 'array',
    ];

    public function instance()
    {
        return $this->belongsTo(ObligationInstance::class, 'instance_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
