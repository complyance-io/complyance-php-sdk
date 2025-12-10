<?php

namespace ComplyanceSDK\Enums;

/**
 * Error Code enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class ErrorCode
{
    const UNKNOWN_ERROR = 1000;
    const MISSING_FIELD = 1001;
    const INVALID_FIELD = 1002;
    const VALIDATION_FAILED = 1003;
    const AUTHENTICATION_FAILED = 1004;
    const AUTHORIZATION_FAILED = 1005;
    const RATE_LIMIT_EXCEEDED = 1006;
    const QUOTA_EXCEEDED = 1007;
    const INVALID_SOURCE = 1008;
    const INVALID_COUNTRY = 1009;
    const INVALID_DOCUMENT_TYPE = 1010;
    const INVALID_PAYLOAD = 1011;
    const NETWORK_ERROR = 1012;
    const TIMEOUT_ERROR = 1013;
    const SERVER_ERROR = 1014;
    const SERVICE_UNAVAILABLE = 1015;
    const INVALID_ARGUMENT = 1016;
    const CONFIGURATION_ERROR = 1017;
    const QUEUE_ERROR = 1018;
    const RETRY_ERROR = 1019;
    const COUNTRY_NOT_SUPPORTED = 1020;
    const DOCUMENT_TYPE_NOT_SUPPORTED = 1021;
    const SOURCE_NOT_FOUND = 1022;
    const TEMPLATE_NOT_FOUND = 1023;
    const MAPPING_FAILED = 1024;
    const CONVERSION_FAILED = 1025;
    const SUBMISSION_FAILED = 1026;
    const VALIDATION_ENGINE_ERROR = 1027;
    const GOVERNMENT_API_ERROR = 1028;
    const ZATCA_VALIDATION_FAILED = 1029;
    const LHDN_VALIDATION_FAILED = 1030;
    const FTA_VALIDATION_FAILED = 1031;
    const IRAS_VALIDATION_FAILED = 1032;
    const API_ERROR = 1033;
    const CIRCUIT_BREAKER_OPEN = 1034;

    /**
     * Get error code name
     * 
     * @param int $code Error code
     * @return string Error code name
     */
    public static function getName($code)
    {
        $names = [
            self::UNKNOWN_ERROR => 'Unknown Error',
            self::MISSING_FIELD => 'Missing Field',
            self::INVALID_FIELD => 'Invalid Field',
            self::VALIDATION_FAILED => 'Validation Failed',
            self::AUTHENTICATION_FAILED => 'Authentication Failed',
            self::AUTHORIZATION_FAILED => 'Authorization Failed',
            self::RATE_LIMIT_EXCEEDED => 'Rate Limit Exceeded',
            self::QUOTA_EXCEEDED => 'Quota Exceeded',
            self::INVALID_SOURCE => 'Invalid Source',
            self::INVALID_COUNTRY => 'Invalid Country',
            self::INVALID_DOCUMENT_TYPE => 'Invalid Document Type',
            self::INVALID_PAYLOAD => 'Invalid Payload',
            self::NETWORK_ERROR => 'Network Error',
            self::TIMEOUT_ERROR => 'Timeout Error',
            self::SERVER_ERROR => 'Server Error',
            self::SERVICE_UNAVAILABLE => 'Service Unavailable',
            self::INVALID_ARGUMENT => 'Invalid Argument',
            self::CONFIGURATION_ERROR => 'Configuration Error',
            self::QUEUE_ERROR => 'Queue Error',
            self::RETRY_ERROR => 'Retry Error',
            self::COUNTRY_NOT_SUPPORTED => 'Country Not Supported',
            self::DOCUMENT_TYPE_NOT_SUPPORTED => 'Document Type Not Supported',
            self::SOURCE_NOT_FOUND => 'Source Not Found',
            self::TEMPLATE_NOT_FOUND => 'Template Not Found',
            self::MAPPING_FAILED => 'Mapping Failed',
            self::CONVERSION_FAILED => 'Conversion Failed',
            self::SUBMISSION_FAILED => 'Submission Failed',
            self::VALIDATION_ENGINE_ERROR => 'Validation Engine Error',
            self::GOVERNMENT_API_ERROR => 'Government API Error',
            self::ZATCA_VALIDATION_FAILED => 'ZATCA Validation Failed',
            self::LHDN_VALIDATION_FAILED => 'LHDN Validation Failed',
            self::FTA_VALIDATION_FAILED => 'FTA Validation Failed',
            self::IRAS_VALIDATION_FAILED => 'IRAS Validation Failed',
            self::API_ERROR => 'API Error',
            self::CIRCUIT_BREAKER_OPEN => 'Circuit Breaker Open',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown Error';
    }

    /**
     * Check if error is retryable
     * 
     * @param int $code Error code
     * @return bool True if retryable
     */
    public static function isRetryable($code)
    {
        return in_array($code, [
            self::NETWORK_ERROR,
            self::TIMEOUT_ERROR,
            self::SERVER_ERROR,
            self::SERVICE_UNAVAILABLE,
            self::RATE_LIMIT_EXCEEDED,
            self::QUEUE_ERROR,
            self::RETRY_ERROR,
            self::GOVERNMENT_API_ERROR
        ]);
    }

    /**
     * Check if error is client error
     * 
     * @param int $code Error code
     * @return bool True if client error
     */
    public static function isClientError($code)
    {
        return in_array($code, [
            self::MISSING_FIELD,
            self::INVALID_FIELD,
            self::VALIDATION_FAILED,
            self::AUTHENTICATION_FAILED,
            self::AUTHORIZATION_FAILED,
            self::INVALID_SOURCE,
            self::INVALID_COUNTRY,
            self::INVALID_DOCUMENT_TYPE,
            self::INVALID_PAYLOAD,
            self::INVALID_ARGUMENT,
            self::CONFIGURATION_ERROR,
            self::COUNTRY_NOT_SUPPORTED,
            self::DOCUMENT_TYPE_NOT_SUPPORTED,
            self::SOURCE_NOT_FOUND,
            self::TEMPLATE_NOT_FOUND
        ]);
    }

    /**
     * Check if error is server error
     * 
     * @param int $code Error code
     * @return bool True if server error
     */
    public static function isServerError($code)
    {
        return in_array($code, [
            self::NETWORK_ERROR,
            self::TIMEOUT_ERROR,
            self::SERVER_ERROR,
            self::SERVICE_UNAVAILABLE,
            self::QUEUE_ERROR,
            self::RETRY_ERROR,
            self::VALIDATION_ENGINE_ERROR,
            self::GOVERNMENT_API_ERROR
        ]);
    }

    /**
     * Check if error is validation error
     * 
     * @param int $code Error code
     * @return bool True if validation error
     */
    public static function isValidationError($code)
    {
        return in_array($code, [
            self::VALIDATION_FAILED,
            self::MAPPING_FAILED,
            self::CONVERSION_FAILED,
            self::ZATCA_VALIDATION_FAILED,
            self::LHDN_VALIDATION_FAILED,
            self::FTA_VALIDATION_FAILED,
            self::IRAS_VALIDATION_FAILED
        ]);
    }

    /**
     * Get suggested HTTP status code
     * 
     * @param int $code Error code
     * @return int HTTP status code
     */
    public static function getSuggestedHttpStatusCode($code)
    {
        if (in_array($code, [
            self::MISSING_FIELD, self::INVALID_FIELD, self::VALIDATION_FAILED,
            self::INVALID_SOURCE, self::INVALID_COUNTRY, self::INVALID_DOCUMENT_TYPE,
            self::INVALID_PAYLOAD, self::INVALID_ARGUMENT, self::COUNTRY_NOT_SUPPORTED,
            self::DOCUMENT_TYPE_NOT_SUPPORTED, self::SOURCE_NOT_FOUND,
            self::TEMPLATE_NOT_FOUND
        ])) {
            return 400;
        }
        
        if ($code === self::AUTHENTICATION_FAILED) {
            return 401;
        }
        
        if ($code === self::AUTHORIZATION_FAILED) {
            return 403;
        }
        
        if (in_array($code, [self::RATE_LIMIT_EXCEEDED, self::QUOTA_EXCEEDED])) {
            return 429;
        }
        
        if (in_array($code, [
            self::NETWORK_ERROR, self::TIMEOUT_ERROR, self::SERVER_ERROR,
            self::SERVICE_UNAVAILABLE, self::QUEUE_ERROR, self::RETRY_ERROR,
            self::VALIDATION_ENGINE_ERROR, self::GOVERNMENT_API_ERROR
        ])) {
            return 500;
        }
        
        return 500;
    }

    /**
     * Get all error code values
     * 
     * @return array Array of error code values
     */
    public static function getAllValues()
    {
        return [
            self::UNKNOWN_ERROR, self::MISSING_FIELD, self::INVALID_FIELD,
            self::VALIDATION_FAILED, self::AUTHENTICATION_FAILED, self::AUTHORIZATION_FAILED,
            self::RATE_LIMIT_EXCEEDED, self::QUOTA_EXCEEDED, self::INVALID_SOURCE,
            self::INVALID_COUNTRY, self::INVALID_DOCUMENT_TYPE, self::INVALID_PAYLOAD,
            self::NETWORK_ERROR, self::TIMEOUT_ERROR, self::SERVER_ERROR,
            self::SERVICE_UNAVAILABLE, self::INVALID_ARGUMENT, self::CONFIGURATION_ERROR,
            self::QUEUE_ERROR, self::RETRY_ERROR, self::COUNTRY_NOT_SUPPORTED,
            self::DOCUMENT_TYPE_NOT_SUPPORTED, self::SOURCE_NOT_FOUND, self::TEMPLATE_NOT_FOUND,
            self::MAPPING_FAILED, self::CONVERSION_FAILED, self::SUBMISSION_FAILED,
            self::VALIDATION_ENGINE_ERROR, self::GOVERNMENT_API_ERROR, self::ZATCA_VALIDATION_FAILED,
            self::LHDN_VALIDATION_FAILED, self::FTA_VALIDATION_FAILED, self::IRAS_VALIDATION_FAILED,
            self::API_ERROR, self::CIRCUIT_BREAKER_OPEN
        ];
    }

    /**
     * Check if error code value is valid
     * 
     * @param int $value Error code value
     * @return bool True if valid
     */
    public static function isValid($value)
    {
        return in_array($value, self::getAllValues());
    }
}