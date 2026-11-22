<?php

namespace App\Services\Phone;

/**
 * PhoneNumberResult
 *
 * Immutable data transfer object representing a parsed, analyzed telephone number.
 */
class PhoneNumberResult
{
    public function __construct(
        public readonly string $raw,
        public readonly ?string $e164,
        public readonly ?string $countryCode,
        public readonly ?string $nationalNumber,
        public readonly ?string $regionCode,
        public readonly bool $isValid,
        public readonly ?string $formattedNational = null,
        public readonly ?string $formattedInternational = null
    ) {}

    public function toArray(): array
    {
        return [
            'raw'                     => $this->raw,
            'e164'                    => $this->e164,
            'country_code'            => $this->countryCode,
            'national_number'         => $this->nationalNumber,
            'region_code'             => $this->regionCode,
            'is_valid'                => $this->isValid,
            'formatted_national'      => $this->formattedNational,
            'formatted_international' => $this->formattedInternational,
        ];
    }
}
