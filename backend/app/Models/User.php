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

#[Fillable(['name', 'email', 'password', 'status'])]
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
        ];
    }

    public function salons()
    {
        return $this->belongsToMany(Salon::class, 'salon_users')->withPivot('role')->withTimestamps();
    }

    public function currentSalon()
    {
        // Simplest implementation for now: return the first salon associated with the user
        return $this->salons()->first();
    }

    public function onboardingSession()
    {
        return $this->hasOne(OnboardingSession::class);
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
