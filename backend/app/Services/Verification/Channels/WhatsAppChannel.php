<?php

namespace App\Services\Verification\Channels;

use App\Services\Verification\Contracts\VerificationChannelInterface;
use App\Services\Verification\VerificationDeliveryResult;
use Illuminate\Support\Facades\Log;

/**
 * WhatsAppChannel
 *
 * WhatsApp verification adapter.
 * Operating Policy: WhatsApp is PAUSED by default unless a verified zero-cost gateway
 * is confirmed. Paid Meta Cloud API or Business API delivery will not execute
 * when allow_paid_channels is false.
 */
class WhatsAppChannel implements VerificationChannelInterface
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
            return config("verification.channels.whatsapp.{$key}", $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getName(): string
    {
        return 'whatsapp';
    }

    public function getLabel(): string
    {
        return 'WhatsApp';
    }

    public function isPaid(): bool
    {
        return (bool) $this->getConfig('is_paid', true);
    }

    public function isZeroCost(): bool
    {
        return (bool) $this->getConfig('has_zero_cost_gateway', false);
    }

    public function isAvailable(): bool
    {
        $allowPaid = $this->allowPaid ?? (function_exists('config') ? (bool) config('verification.allow_paid_channels', false) : false);
        $enabled = (bool) $this->getConfig('enabled', false);
        $status = (string) $this->getConfig('status', 'PAUSED');

        if (!$enabled || $status === 'PAUSED') {
            return false;
        }

        if ($this->isPaid() && !$allowPaid && !$this->isZeroCost()) {
            return false;
        }

        return true;
    }

    public function supportsPurpose(string $purpose): bool
    {
        $supported = $this->getConfig('supported_purposes', [
            'phone_verification',
            'registration',
            'account_recovery',
            'phone_change',
            'sensitive_action',
        ]);
        return in_array($purpose, $supported, true);
    }

    public function send(string $identifier, string $code, string $purpose, array $metadata = []): VerificationDeliveryResult
    {
        if (!$this->isAvailable()) {
            try {
                Log::info("WhatsApp OTP dispatch halted: Channel is PAUSED under zero-cost operating policy.", [
                    'recipient' => $identifier,
                    'purpose'   => $purpose,
                ]);
            } catch (\Throwable) {}
            return VerificationDeliveryResult::paused($this->getName(), 'WhatsApp channel is paused under zero-cost operating policy.');
        }

        try {
            Log::info("WhatsApp OTP delivered (simulated/mocked)", [
                'recipient' => $identifier,
                'purpose'   => $purpose,
            ]);
        } catch (\Throwable) {}

        return VerificationDeliveryResult::delivered($this->getName(), 'WhatsApp message queued for delivery.');
    }
}
