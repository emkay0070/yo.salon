<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Organization represents the commercial and membership owner in TYBUEL.
 *
 * Hierarchy:
 * TYBUEL Platform -> Organizations -> Providers -> Locations
 *
 * Organization owns:
 * - Organizational identity and profile
 * - Organizational membership (governance)
 * - Commercial configuration
 * - Subscription and billing relationships
 * - Organization-level administration and permissions
 * - Relationships to its operational Providers
 *
 * Dual-principal ownership: either a User (business owner) or a SpecialistAccount
 * (independent specialist) may be the designated owner. Exactly one must be set.
 * Enforced at the database level via chk_organization_designated_owner.
 */
class Organization extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    const TYPE_BUSINESS = 'business';
    const TYPE_PERSONAL = 'personal';

    const STATUS_ACTIVE    = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_INACTIVE  = 'inactive';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'email',
        'phone',
        'description',
        'metadata',
        'owner_user_id',
        'owner_specialist_account_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The User who is the designated owner of this Organization.
     * Null when owned by a SpecialistAccount.
     */
    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * The SpecialistAccount who is the designated owner of this Organization.
     * Null when owned by a User.
     */
    public function ownerSpecialistAccount(): BelongsTo
    {
        return $this->belongsTo(SpecialistAccount::class, 'owner_specialist_account_id');
    }

    /**
     * All governance memberships within this Organization.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Users who are members of this Organization (via organization_memberships).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships', 'organization_id', 'user_id')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }

    /**
     * Operational Providers belonging to this commercial Organization.
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    /**
     * Subscriptions owned by this Organization.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Returns the designated owner principal regardless of type.
     * Always returns exactly one of User or SpecialistAccount.
     */
    public function getOwnerPrincipalAttribute(): Model|null
    {
        return $this->ownerUser ?? $this->ownerSpecialistAccount;
    }

    // -------------------------------------------------------------------------
    // Type helpers
    // -------------------------------------------------------------------------

    public function isPersonal(): bool
    {
        return $this->type === self::TYPE_PERSONAL;
    }

    public function isBusiness(): bool
    {
        return $this->type === self::TYPE_BUSINESS;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isUserOwned(): bool
    {
        return $this->owner_user_id !== null;
    }

    public function isSpecialistOwned(): bool
    {
        return $this->owner_specialist_account_id !== null;
    }

    // -------------------------------------------------------------------------
    // Authorization helpers (dual-principal aware)
    // -------------------------------------------------------------------------

    /**
     * Check whether a User or SpecialistAccount is the designated owner.
     */
    public function hasOwner(User|SpecialistAccount $principal): bool
    {
        if ($principal instanceof User) {
            return $this->owner_user_id === $principal->id ||
                $this->memberships()
                    ->where('user_id', $principal->id)
                    ->where('role', OrganizationMembership::ROLE_OWNER)
                    ->where('status', OrganizationMembership::STATUS_ACTIVE)
                    ->exists();
        }

        // SpecialistAccount
        return $this->owner_specialist_account_id === $principal->id ||
            $this->memberships()
                ->where('specialist_account_id', $principal->id)
                ->where('role', OrganizationMembership::ROLE_OWNER)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->exists();
    }

    /**
     * Check whether a User or SpecialistAccount has admin or owner authority.
     */
    public function hasAdmin(User|SpecialistAccount $principal): bool
    {
        if ($this->hasOwner($principal)) {
            return true;
        }

        if ($principal instanceof User) {
            return $this->memberships()
                ->where('user_id', $principal->id)
                ->where('role', OrganizationMembership::ROLE_ADMIN)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->exists();
        }

        return $this->memberships()
            ->where('specialist_account_id', $principal->id)
            ->where('role', OrganizationMembership::ROLE_ADMIN)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Check whether a User or SpecialistAccount is an active member.
     */
    public function hasMember(User|SpecialistAccount $principal): bool
    {
        if ($principal instanceof User) {
            return $this->memberships()
                ->where('user_id', $principal->id)
                ->where('status', OrganizationMembership::STATUS_ACTIVE)
                ->exists();
        }

        return $this->memberships()
            ->where('specialist_account_id', $principal->id)
            ->where('status', OrganizationMembership::STATUS_ACTIVE)
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBusiness($query)
    {
        return $query->where('type', self::TYPE_BUSINESS);
    }

    public function scopePersonal($query)
    {
        return $query->where('type', self::TYPE_PERSONAL);
    }
}
