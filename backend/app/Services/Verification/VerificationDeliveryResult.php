<?php

namespace App\Services\Verification;

/**
 * VerificationDeliveryResult
 *
 * Immutable delivery outcome DTO.
 */
class VerificationDeliveryResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $channel,
        public readonly string $status, // 'delivered', 'mocked', 'failed', 'paused', 'unavailable'
        public readonly ?string $message = null,
        public readonly ?string $error = null
    ) {}

    public static function delivered(string $channel, ?string $message = null): self
    {
        return new self(success: true, channel: $channel, status: 'delivered', message: $message);
    }

    public static function mocked(string $channel, ?string $message = null): self
    {
        return new self(success: true, channel: $channel, status: 'mocked', message: $message);
    }

    public static function paused(string $channel, ?string $message = null): self
    {
        return new self(success: false, channel: $channel, status: 'paused', message: $message, error: 'Channel is paused under zero-cost policy.');
    }

    public static function failed(string $channel, string $error): self
    {
        return new self(success: false, channel: $channel, status: 'failed', error: $error);
    }
}
