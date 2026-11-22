<?php

namespace App\Services;

use App\Services\Phone\PhoneIntelligenceService;

/**
 * PhoneNormalizer
 *
 * Facade/delegator over PhoneIntelligenceService, maintaining backward compatibility
 * with all existing customer identity, booking, and authorization flows.
 */
class PhoneNormalizer
{
    public const E164_REGEX = PhoneIntelligenceService::E164_REGEX;

    protected static ?PhoneIntelligenceService $service = null;

    protected static function service(): PhoneIntelligenceService
    {
        if (self::$service === null) {
            self::$service = new PhoneIntelligenceService();
        }
        return self::$service;
    }

    /**
     * Normalize a phone number to canonical E.164 format.
     */
    public static function normalize(?string $phone, ?string $defaultCountryPrefix = null): ?string
    {
        return self::service()->normalize($phone, $defaultCountryPrefix);
    }

    /**
     * Check if a phone number is valid E.164 format.
     */
    public static function isE164(string $phone): bool
    {
        return (bool) preg_match(self::E164_REGEX, $phone);
    }

    /**
     * Validate whether a phone number can be normalized to valid E.164.
     */
    public static function isValid(?string $phone, ?string $defaultCountryPrefix = null): bool
    {
        return self::service()->isValid($phone, $defaultCountryPrefix);
    }

    /**
     * Check equality between two phone numbers under normalization.
     */
    public static function equals(?string $phoneA, ?string $phoneB, ?string $defaultCountryPrefix = null): bool
    {
        $normA = self::normalize($phoneA, $defaultCountryPrefix);
        $normB = self::normalize($phoneB, $defaultCountryPrefix);

        if ($normA === null || $normB === null) {
            return false;
        }

        return $normA === $normB;
    }

    /**
     * Get lookup values for legacy unmigrated compatibility (M-1).
     *
     * @return string[]
     */
    public static function lookupValues(?string $phone, ?string $defaultCountryPrefix = null): array
    {
        return self::service()->lookupQueryValues($phone, $defaultCountryPrefix);
    }
}
