<?php

namespace Tests\Unit\Services;

use App\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * PhoneNormalizerTest
 *
 * Verifies FIND-CRIT-04 / R-7 phone normalization behavior:
 * - Equivalent format canonicalization
 * - International number support across multiple countries (not just Uganda)
 * - Invalid number detection
 * - Missing/null number handling
 * - Format equality checks
 */
class PhoneNormalizerTest extends TestCase
{
    /**
     * Equivalent formats of the same phone number must normalize to the identical E.164 string.
     */
    public function test_equivalent_formats_normalize_identically(): void
    {
        $expected = '+15552345678';

        $variations = [
            '+1 (555) 234-5678',
            '+1-555-234-5678',
            '+1.555.234.5678',
            '+1 555 234 5678',
            '0015552345678',
            '+15552345678',
        ];

        foreach ($variations as $variation) {
            $this->assertEquals(
                $expected,
                PhoneNormalizer::normalize($variation),
                "Failed asserting that [{$variation}] normalizes to [{$expected}]"
            );
            $this->assertTrue(PhoneNormalizer::isValid($variation));
        }

        // Test equality helper across formats
        $this->assertTrue(
            PhoneNormalizer::equals('+1 (555) 234-5678', '0015552345678')
        );
    }

    /**
     * International numbers across various country codes must normalize cleanly.
     * The system must not assume all numbers belong to Uganda (+256).
     */
    public function test_international_numbers_across_multiple_countries(): void
    {
        $testCases = [
            // United Kingdom (+44)
            '+44 20 7946 0958'   => '+442079460958',
            '0044 20 7946 0958'  => '+442079460958',

            // Kenya (+254)
            '+254 712 345678'    => '+254712345678',
            '00254 712 345678'   => '+254712345678',

            // Uganda (+256)
            '+256 700 123456'    => '+256700123456',
            '00256 700 123456'   => '+256700123456',

            // France (+33)
            '+33 1 42 68 55 55'  => '+33142685555',

            // Australia (+61)
            '+61 2 9374 4000'    => '+61293744000',

            // United States (+1)
            '+1 415 555 2671'    => '+14155552671',

            // United Arab Emirates (+971)
            '+971 4 455 6000'    => '+97144556000',
        ];

        foreach ($testCases as $input => $expected) {
            $this->assertEquals(
                $expected,
                PhoneNormalizer::normalize($input),
                "Failed asserting that [{$input}] normalizes to [{$expected}]"
            );
            $this->assertTrue(PhoneNormalizer::isE164($expected));
        }
    }

    /**
     * Local numbers starting with '0' without an explicit country context
     * must NOT be assumed to belong to Uganda.
     */
    public function test_local_numbers_do_not_assume_uganda_without_context(): void
    {
        $local = '0700 123 456';

        // Without explicit default country, cannot guess country
        $this->assertNull(PhoneNormalizer::normalize($local));
        $this->assertFalse(PhoneNormalizer::isValid($local));

        // When explicit country prefix is provided, it normalizes with that prefix
        $this->assertEquals(
            '+256700123456',
            PhoneNormalizer::normalize($local, '+256')
        );

        // When explicit UK prefix is provided, it normalizes with UK prefix
        $this->assertEquals(
            '+44700123456',
            PhoneNormalizer::normalize($local, '+44')
        );
    }

    /**
     * Invalid inputs must return null and report as invalid.
     */
    public function test_invalid_phone_numbers(): void
    {
        $invalidInputs = [
            'not-a-phone',
            '123',                     // Too short (< 7 digits)
            '+0123456',                // +0 is invalid country code
            '+12345678901234567890',   // Too long (> 15 digits)
            'TBD',                     // Placeholder string
            'tbd',
            '+++',
        ];

        foreach ($invalidInputs as $invalid) {
            $this->assertNull(
                PhoneNormalizer::normalize($invalid),
                "Failed asserting that [{$invalid}] returns null"
            );
            $this->assertFalse(
                PhoneNormalizer::isValid($invalid),
                "Failed asserting that [{$invalid}] is reported as invalid"
            );
        }
    }

    /**
     * Missing or empty values must return null safely without throwing errors.
     */
    public function test_missing_and_empty_values(): void
    {
        $this->assertNull(PhoneNormalizer::normalize(null));
        $this->assertNull(PhoneNormalizer::normalize(''));
        $this->assertNull(PhoneNormalizer::normalize('   '));
        $this->assertFalse(PhoneNormalizer::isValid(null));
        $this->assertFalse(PhoneNormalizer::isValid(''));
    }
}
