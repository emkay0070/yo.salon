<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWorkspaceInvitation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'specialist_id',
        'target_provider_id',
        'source_provider_id',
        'channel',
        'status',
        'sent_at',
        'opened_at',
        'accepted_at',
        'declined_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Invitation channels
     */
    const CHANNEL_SPECIALIST_DIRECT = 'specialist_direct_invite';
    const CHANNEL_PLATFORM_SUGGESTION = 'platform_suggestion';
    const CHANNEL_REFERRAL = 'referral';
    const CHANNEL_DIRECT_LINK = 'direct_link';

    /**
     * Invitation statuses
     */
    const STATUS_SENT = 'sent';
    const STATUS_OPENED = 'opened';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_EXPIRED = 'expired';

    /**
     * The customer who received the invitation
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The specialist who sent the invitation
     */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    /**
     * The target provider/workspace (where the customer is invited to)
     */
    public function targetProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'target_provider_id');
    }

    /**
     * The source provider/workspace (where the relationship currently exists, if any)
     */
    public function sourceProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'source_provider_id');
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if invitation can be accepted
     */
    public function canBeAccepted(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_OPENED]) && !$this->isExpired();
    }

    /**
     * Mark invitation as opened
     */
    public function markAsOpened(): void
    {
        if ($this->status === self::STATUS_SENT) {
            $this->update([
                'status' => self::STATUS_OPENED,
                'opened_at' => now(),
            ]);
        }
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(): void
    {
        if ($this->canBeAccepted()) {
            $this->update([
                'status' => self::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);
        }
    }

    /**
     * Mark invitation as declined
     */
    public function markAsDeclined(): void
    {
        if ($this->canBeAccepted()) {
            $this->update([
                'status' => self::STATUS_DECLINED,
                'declined_at' => now(),
            ]);
        }
    }

    /**
     * Mark invitation as expired
     */
    public function markAsExpired(): void
    {
        if (in_array($this->status, [self::STATUS_SENT, self::STATUS_OPENED])) {
            $this->update([
                'status' => self::STATUS_EXPIRED,
            ]);
        }
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_OPENED])
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope for invitations to a specific provider
     */
    public function scopeToProvider($query, string $providerId)
    {
        return $query->where('target_provider_id', $providerId);
    }

    /**
     * Scope for invitations from a specific specialist
     */
    public function scopeFromSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }
}
