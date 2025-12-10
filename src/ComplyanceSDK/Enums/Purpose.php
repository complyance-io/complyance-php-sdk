<?php

namespace ComplyanceSDK\Enums;

/**
 * Purpose enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class Purpose
{
    const INVOICING = 'INVOICING';
    const MAPPING = 'MAPPING';
    const VALIDATION = 'VALIDATION';
    const SUBMISSION = 'SUBMISSION';
    const TESTING = 'TESTING';

    private $code;

    /**
     * Get purpose name
     * 
     * @param string $code Purpose code
     * @return string Purpose name
     */
    public static function getName($code)
    {
        $names = [
            self::INVOICING => 'Invoicing',
            self::MAPPING => 'Mapping',
            self::VALIDATION => 'Validation',
            self::SUBMISSION => 'Submission',
            self::TESTING => 'Testing',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Check if purpose is invoicing
     * 
     * @param string $code Purpose code
     * @return bool True if invoicing
     */
    public static function isInvoicing($code)
    {
        return $code === self::INVOICING;
    }

    /**
     * Check if purpose is mapping
     * 
     * @param string $code Purpose code
     * @return bool True if mapping
     */
    public static function isMapping($code)
    {
        return $code === self::MAPPING;
    }

    /**
     * Check if purpose is validation
     * 
     * @param string $code Purpose code
     * @return bool True if validation
     */
    public static function isValidation($code)
    {
        return $code === self::VALIDATION;
    }

    /**
     * Check if purpose is submission
     * 
     * @param string $code Purpose code
     * @return bool True if submission
     */
    public static function isSubmission($code)
    {
        return $code === self::SUBMISSION;
    }

    /**
     * Check if purpose is testing
     * 
     * @param string $code Purpose code
     * @return bool True if testing
     */
    public static function isTesting($code)
    {
        return $code === self::TESTING;
    }

    /**
     * Get all purpose codes
     * 
     * @return array Array of purpose codes
     */
    public static function getAllCodes()
    {
        return [
            self::INVOICING, self::MAPPING, self::VALIDATION,
            self::SUBMISSION, self::TESTING
        ];
    }

    /**
     * Check if purpose code is valid
     * 
     * @param string $code Purpose code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }

    /**
     * Create Purpose instance from string
     * 
     * @param string $code Purpose code
     * @return Purpose Purpose instance
     */
    public static function from($code)
    {
        $instance = new self();
        $instance->code = $code;
        return $instance;
    }

    /**
     * Get the purpose code
     * 
     * @return string Purpose code
     */
    public function getCode()
    {
        return $this->code;
    }
}