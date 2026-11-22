<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'status', 'notification_preferences', 'photo_url', 'my_view_timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The "booted" method of the model.
     *
     * Invalidate email verification state whenever an existing user's email address is modified.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function (User $user) {
            if ($user->exists && $user->isDirty('email') && !$user->isDirty('email_verified_at')) {
                $user->email_verified_at = null;
                if ($user->status === 'email_verified') {
                    $user->status = 'registered';
                }
            }
        });
    }

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
        // Deterministic provider selection: return the most recently established
        // provider relationship. Avoids non-deterministic first() without orderBy.
        return $this->providers()->orderBy('provider_users.created_at', 'desc')->first();
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

    public function organizationMemberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    public function ownedOrganizations()
    {
        return $this->hasMany(Organization::class, 'owner_user_id');
    }

    public function currentOrganization(): ?Organization
    {
        return $this->currentProvider()?->organization;
    }

    /**
     * Resolve the user's platform governance role.
     */
    public function platformRole(): ?string
    {
        return app(\App\Services\PlatformAuthorizationService::class)->resolvePlatformRole($this);
    }

    /**
     * Check if user possesses one or more platform governance roles.
     */
    public function hasPlatformRole(string|array $roles): bool
    {
        return app(\App\Services\PlatformAuthorizationService::class)->hasPlatformRole($this, $roles);
    }

    /**
     * Check if user is Platform Owner.
     */
    public function isPlatformOwner(): bool
    {
        return $this->hasPlatformRole(\App\Domain\Platform\PlatformRole::PLATFORM_OWNER);
    }

    /**
     * Check if user is Platform Super Admin.
     */
    public function isPlatformSuperAdmin(): bool
    {
        return $this->hasPlatformRole(\App\Domain\Platform\PlatformRole::PLATFORM_SUPER_ADMIN);
    }

    /**
     * Check if user is a Developer.
     */
    public function isDeveloper(): bool
    {
        return $this->hasPlatformRole(\App\Domain\Platform\PlatformRole::DEVELOPER);
    }

    /**
     * Check if user is Support.
     */
    public function isSupport(): bool
    {
        return $this->hasPlatformRole(\App\Domain\Platform\PlatformRole::SUPPORT);
    }

    /**
     * Check if user has high-level platform administrative privileges (Owner or Super Admin).
     */
    public function isPlatformAdmin(): bool
    {
        return $this->hasPlatformRole([
            \App\Domain\Platform\PlatformRole::PLATFORM_OWNER,
            \App\Domain\Platform\PlatformRole::PLATFORM_SUPER_ADMIN,
        ]);
    }
}
