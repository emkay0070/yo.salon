<?php

namespace App\Services\Verification;

use App\Services\Verification\Channels\AfricasTalkingChannel;
use App\Services\Verification\Channels\EmailChannel;
use App\Services\Verification\Channels\TwilioChannel;
use App\Services\Verification\Channels\WhatsAppChannel;
use App\Services\Verification\Contracts\VerificationChannelInterface;

/**
 * VerificationChannelRegistry
 *
 * Manages registered verification channels and enforces zero-cost operating controls.
 */
class VerificationChannelRegistry
{
    /**
     * @var array<string, VerificationChannelInterface>
     */
    protected array $channels = [];

    public function __construct(
        protected ?array $config = null,
        protected ?bool $allowPaid = null
    ) {
        $this->registerDefaultChannels();
    }

    /**
     * Register standard platform channels.
     */
    protected function registerDefaultChannels(): void
    {
        try {
            $allowPaid = $this->allowPaid ?? (bool) config('verification.allow_paid_channels', false);
            $waConfig = $this->config['whatsapp'] ?? config('verification.channels.whatsapp', []);
            $emailConfig = $this->config['email'] ?? config('verification.channels.email', []);
            $atConfig = $this->config['sms_africas_talking'] ?? config('verification.channels.sms_africas_talking', []);
            $twConfig = $this->config['sms_twilio'] ?? config('verification.channels.sms_twilio', []);
        } catch (\Throwable) {
            $allowPaid = $this->allowPaid ?? false;
            $waConfig = $this->config['whatsapp'] ?? [];
            $emailConfig = $this->config['email'] ?? [];
            $atConfig = $this->config['sms_africas_talking'] ?? [];
            $twConfig = $this->config['sms_twilio'] ?? [];
        }

        $this->register(new WhatsAppChannel($waConfig, $allowPaid));
        $this->register(new EmailChannel($emailConfig));
        $this->register(new AfricasTalkingChannel($atConfig, $allowPaid));
        $this->register(new TwilioChannel($twConfig, $allowPaid));
    }

    /**
     * Register a channel instance.
     */
    public function register(VerificationChannelInterface $channel): void
    {
        $this->channels[$channel->getName()] = $channel;
    }

    /**
     * Get a specific channel by name.
     */
    public function getChannel(string $name): ?VerificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    /**
     * Get all registered channels.
     *
     * @return array<string, VerificationChannelInterface>
     */
    public function getAllChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get active channels that support the given purpose.
     *
     * @return VerificationChannelInterface[]
     */
    public function getActiveChannelsForPurpose(string $purpose): array
    {
        return array_filter($this->channels, function (VerificationChannelInterface $channel) use ($purpose) {
            return $channel->isAvailable() && $channel->supportsPurpose($purpose);
        });
    }

    /**
     * Determine preferred channel for an identifier and purpose.
     * Respects the zero-cost policy.
     */
    public function getPreferredChannelForIdentifier(string $identifier, string $purpose): ?VerificationChannelInterface
    {
        $isEmail = str_contains($identifier, '@');

        if ($isEmail) {
            $emailChannel = $this->getChannel('email');
            return ($emailChannel && $emailChannel->isAvailable() && $emailChannel->supportsPurpose($purpose))
                ? $emailChannel
                : null;
        }

        // Phone identifier: WhatsApp is the preferred channel ONLY if zero-cost
        $whatsapp = $this->getChannel('whatsapp');
        if ($whatsapp && $whatsapp->isAvailable() && $whatsapp->supportsPurpose($purpose)) {
            return $whatsapp;
        }

        // No paid fallback permitted
        return null;
    }

    /**
     * Export complete channel activation and cost matrix.
     */
    public function getChannelMatrix(): array
    {
        try {
            $allowPaid = $this->allowPaid ?? (bool) config('verification.allow_paid_channels', false);
        } catch (\Throwable) {
            $allowPaid = $this->allowPaid ?? false;
        }

        $matrix = [];
        foreach ($this->channels as $name => $channel) {
            $matrix[$name] = [
                'name'         => $channel->getLabel(),
                'is_paid'      => $channel->isPaid(),
                'is_zero_cost' => $channel->isZeroCost(),
                'is_available' => $channel->isAvailable(),
                'status'       => $channel->isAvailable() ? 'ACTIVE' : 'PAUSED',
                'policy_note'  => $channel->isPaid() && !$allowPaid
                    ? 'Paused under zero-cost operating policy.'
                    : ($channel->isAvailable() ? 'Operating normally.' : 'Channel dormant.'),
            ];
        }

        return $matrix;
    }
}
