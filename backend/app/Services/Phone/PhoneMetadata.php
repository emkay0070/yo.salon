<?php

namespace App\Services\Phone;

/**
 * PhoneMetadata
 *
 * Comprehensive ITU-T E.164 country calling codes and numbering plan metadata.
 * Does not assume Uganda or any single nation.
 */
class PhoneMetadata
{
    /**
     * Map of ISO 3166-1 alpha-2 country codes to primary calling codes.
     */
    public const REGION_TO_CALLING_CODE = [
        // Africa
        'UG' => '256',
        'KE' => '254',
        'TZ' => '255',
        'RW' => '250',
        'BI' => '257',
        'SS' => '211',
        'NG' => '234',
        'GH' => '233',
        'ZA' => '27',
        'EG' => '20',
        'ET' => '251',
        'MA' => '212',

        // Americas
        'US' => '1',
        'CA' => '1',
        'MX' => '52',
        'BR' => '55',
        'CO' => '57',
        'AR' => '54',
        'CL' => '56',

        // Europe
        'GB' => '44',
        'FR' => '33',
        'DE' => '49',
        'IT' => '39',
        'ES' => '34',
        'NL' => '31',
        'BE' => '32',
        'CH' => '41',
        'SE' => '46',
        'NO' => '47',
        'IE' => '353',

        // Middle East & Asia
        'AE' => '971',
        'SA' => '966',
        'QA' => '974',
        'IN' => '91',
        'PK' => '92',
        'CN' => '86',
        'JP' => '81',
        'KR' => '82',
        'SG' => '65',
        'MY' => '60',
        'AU' => '61',
        'NZ' => '64',
    ];

    /**
     * Map of calling codes to primary ISO region.
     */
    public const CALLING_CODE_TO_REGION = [
        '1'   => 'US',
        '20'  => 'EG',
        '27'  => 'ZA',
        '31'  => 'NL',
        '32'  => 'BE',
        '33'  => 'FR',
        '34'  => 'ES',
        '39'  => 'IT',
        '41'  => 'CH',
        '44'  => 'GB',
        '46'  => 'SE',
        '47'  => 'NO',
        '49'  => 'DE',
        '52'  => 'MX',
        '54'  => 'AR',
        '55'  => 'BR',
        '56'  => 'CL',
        '57'  => 'CO',
        '60'  => 'MY',
        '61'  => 'AU',
        '64'  => 'NZ',
        '65'  => 'SG',
        '81'  => 'JP',
        '82'  => 'KR',
        '86'  => 'CN',
        '91'  => 'IN',
        '92'  => 'PK',
        '211' => 'SS',
        '212' => 'MA',
        '233' => 'GH',
        '234' => 'NG',
        '250' => 'RW',
        '251' => 'ET',
        '254' => 'KE',
        '255' => 'TZ',
        '256' => 'UG',
        '257' => 'BI',
        '353' => 'IE',
        '966' => 'SA',
        '971' => 'AE',
        '974' => 'QA',
    ];

    /**
     * National trunk prefix (leading 0 in local dialing).
     */
    public const NATIONAL_TRUNK_PREFIX = '0';

    /**
     * Resolve calling code for a given ISO 3166-1 alpha-2 region.
     */
    public static function getCallingCodeForRegion(?string $region): ?string
    {
        if ($region === null) {
            return null;
        }

        return self::REGION_TO_CALLING_CODE[strtoupper(trim($region))] ?? null;
    }

    /**
     * Resolve ISO region for a given calling code.
     */
    public static function getRegionForCallingCode(?string $callingCode): ?string
    {
        if ($callingCode === null) {
            return null;
        }

        $clean = ltrim(preg_replace('/[^\d]/', '', $callingCode), '0');
        return self::CALLING_CODE_TO_REGION[$clean] ?? null;
    }
}
