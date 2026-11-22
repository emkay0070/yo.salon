<?php

namespace App\Services\Verification\Channels;

use App\Services\Verification\Contracts\VerificationChannelInterface;
use App\Services\Verification\VerificationDeliveryResult;
use Illuminate\Support\Facades\Log;

/**
 * AfricasTalkingChannel
 *
 * SMS adapter for Africa's Talking.
 * Policy: PAUSED under zero-cost policy.
 */
class AfricasTalkingChannel implements VerificationChannelInterface
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
            return config("verification.channels.sms_africas_talking.{$key}", $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getName(): string
    {
        return 'sms_africas_talking';
    }

    public function getLabel(): string
    {
        return "Africa's Talking SMS";
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
            Log::info("Africa's Talking SMS dispatch halted: Channel is PAUSED under zero-cost operating policy.", [
                'recipient' => $identifier,
                'purpose'   => $purpose,
            ]);
        } catch (\Throwable) {}

        return VerificationDeliveryResult::paused($this->getName(), "Africa's Talking SMS is paused to prevent billing charges.");
    }
}
