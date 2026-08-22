<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DiagnosisAccessRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_MORE_INFO_REQUIRED = 'more_info_required';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_INVITED = 'invited';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_MORE_INFO_REQUIRED,
        self::STATUS_APPROVED,
        self::STATUS_INVITED,
        self::STATUS_ACTIVE,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'public_id',
        'contact_request_id',
        'user_id',
        'diagnosis_assessment_id',
        'reviewed_by_user_id',
        'status',
        'review_notes',
        'rejection_reason',
        'approved_at',
        'invitation_sent_at',
        'invitation_expires_at',
        'invitation_accepted_at',
        'rejected_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(DiagnosisAssessment::class, 'diagnosis_assessment_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function canResendInvitation(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_INVITED,
            self::STATUS_ACTIVE,
        ], true) && $this->user_id && $this->diagnosis_assessment_id;
    }
}
