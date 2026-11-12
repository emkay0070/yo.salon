<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingReminder extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'booking_id',
        'reminder_rule_id',
        'recipient_customer_id',
        'type',
        'channel',
        'scheduled_for',
        'scheduled_at',
        'notification_job_id',
        'status',
        'sent_at',
        'failed_at',
        'cancelled_at',
        'attempts',
        'last_error',
        'data',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'data' => 'array',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Type constants
    const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';

    // Channel constants
    const CHANNEL_SMS = 'sms';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_IN_APP = 'in_app';

    /**
     * The booking this reminder is for
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The customer receiving the reminder
     */
    public function recipientCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'recipient_customer_id');
    }

    /**
     * The notification job for delivery
     */
    public function notificationJob(): BelongsTo
    {
        return $this->belongsTo(NotificationJob::class, 'notification_job_id');
    }

    /**
     * Scope for pending reminders
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for reminders that are due (scheduled_for <= now)
     */
    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('scheduled_for', '<=', now());
    }

    /**
     * Scope for reminders by booking
     */
    public function scopeForBooking($query, string $bookingId)
    {
        return $query->where('booking_id', $bookingId);
    }

    /**
     * Scope for reminders by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark as scheduled (notification job created)
     */
    public function markAsScheduled(string $notificationJobId): void
    {
        $this->update([
            'status' => self::STATUS_SCHEDULED,
            'notification_job_id' => $notificationJobId,
            'scheduled_at' => now(),
        ]);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'last_error' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Check if reminder is cancellable
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SCHEDULED]);
    }
}
