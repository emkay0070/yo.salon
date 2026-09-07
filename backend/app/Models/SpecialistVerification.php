<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistVerification extends Model
{
    use HasUuids;

    protected $fillable = [
        'specialist_id',
        'status',
        'previous_status',
        'documents',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'status' => \App\Domain\Specialist\VerificationStatus::class,
        'previous_status' => \App\Domain\Specialist\VerificationStatus::class,
        'documents' => 'array',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function scopeForSpecialist($query, string $specialistId)
    {
        return $query->where('specialist_id', $specialistId);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
