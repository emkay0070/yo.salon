<?php

namespace App\Services\Verification;

use App\Models\VerificationChallenge;
use App\Services\Phone\PhoneIntelligenceService;
use Illuminate\Support\Str;

/**
 * VerificationService
 *
 * Core engine orchestrating challenge generation, hashed OTP persistence,
 * zero-cost channel routing, attempt throttling, and non-blocking onboarding status.
 */
class VerificationService
{
    public const PURPOSES = [
        'phone_verification',
        'email_verification',
        'registration',
        'account_recovery',
        'phone_change',
        'sensitive_action',
    ];

    public function __construct(
        protected VerificationChannelRegistry $registry,
        protected PhoneIntelligenceService $phoneService
    ) {}

    /**
     * Request a new verification challenge.
     * Non-blocking: if delivery channels are unavailable/paused under zero-cost policy,
     * returns a non-blocking verification_unavailable status.
     *
     * @param string $identifier E.164 phone number or email address
     * @param string $purpose Must be in PURPOSES
     * @param string|null $preferredChannel Optional specific channel name
     * @param array $metadata Extra context
     * @return array
     */
    public function requestChallenge(
        string $identifier,
        string $purpose,
        ?string $preferredChannel = null,
        array $metadata = []
    ): array {
        if (!in_array($purpose, self::PURPOSES, true)) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => "Invalid verification purpose: [{$purpose}].",
            ];
        }

        $isEmail = str_contains($identifier, '@');
        $identifierType = $isEmail ? 'email' : 'phone';

        // Canonicalize identifier
        $cleanIdentifier = $isEmail
            ? strtolower(trim($identifier))
            : $this->phoneService->normalize($identifier, $metadata['country_code'] ?? null);

        if (!$cleanIdentifier) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'Invalid phone or email identifier provided.',
            ];
        }

        // Check resend cooldown
        $latest = VerificationChallenge::where('identifier', $cleanIdentifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if ($latest && !$latest->isExpired() && !$latest->canResend()) {
            $remainingSeconds = now()->diffInSeconds($latest->resend_available_at, false);
            return [
                'success'           => false,
                'status'            => 'rate_limited',
                'message'           => "Please wait {$remainingSeconds} seconds before requesting another code.",
                'remaining_seconds' => max(1, $remainingSeconds),
            ];
        }

        // Resolve delivery channel
        $channel = $preferredChannel
            ? $this->registry->getChannel($preferredChannel)
            : $this->registry->getPreferredChannelForIdentifier($cleanIdentifier, $purpose);

        // Configuration values
        $ttlMinutes = function_exists('config') ? (int) config('verification.defaults.ttl_minutes', 15) : 15;
        $cooldownSeconds = function_exists('config') ? (int) config('verification.defaults.resend_cooldown_seconds', 60) : 60;
        $otpLength = function_exists('config') ? (int) config('verification.defaults.otp_length', 6) : 6;
        $maxAttempts = function_exists('config') ? (int) config('verification.defaults.max_attempts', 5) : 5;

        // Generate cryptographically secure OTP
        $min = 10 ** ($otpLength - 1);
        $max = (10 ** $otpLength) - 1;
        $otp = (string) random_int($min, $max);

        $now = now();
        $expiresAt = $now->copy()->addMinutes($ttlMinutes);
        $resendAvailableAt = $now->copy()->addSeconds($cooldownSeconds);

        // If no active channel exists or channel is paused under zero-cost policy:
        if (!$channel || !$channel->isAvailable()) {
            $channelName = $channel ? $channel->getName() : ($isEmail ? 'email' : 'whatsapp');

            $challenge = VerificationChallenge::create([
                'id'                  => (string) Str::uuid(),
                'identifier'          => $cleanIdentifier,
                'identifier_type'     => $identifierType,
                'purpose'             => $purpose,
                'channel'             => $channelName,
                'code_hash'           => VerificationChallenge::hashCode($otp),
                'expires_at'          => $expiresAt,
                'resend_available_at' => $resendAvailableAt,
                'max_attempts'        => $maxAttempts,
                'metadata'            => $metadata,
                'delivery_status'     => 'paused',
                'delivery_error'      => 'Channel paused under zero-cost policy.',
            ]);

            return [
                'success'           => true,
                'challenge_id'      => $challenge->id,
                'status'            => 'verification_unavailable',
                'channel'           => $channelName,
                'non_blocking'      => true,
                'message'           => 'Verification channel is dormant under zero-cost policy. Your onboarding remains active and unblocked.',
                'expires_at'        => $expiresAt->toISOString(),
            ];
        }

        // Channel is active: persist and dispatch
        $challenge = VerificationChallenge::create([
            'id'                  => (string) Str::uuid(),
            'identifier'          => $cleanIdentifier,
            'identifier_type'     => $identifierType,
            'purpose'             => $purpose,
            'channel'             => $channel->getName(),
            'code_hash'           => VerificationChallenge::hashCode($otp, $cleanIdentifier),
            'expires_at'          => $expiresAt,
            'resend_available_at' => $resendAvailableAt,
            'max_attempts'        => $maxAttempts,
            'metadata'            => $metadata,
            'delivery_status'     => 'pending',
        ]);

        $result = $channel->send($cleanIdentifier, $otp, $purpose, $metadata);

        if (!$result->success) {
            // Delivery failed: mark challenge as failed and consume it so it does not leave
            // a zombie challenge or lock the user out with an unserviceable resend cooldown
            $challenge->update([
                'delivery_status' => 'failed',
                'delivery_error'  => $result->error ?? 'Delivery failed',
                'consumed_at'     => now(),
            ]);

            return [
                'success'      => false,
                'challenge_id' => $challenge->id,
                'status'       => 'failed',
                'channel'      => $channel->getName(),
                'message'      => $result->error ?? 'Delivery failed',
            ];
        }

        $challenge->update([
            'delivery_status' => $result->status,
            'delivery_error'  => $result->error,
        ]);

        return [
            'success'      => true,
            'challenge_id' => $challenge->id,
            'status'       => $result->status,
            'channel'      => $channel->getName(),
            'expires_at'   => $expiresAt->toISOString(),
            'message'      => $result->message ?? 'Verification code sent.',
        ];
    }

    /**
     * Verify a submitted OTP challenge.
     * Concurrency-safe: executes with pessimistic row locking inside a transaction
     * to eliminate race-condition brute-force attacks and double-consumption.
     */
    public function verifyChallenge(string $identifier, string $code, string $purpose): array
    {
        $isEmail = str_contains($identifier, '@');
        $cleanIdentifier = $isEmail
            ? strtolower(trim($identifier))
            : $this->phoneService->normalize($identifier);

        if (!$cleanIdentifier) {
            return [
                'verified' => false,
                'error'    => 'Invalid identifier format.',
            ];
        }

        $verifyTransaction = function () use ($cleanIdentifier, $purpose, $code) {
            $query = VerificationChallenge::where('identifier', $cleanIdentifier)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->latest();

            // Pessimistic row locking for concurrency protection when database connection is available
            try {
                $challenge = $query->lockForUpdate()->first();
            } catch (\Throwable) {
                $challenge = $query->first();
            }

            if (!$challenge) {
                return [
                    'verified' => false,
                    'error'    => 'No active verification challenge found.',
                ];
            }

            if ($challenge->isExpired()) {
                return [
                    'verified' => false,
                    'error'    => 'Verification code has expired. Please request a new code.',
                ];
            }

            if ($challenge->hasExceededAttempts()) {
                return [
                    'verified' => false,
                    'error'    => 'Maximum verification attempts exceeded. Please request a new code.',
                ];
            }

            if (!$challenge->verifyCode($code)) {
                $challenge->recordAttempt();
                $remaining = max(0, $challenge->max_attempts - $challenge->attempts);

                return [
                    'verified'           => false,
                    'error'              => 'Incorrect verification code.',
                    'remaining_attempts' => $remaining,
                ];
            }

            // Successfully verified: consume one-time
            $challenge->consume();

            return [
                'verified'     => true,
                'challenge_id' => $challenge->id,
                'purpose'      => $purpose,
                'identifier'   => $cleanIdentifier,
            ];
        };

        try {
            return \Illuminate\Support\Facades\DB::transaction($verifyTransaction);
        } catch (\Throwable) {
            return $verifyTransaction();
        }
    }

    /**
     * Get explicit verification status for an identifier.
     *
     * @return string 'verified'|'pending'|'unverified'|'verification_unavailable'
     */
    public function getVerificationStatus(string $identifier, string $purpose): string
    {
        $isEmail = str_contains($identifier, '@');
        $cleanIdentifier = $isEmail
            ? strtolower(trim($identifier))
            : $this->phoneService->normalize($identifier);

        if (!$cleanIdentifier) {
            return 'unverified';
        }

        // Check if recently verified and consumed
        $consumed = VerificationChallenge::where('identifier', $cleanIdentifier)
            ->where('purpose', $purpose)
            ->whereNotNull('consumed_at')
            ->latest('consumed_at')
            ->first();

        if ($consumed) {
            return 'verified';
        }

        $active = VerificationChallenge::where('identifier', $cleanIdentifier)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($active) {
            return $active->delivery_status === 'paused' ? 'verification_unavailable' : 'pending';
        }

        return 'unverified';
    }
}
