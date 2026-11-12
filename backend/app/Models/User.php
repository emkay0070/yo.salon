<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'status', 'notification_preferences', 'photo_url', 'my_view_timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public function providers()
    {
        return $this->belongsToMany(Provider::class, 'provider_users')
            ->withPivot('role', 'authority_scope')
            ->withTimestamps();
    }

    public function currentProvider()
    {
        // Simplest implementation for now: return the first provider associated with the user
        return $this->providers()->first();
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function onboardingSession()
    {
        return $this->hasOne(OnboardingSession::class);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'attachable');
    }

    public function profilePhoto()
    {
        // Query via uploaded_by since users have integer IDs but media.attachable_id is UUID
        return \App\Models\Media::where('uploaded_by', $this->id)
            ->where('alt_text', 'like', '%profile%')
            ->latest();
    }

    public function isOnboarding(): bool
    {
        return in_array($this->status, ['registered', 'email_verified', 'onboarding_started']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === 'onboarding_completed';
    }
}
