<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * VerificationChallenge
 *
 * Persists hashed one-time verification tokens and challenge lifecycle.
 * Plaintext OTPs are NEVER stored or logged.
 */
class VerificationChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'identifier',
        'identifier_type',
        'purpose',
        'channel',
        'code_hash',
        'expires_at',
        'resend_available_at',
        'attempts',
        'max_attempts',
        'consumed_at',
        'metadata',
        'delivery_status',
        'delivery_error',
    ];

    protected $casts = [
        'expires_at'          => 'datetime',
        'resend_available_at' => 'datetime',
        'consumed_at'         => 'datetime',
        'metadata'            => 'array',
        'attempts'            => 'integer',
        'max_attempts'        => 'integer',
    ];

    /**
     * Compute HMAC-SHA-256 hash of an OTP code with server secret and optional salt.
     * Prevents offline brute-force attacks on low-entropy 6-digit numeric OTPs.
     */
    public static function hashCode(string $code, ?string $salt = null): string
    {
        $key = 'tybuel-verification-key';
        try {
            if (function_exists('config')) {
                $key = config('app.key') ?: $key;
            }
        } catch (\Throwable) {}

        $payload = trim($code) . ($salt ? ":{$salt}" : '');
        return hash_hmac('sha256', $payload, $key);
    }

    /**
     * Determine if challenge has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine if challenge has been consumed.
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Determine if maximum attempt limit has been reached.
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    /**
     * Determine if resend cooldown has elapsed.
     */
    public function canResend(): bool
    {
        return now()->gte($this->resend_available_at);
    }

    /**
     * Verify whether a submitted OTP matches the stored hash.
     * Checks both salted and unsalted variants for seamless compatibility.
     */
    public function verifyCode(string $code): bool
    {
        if ($this->isExpired() || $this->isConsumed() || $this->hasExceededAttempts()) {
            return false;
        }

        $matchesSalted = hash_equals($this->code_hash, self::hashCode($code, $this->identifier));
        $matchesPlain = hash_equals($this->code_hash, self::hashCode($code));

        return $matchesSalted || $matchesPlain;
    }

    /**
     * Mark challenge as successfully consumed.
     */
    public function consume(): void
    {
        $this->update([
            'consumed_at' => now(),
        ]);
    }

    /**
     * Increment verification attempt counter.
     */
    public function recordAttempt(): void
    {
        $this->increment('attempts');
    }

    /**
     * Scope: Unconsumed and unexpired challenges.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('consumed_at')
                     ->where('expires_at', '>', now());
    }

    /**
     * Scope: Filter by identifier and purpose.
     */
    public function scopeForIdentifierAndPurpose($query, string $identifier, string $purpose)
    {
        return $query->where('identifier', $identifier)
                     ->where('purpose', $purpose);
    }
}
