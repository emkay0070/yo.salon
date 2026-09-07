<?php

namespace App\Services\Payments;

class PayoutReadinessDTO
{
    public function __construct(
        public readonly bool    $automatedAvailable,
        public readonly ?string $method,
        public readonly ?string $destination,
        public readonly string  $verificationStatus,  // 'verified' | 'pending' | 'none'
        public readonly bool    $manualAvailable,
        public readonly ?string $reason,
    ) {}

    /**
     * Convenience constructor for manual-only result.
     */
    public static function manualOnly(
        string  $reason,
        ?string $destination = null,
        string  $verificationStatus = 'none',
    ): self {
        return new self(
            automatedAvailable: false,
            method: null,
            destination: $destination,
            verificationStatus: $verificationStatus,
            manualAvailable: true,
            reason: $reason,
        );
    }

    public function toArray(): array
    {
        return [
            'automated_available'  => $this->automatedAvailable,
            'method'               => $this->method,
            'destination'          => $this->destination,
            'verification_status'  => $this->verificationStatus,
            'manual_available'     => $this->manualAvailable,
            'reason'               => $this->reason,
        ];
    }
}
