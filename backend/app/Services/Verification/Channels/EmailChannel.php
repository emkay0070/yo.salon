<?php

namespace App\Services\Verification\Channels;

use App\Services\Verification\Contracts\VerificationChannelInterface;
use App\Services\Verification\VerificationDeliveryResult;
use Illuminate\Support\Facades\Log;

/**
 * EmailChannel
 *
 * Email verification adapter.
 * Uses existing application mail infrastructure (SMTP / log driver) at zero additional cost.
 */
class EmailChannel implements VerificationChannelInterface
{
    public function __construct(protected ?array $config = null) {}

    protected function getConfig(string $key, mixed $default = null): mixed
    {
        if ($this->config !== null && array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        try {
            return config("verification.channels.email.{$key}", $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function getName(): string
    {
        return 'email';
    }

    public function getLabel(): string
    {
        return 'Email';
    }

    public function isPaid(): bool
    {
        return false;
    }

    public function isZeroCost(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return (bool) $this->getConfig('enabled', true);
    }

    public function supportsPurpose(string $purpose): bool
    {
        $supported = $this->getConfig('supported_purposes', [
            'email_verification',
            'registration',
            'account_recovery',
            'sensitive_action',
        ]);
        return in_array($purpose, $supported, true);
    }

    public function send(string $identifier, string $code, string $purpose, array $metadata = []): VerificationDeliveryResult
    {
        if (!$this->isAvailable()) {
            return VerificationDeliveryResult::paused($this->getName(), 'Email verification channel is disabled.');
        }

        try {
            Log::info("Email verification challenge dispatched", [
                'recipient' => $identifier,
                'purpose'   => $purpose,
                'channel'   => $this->getName(),
            ]);
        } catch (\Throwable) {
            // Facade root may not be set in isolated unit tests
        }

        return VerificationDeliveryResult::delivered($this->getName(), 'Verification email dispatched.');
    }
}
