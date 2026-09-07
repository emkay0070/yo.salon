<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Carbon\CarbonInterface;

class Invitation extends Model
{
    use HasFactory, HasUuids;

    public const ROLE_STAFF = 'STAFF';
    public const ROLE_CUSTOMER = 'CUSTOMER';
    public const ROLE_ADMIN = 'ADMIN';

    protected $fillable = [
        'salon_id',
        'email',
        'role',
        'target_id',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'accepted_at'   => 'datetime',
    ];

    protected $hidden = [];

    // ── Factory ────────────────────────────────────────────────────────

    /**
     * Issue a brand-new invitation.
     * Token is stored as SHA-256 of a random string; raw token is what goes in the URL.
     *
     * @return array{invitation: Invitation, rawToken: string}
     */
    public static function issue(array $attrs, ?CarbonInterface $expiresAt = null): array
    {
        $rawToken = Str::random(64);
        $attrs['token'] = hash('sha256', $rawToken);
        if ($expiresAt !== null) {
            $attrs['expires_at'] = $expiresAt;
        }

        $invitation = self::create($attrs);

        return [
            'invitation' => $invitation,
            'rawToken'   => $rawToken,
        ];
    }

    /**
     * Look up an invitation by raw URL token (hash it first, then query).
     */
    public static function findByToken(string $rawToken): ?self
    {
        if (empty($rawToken)) return null;
        return self::where('token', hash('sha256', $rawToken))->first();
    }

    // ── Queries ────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeStaff($query)
    {
        return $query->where('role', self::ROLE_STAFF);
    }

    public function scopeCustomer($query)
    {
        return $query->where('role', self::ROLE_CUSTOMER);
    }

    // ── State helpers ──────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
            ($this->status === 'pending' && $this->expires_at !== null && $this->expires_at->isPast());
    }

    public function isValid(): bool
    {
        return $this->isPending();
    }

    // ── Public one-time URL (what the salon sends to the invitee) ─────

    /**
     * Build the public join link that the customer/staff opens in their browser.
     * This needs the RAW token (not the stored hash). Accept the raw token as parameter
     * because we do NOT want to store raw tokens in any DB for leak-resistance.
     *
     * Examples:
     *   https://yoursalon.com/join/staff?token=abc123…
     *   https://yoursalon.com/join/customer?token=abc123…
     */
    public function publicJoinUrl(string $rawToken): string
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));

        $role = strtolower($this->role);

        if ($role === 'customer') {
            return $frontendUrl . '/portal/invite/' . $rawToken;
        } elseif ($role === 'specialist') {
            return $frontendUrl . '/specialist-portal/invite/' . $rawToken;
        }
        // staff, manager, receptionist all go to salon workspace
        return $frontendUrl . '/invite/' . $rawToken;
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

}
