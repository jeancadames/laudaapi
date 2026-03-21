<?php

namespace App\Models;

use App\Models\CrmActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'crm_customer_id',
        'first_name',
        'last_name',
        'full_name',
        'position',
        'department',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'status',
        'assigned_user_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CrmContact $contact) {
            $fullName = trim(
                collect([$contact->first_name, $contact->last_name])
                    ->filter()
                    ->implode(' ')
            );

            $contact->full_name = $fullName !== '' ? $fullName : null;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'crm_customer_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }
}
