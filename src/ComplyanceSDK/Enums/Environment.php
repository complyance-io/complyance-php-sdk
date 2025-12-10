<?php

namespace ComplyanceSDK\Enums;

/**
 * Environment enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class Environment
{
    const DEV = 'dev';
    const TEST = 'test';
    const STAGE = 'stage';
    const LOCAL = 'local';
    const SANDBOX = 'sandbox';
    const SIMULATION = 'simulation';
    const PRODUCTION = 'production';

    private $code;

    /**
     * Get environment name
     * 
     * @param string $code Environment code
     * @return string Environment name
     */
    public static function getName($code)
    {
        $names = [
            self::DEV => 'Development',
            self::TEST => 'Test',
            self::STAGE => 'Staging',
            self::LOCAL => 'Local',
            self::SANDBOX => 'Sandbox',
            self::SIMULATION => 'Simulation',
            self::PRODUCTION => 'Production',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Create Environment instance from string
     * 
     * @param string $code Environment code
     * @return Environment Environment instance
     */
    public static function from($code)
    {
        $instance = new self();
        $instance->code = $code;
        return $instance;
    }

    /**
     * Get the environment code
     * 
     * @return string Environment code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the base URL for this environment instance
     * 
     * @return string Base URL
     */
    public function getBaseUrl()
    {
        return self::getBaseUrlForCode($this->code);
    }

    /**
     * Legacy support - maintain backward compatibility
     * 
     * @deprecated Use getBaseUrl() instead
     * @return string Base URL
     */
    public function getDefaultBaseUrl()
    {
        return $this->getBaseUrl();
    }

    /**
     * Check if environment is production
     * 
     * @param string $code Environment code
     * @return bool True if production
     */
    public static function isProduction($code)
    {
        return in_array($code, [
            self::SANDBOX,
            self::SIMULATION,
            self::PRODUCTION
        ]);
    }

    /**
     * Check if environment is development
     * 
     * @param string $code Environment code
     * @return bool True if development
     */
    public static function isDevelopment($code)
    {
        return in_array($code, [
            self::DEV,
            self::TEST,
            self::STAGE,
            self::LOCAL
        ]);
    }

    /**
     * Get base URL for environment
     * 
     * @param string $code Environment code
     * @return string Base URL
     */
    public static function getBaseUrlForCode($code)
    {
        $urls = [
            self::DEV => 'https://prod.gets.complyance.io/unify',
            self::TEST => 'https://prod.gets.complyance.io/unify',
            self::STAGE => 'https://prod.gets.complyance.io/unify',
            self::LOCAL => 'http://127.0.0.1:4000/unify',
            self::SANDBOX => 'https://prod.gets.complyance.io/unify',     // Maps to DEV URL
            self::SIMULATION => 'https://prod.gets.complyance.io/unify',   // Maps to PROD URL
            self::PRODUCTION => 'https://prod.gets.complyance.io/unify'   // Production URL
        ];

        return isset($urls[$code]) ? $urls[$code] : 'https://prod.gets.complyance.io/unify';
    }

    /**
     * Get all environment codes
     * 
     * @return array Array of environment codes
     */
    public static function getAllCodes()
    {
        return [
            self::DEV, self::TEST, self::STAGE, self::LOCAL,
            self::SANDBOX, self::SIMULATION, self::PRODUCTION
        ];
    }

    /**
     * Check if environment code is valid
     * 
     * @param string $code Environment code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }
}