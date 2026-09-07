<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SpecialistAccount extends Authenticatable
{
    use HasApiTokens, Notifiable, HasUuids;

    protected $fillable = [
        'id',
        'specialist_id',
        'email',
        'password',
        'phone',
        'is_active',
        'preferences',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'preferences' => 'array',
        'onboarding_completed_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'uuid';

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function updateLastLogin()
    {
        $this->update(['last_login_at' => now()]);
    }
}
