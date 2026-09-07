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

#[Fillable(['name', 'email', 'password', 'status', 'notification_preferences', 'phone', 'photo_url'])]
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

    public function salons()
    {
        return $this->belongsToMany(Salon::class, 'salon_users')
            ->withPivot('role')
            ->withTimestamps()
            ->whereExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('salons')
                    ->whereColumn('salons.id', 'salon_users.salon_id');
            });
    }

    public function currentSalon()
    {
        // Simplest implementation for now: return the first salon associated with the user
        return $this->salons()->first();
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
        return $this->morphOne(Media::class, 'attachable')->where('alt_text', 'like', '%profile%')->latest();
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
