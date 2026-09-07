<?php

namespace App\Domain\Specialist;

enum VerificationStatus: string
{
    case UNVERIFIED = 'unverified';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case VERIFIED = 'verified';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
    case REJECTED = 'rejected';

    public function canSubmit(): bool
    {
        return $this === self::UNVERIFIED || $this === self::REJECTED;
    }

    public function canApprove(): bool
    {
        return $this === self::SUBMITTED || $this === self::UNDER_REVIEW;
    }

    public function canReject(): bool
    {
        return $this === self::SUBMITTED || $this === self::UNDER_REVIEW;
    }

    public function canRevoke(): bool
    {
        return $this === self::VERIFIED || $this === self::APPROVED;
    }

    public function canExpire(): bool
    {
        return $this === self::VERIFIED;
    }

    public function isVerified(): bool
    {
        return $this === self::VERIFIED;
    }

    public function isPending(): bool
    {
        return $this === self::SUBMITTED || $this === self::UNDER_REVIEW;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::VERIFIED, self::EXPIRED, self::REVOKED, self::REJECTED]);
    }

    public function getLabel(): string
    {
        return match($this) {
            self::UNVERIFIED => 'Unverified',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED => 'Approved',
            self::VERIFIED => 'Verified',
            self::EXPIRED => 'Expired',
            self::REVOKED => 'Revoked',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::UNVERIFIED => 'gray',
            self::SUBMITTED => 'blue',
            self::UNDER_REVIEW => 'yellow',
            self::APPROVED => 'green',
            self::VERIFIED => 'green',
            self::EXPIRED => 'orange',
            self::REVOKED => 'red',
            self::REJECTED => 'red',
        };
    }
}
