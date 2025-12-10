<?php

namespace ComplyanceSDK\Enums;

/**
 * Mode enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class Mode
{
    const DOCUMENTS = 'DOCUMENTS';
    const TEMPLATES = 'TEMPLATES';
    const MAPPING = 'MAPPING';
    const VALIDATION = 'VALIDATION';
    const SUBMISSION = 'SUBMISSION';

    private $code;

    /**
     * Get mode name
     * 
     * @param string $code Mode code
     * @return string Mode name
     */
    public static function getName($code)
    {
        $names = [
            self::DOCUMENTS => 'Documents',
            self::TEMPLATES => 'Templates',
            self::MAPPING => 'Mapping',
            self::VALIDATION => 'Validation',
            self::SUBMISSION => 'Submission',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Check if mode is documents
     * 
     * @param string $code Mode code
     * @return bool True if documents
     */
    public static function isDocuments($code)
    {
        return $code === self::DOCUMENTS;
    }

    /**
     * Check if mode is templates
     * 
     * @param string $code Mode code
     * @return bool True if templates
     */
    public static function isTemplates($code)
    {
        return $code === self::TEMPLATES;
    }

    /**
     * Check if mode is mapping
     * 
     * @param string $code Mode code
     * @return bool True if mapping
     */
    public static function isMapping($code)
    {
        return $code === self::MAPPING;
    }

    /**
     * Check if mode is validation
     * 
     * @param string $code Mode code
     * @return bool True if validation
     */
    public static function isValidation($code)
    {
        return $code === self::VALIDATION;
    }

    /**
     * Check if mode is submission
     * 
     * @param string $code Mode code
     * @return bool True if submission
     */
    public static function isSubmission($code)
    {
        return $code === self::SUBMISSION;
    }

    /**
     * Get all mode codes
     * 
     * @return array Array of mode codes
     */
    public static function getAllCodes()
    {
        return [
            self::DOCUMENTS, self::TEMPLATES, self::MAPPING,
            self::VALIDATION, self::SUBMISSION
        ];
    }

    /**
     * Check if mode code is valid
     * 
     * @param string $code Mode code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }

    /**
     * Create Mode instance from string
     * 
     * @param string $code Mode code
     * @return Mode Mode instance
     */
    public static function from($code)
    {
        $instance = new self();
        $instance->code = $code;
        return $instance;
    }

    /**
     * Get the mode code
     * 
     * @return string Mode code
     */
    public function getCode()
    {
        return $this->code;
    }
}
