<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationJob extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'type',
        'recipient',
        'subject',
        'message',
        'data',
        'status',
        'attempts',
        'scheduled_at',
        'sent_at',
        'error_message',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            });
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed')
            ->where('attempts', '<', 3);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
        ]);
    }
}
