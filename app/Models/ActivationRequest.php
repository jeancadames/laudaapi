<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ActivationRequest extends Model
{
    use HasFactory;

    // ✅ Estados (evitar typos)
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_TRIALING  = 'trialing';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_DISCARDED = 'discarded';

    /**
     * ✅ Regla de negocio:
     * "Solicitud activa" = bloquea crear otra solicitud.
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_TRIALING,
    ];

    /**
     * ✅ Estados que permiten acceder al panel subscriber.
     */
    public const ACCESS_ALLOWED_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_TRIALING,
        self::STATUS_CONVERTED,
    ];

    protected $fillable = [
        'contact_request_id',
        'user_id',
        'name',
        'company',
        'role',
        'email',
        'phone',
        'topic',
        'other_topic',
        'system',
        'volume',
        'message',
        'terms',
        'status',
        'trial_starts_at',
        'trial_ends_at',
        'trial_days',
        'metadata',
    ];

    protected $casts = [
        'terms' => 'boolean',
        'metadata' => 'array',
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'trial_days' => 30,
        'terms' => false,
    ];

    // -------------------------
    // Relations
    // -------------------------

    public function contactRequest(): BelongsTo
    {
        return $this->belongsTo(ContactRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'activation_request_service')
            ->using(ActivationRequestService::class)
            ->withPivot(['meta', 'status'])
            ->withTimestamps();
    }

    // -------------------------
    // Accessors
    // -------------------------

    public function getIsTrialActiveAttribute(): bool
    {
        if (!$this->trial_starts_at || !$this->trial_ends_at) {
            return false;
        }

        return now()->between($this->trial_starts_at, $this->trial_ends_at);
    }

    public function getTrialDaysLeftAttribute(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }

        return now()->diffInDays($this->trial_ends_at, false);
    }

    // -------------------------
    // Scopes
    // -------------------------

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', self::STATUS_TRIALING);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * ✅ "Activa" = pending/accepted/trialing
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * ✅ "AccessAllowed" = accepted/trialing/converted
     */
    public function scopeAccessAllowed($query)
    {
        return $query->whereIn('status', self::ACCESS_ALLOWED_STATUSES);
    }

    // -------------------------
    // Domain methods
    // -------------------------

    public function startTrial(int $days = 30): void
    {
        $this->trial_days = $days;
        $this->trial_starts_at = now();
        $this->trial_ends_at = now()->addDays($days);
        $this->status = self::STATUS_TRIALING;
        $this->save();
    }

    public function markExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->save();
    }

    /**
     * ✅ Devuelve la última solicitud que permite acceso (accepted/trialing/converted).
     */
    public static function latestAccessAllowedForUser(int $userId): ?self
    {
        return static::query()
            ->where('user_id', $userId)
            ->accessAllowed()
            ->latest('id')
            ->first();
    }

    /**
     * ✅ Expira cualquier PENDING cuyo link ya venció.
     * Fuente de verdad:
     * - metadata.activation_email_expires_at (si existe)
     * - fallback: created_at + 24h
     *
     * DB-agnostic: se evalúa en PHP para no depender de JSON_EXTRACT por motor.
     */
    public static function expireStalePendingForEmail(string $email): int
    {
        $email = strtolower(trim($email));
        $expiredCount = 0;

        $pendings = static::query()
            ->where('email', $email)
            ->where('status', self::STATUS_PENDING)
            ->orderByDesc('id')
            ->get(['id', 'created_at', 'metadata', 'status']);

        foreach ($pendings as $req) {
            $meta = $req->metadata ?? [];

            $expiresAt = null;

            if (!empty($meta['activation_email_expires_at'])) {
                try {
                    $expiresAt = Carbon::parse($meta['activation_email_expires_at']);
                } catch (\Throwable $e) {
                    $expiresAt = null;
                }
            }

            // fallback si metadata no existe o está mala
            $expiresAt ??= $req->created_at?->copy()->addHours(24);

            if ($expiresAt && $expiresAt->isPast()) {
                $req->status = self::STATUS_EXPIRED;
                $req->save();
                $expiredCount++;
            }
        }

        return $expiredCount;
    }
}
