<?php

namespace App\Services\Verification\Contracts;

use App\Services\Verification\VerificationDeliveryResult;

/**
 * VerificationChannelInterface
 *
 * Contract for delivery channels in the zero-cost-aware verification architecture.
 */
interface VerificationChannelInterface
{
    /**
     * Unique channel identifier (e.g. 'whatsapp', 'email', 'sms_africas_talking').
     */
    public function getName(): string;

    /**
     * Channel display label.
     */
    public function getLabel(): string;

    /**
     * Whether this channel incurs third-party financial cost.
     */
    public function isPaid(): bool;

    /**
     * Whether this channel operates via a confirmed zero-cost infrastructure.
     */
    public function isZeroCost(): bool;

    /**
     * Whether this channel is currently active and permitted to send messages.
     */
    public function isAvailable(): bool;

    /**
     * Whether this channel supports the specified verification purpose.
     */
    public function supportsPurpose(string $purpose): bool;

    /**
     * Dispatch an OTP verification challenge through the channel.
     *
     * @param string $identifier E.164 phone or email address
     * @param string $code Plaintext OTP (used solely in-memory for transit, never logged)
     * @param string $purpose Verification purpose
     * @param array $metadata Context metadata
     * @return VerificationDeliveryResult
     */
    public function send(string $identifier, string $code, string $purpose, array $metadata = []): VerificationDeliveryResult;
}
