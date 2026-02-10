<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'href',
        'roles',
        'icon',
        'badge',
        'required_plan',
        'parent_id',
        'type',
        'billable',
        'billing_model',
        'currency',
        'monthly_price',
        'yearly_price',
        'block_size',
        'included_units',
        'unit_name',
        'overage_unit_price',
        'description',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'roles' => 'array',
        'active' => 'boolean',
        'billable' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'overage_unit_price' => 'decimal:4',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Service::class, 'parent_id');
    }

    public function activationRequests(): BelongsToMany
    {
        return $this->belongsToMany(ActivationRequest::class, 'activation_request_service')
            ->withPivot(['meta', 'status'])
            ->withTimestamps();
    }

    public function subscriptionItems()
    {
        return $this->hasMany(SubscriptionItem::class, 'service_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeChildrenOf($query, $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'service_id');
    }
}
