<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileRecommendation extends Model
{
    protected $fillable = [
        'customer_id',
        'assessment_id',
        'specialist_id',
        'field',
        'current_value',
        'recommended_value',
        'reason',
        'confidence_level',
        'status',
        'expires_at',
        'responded_at',
        'customer_feedback',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'uuid';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessment()
    {
        return $this->belongsTo(ProfessionalAssessment::class);
    }

    public function specialist()
    {
        return $this->belongsTo(Staff::class, 'specialist_id');
    }

    public function scopeByCustomer($query, string $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function markAsAccepted(string $feedback = null)
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
            'customer_feedback' => $feedback,
        ]);
    }

    public function markAsRejected(string $feedback = null)
    {
        $this->update([
            'status' => 'rejected',
            'responded_at' => now(),
            'customer_feedback' => $feedback,
        ]);
    }

    public function markAsExpired()
    {
        $this->update([
            'status' => 'expired',
        ]);
    }
}
