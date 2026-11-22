<?php

namespace App\Traits;

use App\Services\Verification\VerificationService;

/**
 * HasVerificationStatus
 *
 * Trait providing non-blocking verification status queries and gating logic.
 */
trait HasVerificationStatus
{
    /**
     * Check if user/account has verified phone status.
     */
    public function isPhoneVerified(): bool
    {
        if (isset($this->phone_verified_at) && $this->phone_verified_at !== null) {
            return true;
        }

        if (empty($this->phone)) {
            return false;
        }

        $service = app(VerificationService::class);
        return $service->getVerificationStatus($this->phone, 'phone_verification') === 'verified';
    }

    /**
     * Check if user/account has verified email status.
     */
    public function isEmailVerified(): bool
    {
        if (isset($this->email_verified_at) && $this->email_verified_at !== null) {
            return true;
        }

        if (empty($this->email)) {
            return false;
        }

        $service = app(VerificationService::class);
        return $service->getVerificationStatus($this->email, 'email_verification') === 'verified';
    }

    /**
     * Get explicit phone verification status:
     * 'verified', 'pending', 'unverified', or 'verification_unavailable'.
     */
    public function getPhoneVerificationStatus(): string
    {
        if (empty($this->phone)) {
            return 'unverified';
        }

        $service = app(VerificationService::class);
        return $service->getVerificationStatus($this->phone, 'phone_verification');
    }

    /**
     * Non-blocking policy: determine whether onboarding or actions can proceed
     * without blocking on verification.
     */
    public function canCompleteOnboardingWithoutVerification(): bool
    {
        return !config('verification.enforce_mandatory_otp_onboarding', false);
    }
}
