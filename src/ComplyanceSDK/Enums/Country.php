<?php

namespace ComplyanceSDK\Enums;

/**
 * Country enumeration for supported countries
 * 
 * @package ComplyanceSDK\Enums
 */
class Country
{
    const SA = 'SA'; // Saudi Arabia
    const MY = 'MY'; // Malaysia
    const AE = 'AE'; // United Arab Emirates
    const SG = 'SG'; // Singapore
    const EG = 'EG'; // Egypt
    const IN = 'IN'; // India
    const US = 'US'; // United States
    const GB = 'GB'; // United Kingdom
    const DE = 'DE'; // Germany
    const FR = 'FR'; // France
    const IT = 'IT'; // Italy
    const ES = 'ES'; // Spain
    const NL = 'NL'; // Netherlands
    const BE = 'BE'; // Belgium
    const AT = 'AT'; // Austria
    const CH = 'CH'; // Switzerland
    const SE = 'SE'; // Sweden
    const NO = 'NO'; // Norway
    const DK = 'DK'; // Denmark
    const FI = 'FI'; // Finland
    const PL = 'PL'; // Poland
    const CZ = 'CZ'; // Czech Republic
    const HU = 'HU'; // Hungary
    const RO = 'RO'; // Romania
    const BG = 'BG'; // Bulgaria
    const HR = 'HR'; // Croatia
    const SI = 'SI'; // Slovenia
    const SK = 'SK'; // Slovakia
    const LT = 'LT'; // Lithuania
    const LV = 'LV'; // Latvia
    const EE = 'EE'; // Estonia
    const CY = 'CY'; // Cyprus
    const MT = 'MT'; // Malta
    const LU = 'LU'; // Luxembourg
    const IE = 'IE'; // Ireland
    const PT = 'PT'; // Portugal
    const GR = 'GR'; // Greece

    private $code;

    /**
     * Get country name
     * 
     * @param string $code Country code
     * @return string Country name
     */
    public static function getName($code)
    {
        $names = [
            self::SA => 'Saudi Arabia',
            self::MY => 'Malaysia',
            self::AE => 'United Arab Emirates',
            self::SG => 'Singapore',
            self::EG => 'Egypt',
            self::IN => 'India',
            self::US => 'United States',
            self::GB => 'United Kingdom',
            self::DE => 'Germany',
            self::FR => 'France',
            self::IT => 'Italy',
            self::ES => 'Spain',
            self::NL => 'Netherlands',
            self::BE => 'Belgium',
            self::AT => 'Austria',
            self::CH => 'Switzerland',
            self::SE => 'Sweden',
            self::NO => 'Norway',
            self::DK => 'Denmark',
            self::FI => 'Finland',
            self::PL => 'Poland',
            self::CZ => 'Czech Republic',
            self::HU => 'Hungary',
            self::RO => 'Romania',
            self::BG => 'Bulgaria',
            self::HR => 'Croatia',
            self::SI => 'Slovenia',
            self::SK => 'Slovakia',
            self::LT => 'Lithuania',
            self::LV => 'Latvia',
            self::EE => 'Estonia',
            self::CY => 'Cyprus',
            self::MT => 'Malta',
            self::LU => 'Luxembourg',
            self::IE => 'Ireland',
            self::PT => 'Portugal',
            self::GR => 'Greece',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Create Country instance from string
     * 
     * @param string $code Country code
     * @return Country Country instance
     */
    public static function from($code)
    {
        $instance = new self();
        $instance->code = $code;
        return $instance;
    }

    /**
     * Get the country code
     * 
     * @return string Country code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Check if country is supported for production
     * SA, MY, and AE (UAE) are allowed in SANDBOX/PRODUCTION; AE not in SIMULATION.
     *
     * @param string $code Country code
     * @return bool True if supported for production
     */
    public static function isProductionSupported($code)
    {
        return in_array($code, [self::SA, self::MY, self::AE]);
    }

    /**
     * Get default tax authority for country
     * 
     * @param string $code Country code
     * @return string|null Tax authority code or null
     */
    public static function getDefaultTaxAuthority($code)
    {
        $authorities = [
            self::SA => 'ZATCA',
            self::MY => 'LHDN',
            self::AE => 'FTA',
            self::SG => 'IRAS',
        ];

        return isset($authorities[$code]) ? $authorities[$code] : null;
    }

    /**
     * Get all supported countries
     * 
     * @return array Array of country codes
     */
    public static function getAllCodes()
    {
        return [
            self::SA, self::MY, self::AE, self::SG, self::EG, self::IN,
            self::US, self::GB, self::DE, self::FR, self::IT, self::ES,
            self::NL, self::BE, self::AT, self::CH, self::SE, self::NO,
            self::DK, self::FI, self::PL, self::CZ, self::HU, self::RO,
            self::BG, self::HR, self::SI, self::SK, self::LT, self::LV,
            self::EE, self::CY, self::MT, self::LU, self::IE, self::PT, self::GR
        ];
    }

    /**
     * Check if country code is valid
     * 
     * @param string $code Country code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }
}