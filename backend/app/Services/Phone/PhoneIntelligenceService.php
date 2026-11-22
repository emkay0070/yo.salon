<?php

namespace App\Services\Phone;

/**
 * PhoneIntelligenceService
 *
 * Comprehensive international phone parsing, formatting, validation, and
 * compatibility resolver. Integrates giggsey/libphonenumber-for-php when installed,
 * with zero-dependency ITU-T E.164 fallback.
 */
class PhoneIntelligenceService
{
    /**
     * Regex matching canonical ITU-T E.164 phone numbers (+, 1-9 country code, 6-14 subscriber digits).
     */
    public const E164_REGEX = '/^\+[1-9]\d{6,14}$/';

    /**
     * Parse raw phone string into a PhoneNumberResult DTO.
     *
     * @param string|null $input Raw input string
     * @param string|null $defaultRegionOrCallingCode Optional ISO region (e.g. 'UG', 'KE', 'US') or dial code ('+256')
     * @return PhoneNumberResult
     */
    public function parse(?string $input, ?string $defaultRegionOrCallingCode = null): PhoneNumberResult
    {
        if ($input === null) {
            return new PhoneNumberResult(raw: '', e164: null, countryCode: null, nationalNumber: null, regionCode: null, isValid: false);
        }

        $raw = trim($input);

        // Security / Data Integrity Guard (H-1): Email addresses must NEVER be parsed as phone numbers
        if ($this->isEmail($raw) || strtolower($raw) === 'tbd' || $raw === '') {
            return new PhoneNumberResult(raw: $raw, e164: null, countryCode: null, nationalNumber: null, regionCode: null, isValid: false);
        }

        // Delegate to libphonenumber if installed
        if (class_exists(\libphonenumber\PhoneNumberUtil::class)) {
            return $this->parseWithLibPhoneNumber($raw, $defaultRegionOrCallingCode);
        }

        return $this->parseNative($raw, $defaultRegionOrCallingCode);
    }

    /**
     * Native ITU-T E.164 parsing with PhoneMetadata.
     */
    protected function parseNative(string $raw, ?string $defaultRegionOrCallingCode = null): PhoneNumberResult
    {
        $working = $raw;

        // Replace leading international call access code '00' with '+'
        if (str_starts_with($working, '00')) {
            $working = '+' . substr($working, 2);
        }

        $hasPlus = str_starts_with($working, '+');
        $digitsOnly = preg_replace('/[^\d]/', '', $working);

        if ($digitsOnly === '') {
            return new PhoneNumberResult(raw: $raw, e164: null, countryCode: null, nationalNumber: null, regionCode: null, isValid: false);
        }

        $e164 = null;
        $countryCode = null;
        $nationalNumber = null;
        $regionCode = null;

        if ($hasPlus) {
            $candidate = '+' . $digitsOnly;
            if (preg_match(self::E164_REGEX, $candidate)) {
                $e164 = $candidate;
                [$countryCode, $nationalNumber, $regionCode] = $this->decomposeE164($digitsOnly);
            }
        } elseif ($defaultRegionOrCallingCode !== null) {
            $resolvedCallingCode = $this->resolveCallingCode($defaultRegionOrCallingCode);
            if ($resolvedCallingCode) {
                // Strip local trunk prefix '0' if present
                $national = str_starts_with($digitsOnly, '0') ? substr($digitsOnly, 1) : $digitsOnly;
                $candidate = '+' . $resolvedCallingCode . $national;
                if (preg_match(self::E164_REGEX, $candidate)) {
                    $e164 = $candidate;
                    $countryCode = $resolvedCallingCode;
                    $nationalNumber = $national;
                    $regionCode = PhoneMetadata::getRegionForCallingCode($resolvedCallingCode);
                }
            }
        } elseif (!str_starts_with($digitsOnly, '0')) {
            // Plausible international digits without '+' (e.g. 15551234567, 256700123456)
            $candidate = '+' . $digitsOnly;
            if (preg_match(self::E164_REGEX, $candidate)) {
                [$possibleCc, $possibleNat, $possibleRegion] = $this->decomposeE164($digitsOnly);
                if ($possibleRegion !== null) {
                    $e164 = $candidate;
                    $countryCode = $possibleCc;
                    $nationalNumber = $possibleNat;
                    $regionCode = $possibleRegion;
                }
            }
        }

        $isValid = ($e164 !== null);
        $formattedInternational = $isValid ? $this->formatInternational($e164, $countryCode, $nationalNumber) : null;
        $formattedNational = ($isValid && $nationalNumber) ? ('0' . $nationalNumber) : null;

        return new PhoneNumberResult(
            raw: $raw,
            e164: $e164,
            countryCode: $countryCode,
            nationalNumber: $nationalNumber,
            regionCode: $regionCode,
            isValid: $isValid,
            formattedNational: $formattedNational,
            formattedInternational: $formattedInternational
        );
    }

    /**
     * Fallback parsing when giggsey/libphonenumber-for-php is available.
     */
    protected function parseWithLibPhoneNumber(string $raw, ?string $defaultRegionOrCallingCode): PhoneNumberResult
    {
        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $defaultRegion = $this->resolveRegionCode($defaultRegionOrCallingCode) ?? 'ZZ';

        try {
            $numberProto = $phoneUtil->parse($raw, $defaultRegion);
            $isValid = $phoneUtil->isValidNumber($numberProto);
            $e164 = $isValid ? $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164) : null;
            $countryCode = (string) $numberProto->getCountryCode();
            $nationalNumber = (string) $numberProto->getNationalNumber();
            $regionCode = $phoneUtil->getRegionCodeForNumber($numberProto);

            return new PhoneNumberResult(
                raw: $raw,
                e164: $e164,
                countryCode: $countryCode,
                nationalNumber: $nationalNumber,
                regionCode: $regionCode,
                isValid: $isValid,
                formattedNational: $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::NATIONAL),
                formattedInternational: $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::INTERNATIONAL)
            );
        } catch (\libphonenumber\NumberParseException) {
            return new PhoneNumberResult(raw: $raw, e164: null, countryCode: null, nationalNumber: null, regionCode: null, isValid: false);
        }
    }

    /**
     * Normalize an input phone number to canonical E.164 string or null.
     */
    public function normalize(?string $input, ?string $defaultRegionOrCallingCode = null): ?string
    {
        return $this->parse($input, $defaultRegionOrCallingCode)->e164;
    }

    /**
     * Validate whether an input phone number is valid E.164.
     */
    public function isValid(?string $input, ?string $defaultRegionOrCallingCode = null): bool
    {
        return $this->parse($input, $defaultRegionOrCallingCode)->isValid;
    }

    /**
     * Format a number for international display.
     */
    public function formatInternational(?string $e164, ?string $countryCode, ?string $nationalNumber): ?string
    {
        if (!$e164) {
            return null;
        }

        if ($countryCode && $nationalNumber) {
            return "+{$countryCode} {$nationalNumber}";
        }

        return $e164;
    }

    /**
     * Return search candidates for legacy unmigrated database lookups (Resolves M-1).
     * Returns an array with normalized E.164 and the raw input (if different) to guarantee
     * that historical unmigrated rows containing spaces or dashes are still found.
     *
     * @return string[]
     */
    public function lookupQueryValues(?string $input, ?string $defaultRegionOrCallingCode = null): array
    {
        if ($input === null || trim($input) === '') {
            return [];
        }

        $raw = trim($input);
        $normalized = $this->normalize($raw, $defaultRegionOrCallingCode);

        $values = [];
        if ($normalized) {
            $values[] = $normalized;
        }
        if (!in_array($raw, $values, true)) {
            $values[] = $raw;
        }

        return $values;
    }

    /**
     * Decompose E.164 digits into calling code, national number, and region.
     */
    protected function decomposeE164(string $digits): array
    {
        // Try 3-digit calling codes first, then 2-digit, then 1-digit
        for ($len = 3; $len >= 1; $len--) {
            $prefix = substr($digits, 0, $len);
            if (isset(PhoneMetadata::CALLING_CODE_TO_REGION[$prefix])) {
                return [
                    $prefix,
                    substr($digits, $len),
                    PhoneMetadata::CALLING_CODE_TO_REGION[$prefix],
                ];
            }
        }

        return [null, $digits, null];
    }

    /**
     * Resolve calling code from region code or dial prefix.
     */
    public function resolveCallingCode(?string $regionOrCode): ?string
    {
        if ($regionOrCode === null) {
            return null;
        }

        $clean = trim($regionOrCode);
        if (str_starts_with($clean, '+')) {
            return ltrim($clean, '+');
        }

        if (ctype_alpha($clean)) {
            return PhoneMetadata::getCallingCodeForRegion($clean);
        }

        return ltrim(preg_replace('/[^\d]/', '', $clean), '0');
    }

    /**
     * Resolve ISO region code from country or calling code.
     */
    public function resolveRegionCode(?string $regionOrCode): ?string
    {
        if ($regionOrCode === null) {
            return null;
        }

        $clean = trim($regionOrCode);
        if (ctype_alpha($clean) && strlen($clean) === 2) {
            return strtoupper($clean);
        }

        $code = $this->resolveCallingCode($clean);
        return PhoneMetadata::getRegionForCallingCode($code);
    }

    /**
     * Guard: determine if string is an email address.
     */
    public function isEmail(?string $string): bool
    {
        if ($string === null) {
            return false;
        }

        return str_contains($string, '@');
    }
}
