<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrganizationMembership represents a person's membership and authority within
 * an Organization (commercial governance layer).
 *
 * Distinct from ProviderUser, which represents operational assignment to a Provider.
 *
 * Dual-principal: a membership record references EITHER a User OR a SpecialistAccount,
 * never both, never neither. This is enforced at the database level by the
 * chk_membership_principal constraint.
 */
class OrganizationMembership extends Model
{
    use HasFactory, HasUuids;

    const ROLE_OWNER  = 'org_owner';
    const ROLE_ADMIN  = 'org_admin';
    const ROLE_MEMBER = 'org_member';

    const STATUS_ACTIVE    = 'active';
    const STATUS_INVITED   = 'invited';
    const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'organization_id',
        'user_id',
        'specialist_account_id',
        'role',
        'status',
        'invited_at',
        'accepted_at',
    ];

    protected $casts = [
        'invited_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The User principal of this membership. Null for specialist memberships.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The SpecialistAccount principal of this membership. Null for user memberships.
     */
    public function specialistAccount(): BelongsTo
    {
        return $this->belongsTo(SpecialistAccount::class, 'specialist_account_id');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Returns the authenticated principal regardless of type (User or SpecialistAccount).
     */
    public function getPrincipalAttribute(): Model|null
    {
        return $this->user ?? $this->specialistAccount;
    }

    // -------------------------------------------------------------------------
    // Type helpers
    // -------------------------------------------------------------------------

    public function isUser(): bool
    {
        return $this->user_id !== null;
    }

    public function isSpecialist(): bool
    {
        return $this->specialist_account_id !== null;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
