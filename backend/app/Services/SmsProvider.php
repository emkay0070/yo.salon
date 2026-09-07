<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsProvider
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('services.sms.provider', 'log');
        $this->config = config('services.sms', []);
    }

    /**
     * Send SMS
     */
    public function send(string $phone, string $message): bool
    {
        try {
            switch ($this->provider) {
                case 'twilio':
                    return $this->sendViaTwilio($phone, $message);
                case 'africas_talking':
                    return $this->sendViaAfricasTalking($phone, $message);
                case 'log':
                default:
                    return $this->sendViaLog($phone, $message);
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'provider' => $this->provider,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send via Twilio
     */
    private function sendViaTwilio(string $phone, string $message): bool
    {
        $sid = $this->config['twilio']['sid'] ?? null;
        $token = $this->config['twilio']['token'] ?? null;
        $from = $this->config['twilio']['from'] ?? null;

        if (!$sid || !$token || !$from) {
            Log::error('Twilio credentials not configured');
            return false;
        }

        // TODO: Implement actual Twilio API call
        // For now, log and return true
        Log::info("SMS via Twilio", ['to' => $phone, 'message' => $message]);
        return true;
    }

    /**
     * Send via Africa's Talking
     */
    private function sendViaAfricasTalking(string $phone, string $message): bool
    {
        $username = $this->config['africas_talking']['username'] ?? null;
        $apiKey = $this->config['africas_talking']['api_key'] ?? null;

        if (!$username || !$apiKey) {
            Log::error('Africa\'s Talking credentials not configured');
            return false;
        }

        // TODO: Implement actual Africa's Talking API call
        // For now, log and return true
        Log::info("SMS via Africa's Talking", ['to' => $phone, 'message' => $message]);
        return true;
    }

    /**
     * Send via log (for development/testing)
     */
    private function sendViaLog(string $phone, string $message): bool
    {
        Log::info("SMS (log mode)", [
            'to' => $phone,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ]);
        return true;
    }

    /**
     * Format phone number to international format
     */
    public function formatPhone(string $phone, string $countryCode = '+256'): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 0, replace with country code
        if (str_starts_with($phone, '0')) {
            $phone = $countryCode . substr($phone, 1);
        }

        // If doesn't start with +, add country code
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
