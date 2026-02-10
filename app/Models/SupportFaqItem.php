<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFaqItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'meta' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportFaqCategory::class, 'category_id');
    }
}
