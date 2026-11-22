<?php

namespace App\Services\Verification\Channels;

use App\Services\Verification\Contracts\VerificationChannelInterface;
use App\Services\Verification\VerificationDeliveryResult;
use Illuminate\Support\Facades\Log;

/**
 * TwilioChannel
 *
 * Twilio SMS adapter.
 * Policy: PAUSED under zero-cost policy.
 */
class TwilioChannel implements VerificationChannelInterface
{
    public function __construct(
        protected ?array $config = null,
        protected ?bool $allowPaid = null
    ) {}

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        if ($this->config !== null && array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        try {
            return config("verification.channels.sms_twilio.{$key}", $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getName(): string
    {
        return 'sms_twilio';
    }

    public function getLabel(): string
    {
        return 'Twilio SMS';
    }

    public function isPaid(): bool
    {
        return true;
    }

    public function isZeroCost(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        $allowPaid = $this->allowPaid ?? (function_exists('config') ? (bool) config('verification.allow_paid_channels', false) : false);
        $enabled = (bool) $this->getConfig('enabled', false);
        $status = (string) $this->getConfig('status', 'PAUSED');

        return $allowPaid && $enabled && $status === 'ACTIVE';
    }

    public function supportsPurpose(string $purpose): bool
    {
        $supported = $this->getConfig('supported_purposes', [
            'phone_verification',
            'account_recovery',
            'sensitive_action',
        ]);
        return in_array($purpose, $supported, true);
    }

    public function send(string $identifier, string $code, string $purpose, array $metadata = []): VerificationDeliveryResult
    {
        try {
            Log::info("Twilio SMS dispatch halted: Channel is PAUSED under zero-cost operating policy.", [
                'recipient' => $identifier,
                'purpose'   => $purpose,
            ]);
        } catch (\Throwable) {}

        return VerificationDeliveryResult::paused($this->getName(), 'Twilio SMS is paused to prevent billing charges.');
    }
}
