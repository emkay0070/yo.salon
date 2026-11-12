<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingResolution extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'booking_id',
        'outcome',
        'reason_code',
        'notes',
        'actor_type',
        'actor_id',
        'resolved_at',
        'follow_up_required',
        'follow_up_at',
        'follow_up_status',
        'follow_up_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'follow_up_required' => 'boolean',
    ];

    /**
     * The booking this resolution belongs to.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope to filter by outcome.
     */
    public function scopeWithOutcome($query, string $outcome)
    {
        return $query->where('outcome', $outcome);
    }

    /**
     * Scope to filter by follow-up status.
     */
    public function scopeWithFollowUpStatus($query, ?string $status)
    {
        if ($status === null) {
            return $query->whereNull('follow_up_status');
        }
        return $query->where('follow_up_status', $status);
    }

    /**
     * Scope to get resolutions requiring follow-up.
     */
    public function scopeRequiresFollowUp($query)
    {
        return $query->where('follow_up_required', true);
    }
}
