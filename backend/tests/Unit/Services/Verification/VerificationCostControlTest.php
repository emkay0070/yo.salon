<?php

namespace Tests\Unit\Services\Verification;

use App\Models\VerificationChallenge;
use App\Services\Phone\PhoneIntelligenceService;
use App\Services\Verification\Channels\AfricasTalkingChannel;
use App\Services\Verification\Channels\EmailChannel;
use App\Services\Verification\Channels\TwilioChannel;
use App\Services\Verification\Channels\WhatsAppChannel;
use App\Services\Verification\VerificationChannelRegistry;
use PHPUnit\Framework\TestCase;

/**
 * VerificationCostControlTest
 *
 * Verifies zero-cost verification operating controls:
 * - Paid channels (WhatsApp, Africa's Talking, Twilio) are PAUSED by default
 * - No paid messages are ever dispatched without explicit authorization
 * - Zero-cost email channel operates normally
 * - Non-blocking onboarding policy
 * - OTP security and SHA-256 hashing
 */
class VerificationCostControlTest extends TestCase
{
    private VerificationChannelRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $channelsConfig = [
            'whatsapp' => [
                'enabled'               => false,
                'status'                => 'PAUSED',
                'is_paid'               => true,
                'has_zero_cost_gateway' => false,
            ],
            'email' => [
                'enabled' => true,
            ],
            'sms_africas_talking' => [
                'enabled' => false,
                'status'  => 'PAUSED',
                'is_paid' => true,
            ],
            'sms_twilio' => [
                'enabled' => false,
                'status'  => 'PAUSED',
                'is_paid' => true,
            ],
        ];

        $this->registry = new VerificationChannelRegistry($channelsConfig, allowPaid: false);
    }

    /**
     * By default, all paid channels are PAUSED under the zero-cost policy.
     */
    public function test_paid_channels_are_paused_under_zero_cost_policy(): void
    {
        $matrix = $this->registry->getChannelMatrix();

        // WhatsApp
        $this->assertArrayHasKey('whatsapp', $matrix);
        $this->assertTrue($matrix['whatsapp']['is_paid']);
        $this->assertFalse($matrix['whatsapp']['is_available']);
        $this->assertEquals('PAUSED', $matrix['whatsapp']['status']);

        // Africa's Talking SMS
        $this->assertArrayHasKey('sms_africas_talking', $matrix);
        $this->assertTrue($matrix['sms_africas_talking']['is_paid']);
        $this->assertFalse($matrix['sms_africas_talking']['is_available']);
        $this->assertEquals('PAUSED', $matrix['sms_africas_talking']['status']);

        // Twilio SMS
        $this->assertArrayHasKey('sms_twilio', $matrix);
        $this->assertTrue($matrix['sms_twilio']['is_paid']);
        $this->assertFalse($matrix['sms_twilio']['is_available']);
        $this->assertEquals('PAUSED', $matrix['sms_twilio']['status']);

        // Email: Zero-cost channel is ACTIVE
        $this->assertArrayHasKey('email', $matrix);
        $this->assertFalse($matrix['email']['is_paid']);
        $this->assertTrue($matrix['email']['is_zero_cost']);
        $this->assertTrue($matrix['email']['is_available']);
        $this->assertEquals('ACTIVE', $matrix['email']['status']);
    }

    /**
     * Paused channels refuse to dispatch messages and return a paused result.
     */
    public function test_paused_channels_refuse_message_dispatch(): void
    {
        $whatsapp = new WhatsAppChannel(['enabled' => false, 'status' => 'PAUSED'], allowPaid: false);
        $resultWa = $whatsapp->send('+256700123456', '123456', 'phone_verification');
        $this->assertFalse($resultWa->success);
        $this->assertEquals('paused', $resultWa->status);

        $africasTalking = new AfricasTalkingChannel(['enabled' => false, 'status' => 'PAUSED'], allowPaid: false);
        $resultAt = $africasTalking->send('+256700123456', '123456', 'phone_verification');
        $this->assertFalse($resultAt->success);
        $this->assertEquals('paused', $resultAt->status);

        $twilio = new TwilioChannel(['enabled' => false, 'status' => 'PAUSED'], allowPaid: false);
        $resultTw = $twilio->send('+256700123456', '123456', 'phone_verification');
        $this->assertFalse($resultTw->success);
        $this->assertEquals('paused', $resultTw->status);
    }

    /**
     * Active zero-cost email channel dispatches successfully.
     */
    public function test_email_channel_dispatches_at_zero_cost(): void
    {
        $emailChannel = new EmailChannel(['enabled' => true]);
        $this->assertTrue($emailChannel->isAvailable());
        $this->assertFalse($emailChannel->isPaid());

        $result = $emailChannel->send('user@example.com', '654321', 'email_verification');
        $this->assertTrue($result->success);
        $this->assertEquals('delivered', $result->status);
    }

    /**
     * When phone verification is requested under zero-cost mode (no active zero-cost phone channel),
     * no paid channel is selected as preferred.
     */
    public function test_phone_verification_is_non_blocking_when_channels_are_paused(): void
    {
        // WhatsApp is preferred for phone, but paused under zero-cost policy
        $preferred = $this->registry->getPreferredChannelForIdentifier('+256700123456', 'phone_verification');
        $this->assertNull($preferred, 'No paid channel may be selected as preferred while zero-cost policy is active');

        // Email channel is preferred for email
        $preferredEmail = $this->registry->getPreferredChannelForIdentifier('test@example.com', 'email_verification');
        $this->assertNotNull($preferredEmail);
        $this->assertEquals('email', $preferredEmail->getName());
    }

    /**
     * OTP hashes use SHA-256 and never match plaintext or incorrect codes.
     */
    public function test_otp_code_hashing_and_verification(): void
    {
        $code = '789123';
        $hash = VerificationChallenge::hashCode($code);

        $this->assertNotEquals($code, $hash, 'Plaintext code must never equal hash');
        $this->assertEquals(64, strlen($hash), 'SHA-256 hash must be 64 hexadecimal characters');

        // Verification matches correct code
        $this->assertTrue(hash_equals($hash, VerificationChallenge::hashCode('789123')));
        $this->assertFalse(hash_equals($hash, VerificationChallenge::hashCode('000000')));
    }
}
