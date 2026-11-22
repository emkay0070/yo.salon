<?php

namespace Tests\Unit\Services\Phone;

use App\Services\Phone\PhoneIntelligenceService;
use App\Services\Phone\PhoneMetadata;
use PHPUnit\Framework\TestCase;

/**
 * PhoneIntelligenceTest
 *
 * Verifies global phone intelligence capabilities:
 * - Worldwide E.164 parsing without Uganda bias
 * - Country-aware national number parsing with context
 * - Separation of emails and phones (H-1)
 * - Dual-query lookup compatibility for legacy formats (M-1)
 * - Display formatting and idempotence
 */
class PhoneIntelligenceTest extends TestCase
{
    private PhoneIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhoneIntelligenceService();
    }

    /**
     * Worldwide numbers parse to correct canonical E.164 without assuming Uganda.
     */
    public function test_worldwide_numbers_parse_correctly(): void
    {
        $cases = [
            // Uganda
            '+256 700 123456'  => ['e164' => '+256700123456', 'cc' => '256', 'region' => 'UG'],
            '00256 700 123456' => ['e164' => '+256700123456', 'cc' => '256', 'region' => 'UG'],

            // Kenya
            '+254 712 345678'  => ['e164' => '+254712345678', 'cc' => '254', 'region' => 'KE'],

            // United Kingdom
            '+44 20 7946 0958' => ['e164' => '+442079460958', 'cc' => '44', 'region' => 'GB'],

            // United States
            '+1 (415) 555-2671'=> ['e164' => '+14155552671', 'cc' => '1', 'region' => 'US'],

            // France
            '+33 1 42 68 55 55'=> ['e164' => '+33142685555', 'cc' => '33', 'region' => 'FR'],

            // United Arab Emirates
            '+971 4 455 6000'  => ['e164' => '+97144556000', 'cc' => '971', 'region' => 'AE'],

            // Australia
            '+61 2 9374 4000'  => ['e164' => '+61293744000', 'cc' => '61', 'region' => 'AU'],
        ];

        foreach ($cases as $input => $expected) {
            $result = $this->service->parse($input);
            $this->assertTrue($result->isValid, "Failed parsing [{$input}]");
            $this->assertEquals($expected['e164'], $result->e164);
            $this->assertEquals($expected['cc'], $result->countryCode);
            $this->assertEquals($expected['region'], $result->regionCode);
        }
    }

    /**
     * Local numbers starting with 0 do NOT default to Uganda unless explicitly supplied.
     */
    public function test_local_numbers_do_not_default_to_uganda_without_context(): void
    {
        $local = '0700 123456';

        // Without context: must NOT guess Uganda
        $resultNoContext = $this->service->parse($local);
        $this->assertFalse($resultNoContext->isValid);
        $this->assertNull($resultNoContext->e164);

        // With Uganda context
        $resultUg = $this->service->parse($local, 'UG');
        $this->assertTrue($resultUg->isValid);
        $this->assertEquals('+256700123456', $resultUg->e164);
        $this->assertEquals('UG', $resultUg->regionCode);

        // With Kenya context
        $resultKe = $this->service->parse('0712 345678', 'KE');
        $this->assertTrue($resultKe->isValid);
        $this->assertEquals('+254712345678', $resultKe->e164);
        $this->assertEquals('KE', $resultKe->regionCode);

        // With UK context
        $resultUk = $this->service->parse('020 7946 0958', 'GB');
        $this->assertTrue($resultUk->isValid);
        $this->assertEquals('+442079460958', $resultUk->e164);
        $this->assertEquals('GB', $resultUk->regionCode);
    }

    /**
     * H-1 Guard: Email addresses (even those containing 7+ digits) must NEVER
     * be parsed or matched as phone numbers.
     */
    public function test_email_addresses_with_digits_are_never_parsed_as_phones(): void
    {
        $digitEmails = [
            'user15552345678@example.com',
            'order256700123456@domain.com',
            'contact+442079460958@yo.salon',
            'jane.doe@gmail.com',
        ];

        foreach ($digitEmails as $email) {
            $result = $this->service->parse($email);
            $this->assertFalse($result->isValid, "Email [{$email}] was falsely marked as valid phone");
            $this->assertNull($result->e164, "Email [{$email}] produced a phone E.164 value");
            $this->assertTrue($this->service->isEmail($email));
        }
    }

    /**
     * M-1 Resolution: lookupQueryValues returns both canonical E.164 and raw
     * input so legacy unmigrated records with spaces or dashes are safely matched.
     */
    public function test_lookup_query_values_includes_canonical_and_raw_format(): void
    {
        $raw = '+1 (555) 000-0001';
        $candidates = $this->service->lookupQueryValues($raw);

        $this->assertContains('+15550000001', $candidates, 'Must include canonical E.164');
        $this->assertContains($raw, $candidates, 'Must include raw legacy format for backwards compatibility');
    }

    /**
     * Normalization is strictly idempotent.
     */
    public function test_normalization_is_idempotent(): void
    {
        $canonical = '+256700123456';
        $first = $this->service->normalize($canonical);
        $second = $this->service->normalize($first);
        $third = $this->service->normalize($second);

        $this->assertEquals($canonical, $first);
        $this->assertEquals($canonical, $second);
        $this->assertEquals($canonical, $third);
    }
}
