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

    private static $cachedEnvValue = null;
    private static $envValueLoaded = false;

    /**
     * Get base URL for environment
     * URLs are dynamically constructed based on the ENV environment variable.
     * If ENV is set to "dev", "test", or "stage", that subdomain is used.
     * If not set, defaults to "prod" (production).
     * LOCAL environment always uses localhost.
     * 
     * @param string $code Environment code
     * @return string Base URL
     */
    public static function getBaseUrlForCode($code)
    {
        if ($code === self::LOCAL) {
            return 'http://127.0.0.1:4000/unify';
        }

        $envValue = self::getEnvValue();
        $subdomain = ($envValue && trim($envValue)) ? strtolower(trim($envValue)) : 'prod';
        return "https://{$subdomain}.gets.complyance.io/unify";
    }

    /**
     * Gets the ENV value from system environment variable or .env files
     * 
     * @return string|null The ENV value or null if not found
     */
    private static function getEnvValue()
    {
        if (self::$envValueLoaded) {
            return self::$cachedEnvValue;
        }

        // First, check system environment variable
        $envValue = getenv('ENV');
        if ($envValue && trim($envValue)) {
            self::$cachedEnvValue = $envValue;
            self::$envValueLoaded = true;
            return $envValue;
        }

        // Try to read from .env files in common locations
        $envFilePaths = [
            '.env',
            '../.env',
            '../../.env',
            '../services/encore/.env',
            '../../services/encore/.env',
            'services/encore/.env'
        ];

        foreach ($envFilePaths as $filePath) {
            $envValue = self::readEnvFromFile($filePath);
            if ($envValue && trim($envValue)) {
                self::$cachedEnvValue = $envValue;
                self::$envValueLoaded = true;
                return $envValue;
            }
        }

        // No ENV found, cache null and return null
        self::$envValueLoaded = true;
        self::$cachedEnvValue = null;
        return null;
    }

    /**
     * Reads the ENV variable from a .env file
     * 
     * @param string $filePath Path to the .env file
     * @return string|null The ENV value or null if not found
     */
    private static function readEnvFromFile($filePath)
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        try {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }

                if (strpos($line, 'ENV=') === 0) {
                    $value = trim(substr($line, 4));
                    if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                        (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                        $value = substr($value, 1, -1);
                    }
                    return $value;
                }
            }
        } catch (Exception $e) {
            // Silently ignore - file might not be readable
        }

        return null;
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