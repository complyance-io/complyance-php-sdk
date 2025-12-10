<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Models\CircuitBreakerConfig;

/**
 * Retry Configuration model
 * 
 * @package ComplyanceSDK\Models
 */
class RetryConfig
{
    private $maxAttempts;
    private $baseDelayMs;
    private $maxDelayMs;
    private $backoffMultiplier;
    private $jitter;
    private $circuitBreakerEnabled;
    private $circuitBreakerConfig;
    private $retryableErrorCodes;
    private $retryableHttpCodes;

    /**
     * Constructor
     * 
     * @param int $maxAttempts Maximum retry attempts
     * @param int $baseDelayMs Base delay in milliseconds
     * @param int $maxDelayMs Maximum delay in milliseconds
     * @param float $backoffMultiplier Backoff multiplier
     * @param bool $jitter Enable jitter
     */
    public function __construct(
        $maxAttempts = 5,
        $baseDelayMs = 500,
        $maxDelayMs = 30000,
        $backoffMultiplier = 2.0,
        $jitter = true,
        $circuitBreakerEnabled = true,
        $circuitBreakerConfig = null
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->baseDelayMs = $baseDelayMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->backoffMultiplier = $backoffMultiplier;
        $this->jitter = $jitter;
        $this->circuitBreakerEnabled = $circuitBreakerEnabled;
        $this->circuitBreakerConfig = $circuitBreakerConfig ?? ($circuitBreakerEnabled ? CircuitBreakerConfig::defaultConfig() : null);
        
        // Initialize retryable error codes and HTTP codes like Java SDK
        $this->retryableErrorCodes = [
            \ComplyanceSDK\Enums\ErrorCode::NETWORK_ERROR,
            \ComplyanceSDK\Enums\ErrorCode::TIMEOUT_ERROR,
            \ComplyanceSDK\Enums\ErrorCode::SERVER_ERROR,
            \ComplyanceSDK\Enums\ErrorCode::SERVICE_UNAVAILABLE,
            \ComplyanceSDK\Enums\ErrorCode::RATE_LIMIT_EXCEEDED,
            \ComplyanceSDK\Enums\ErrorCode::CIRCUIT_BREAKER_OPEN
        ];
        
        $this->retryableHttpCodes = [429, 500, 502, 503, 504];
    }

    /**
     * Get maximum attempts
     * 
     * @return int Maximum attempts
     */
    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }

    /**
     * Get base delay in milliseconds
     * 
     * @return int Base delay in milliseconds
     */
    public function getBaseDelayMs()
    {
        return $this->baseDelayMs;
    }

    /**
     * Get base delay (alias for compatibility)
     * 
     * @return int Base delay in milliseconds
     */
    public function getBaseDelay()
    {
        return $this->baseDelayMs;
    }

    /**
     * Get maximum delay in milliseconds
     * 
     * @return int Maximum delay in milliseconds
     */
    public function getMaxDelayMs()
    {
        return $this->maxDelayMs;
    }

    /**
     * Get maximum delay (alias for compatibility)
     * 
     * @return int Maximum delay in milliseconds
     */
    public function getMaxDelay()
    {
        return $this->maxDelayMs;
    }

    /**
     * Get backoff multiplier
     * 
     * @return float Backoff multiplier
     */
    public function getBackoffMultiplier()
    {
        return $this->backoffMultiplier;
    }

    /**
     * Check if jitter is enabled
     * 
     * @return bool True if jitter is enabled
     */
    public function isJitter()
    {
        return $this->jitter;
    }

    /**
     * Get jitter factor (alias for compatibility)
     * 
     * @return float Jitter factor
     */
    public function getJitterFactor()
    {
        return $this->jitter ? 0.25 : 0; // 25% jitter factor when enabled
    }

    /**
     * Check if circuit breaker is enabled
     * 
     * @return bool True if circuit breaker is enabled
     */
    public function isCircuitBreakerEnabled()
    {
        return $this->circuitBreakerEnabled;
    }

    /**
     * Get circuit breaker configuration
     * 
     * @return mixed Circuit breaker configuration
     */
    public function getCircuitBreakerConfig()
    {
        return $this->circuitBreakerConfig;
    }

    /**
     * Check if error code should be retried
     * 
     * @param int $errorCode Error code to check
     * @return bool True if should retry
     */
    public function shouldRetry($errorCode)
    {
        return in_array($errorCode, $this->retryableErrorCodes);
    }

    /**
     * Check if HTTP status code should be retried
     * 
     * @param int $httpCode HTTP status code to check
     * @return bool True if should retry
     */
    public function shouldRetryHttpCode($httpCode)
    {
        return in_array($httpCode, $this->retryableHttpCodes);
    }

    /**
     * Set maximum attempts
     * 
     * @param int $maxAttempts Maximum attempts
     * @return self
     */
    public function setMaxAttempts($maxAttempts)
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * Set base delay in milliseconds
     * 
     * @param int $baseDelayMs Base delay in milliseconds
     * @return self
     */
    public function setBaseDelayMs($baseDelayMs)
    {
        $this->baseDelayMs = $baseDelayMs;
        return $this;
    }

    /**
     * Set maximum delay in milliseconds
     * 
     * @param int $maxDelayMs Maximum delay in milliseconds
     * @return self
     */
    public function setMaxDelayMs($maxDelayMs)
    {
        $this->maxDelayMs = $maxDelayMs;
        return $this;
    }

    /**
     * Set backoff multiplier
     * 
     * @param float $backoffMultiplier Backoff multiplier
     * @return self
     */
    public function setBackoffMultiplier($backoffMultiplier)
    {
        $this->backoffMultiplier = $backoffMultiplier;
        return $this;
    }

    /**
     * Set jitter
     * 
     * @param bool $jitter Enable jitter
     * @return self
     */
    public function setJitter($jitter)
    {
        $this->jitter = $jitter;
        return $this;
    }

    /**
     * Calculate delay for attempt
     * 
     * @param int $attempt Current attempt (0-based)
     * @return int Delay in milliseconds
     */
    public function calculateDelay($attempt)
    {
        $delay = $this->baseDelayMs * pow($this->backoffMultiplier, $attempt);
        $delay = min($delay, $this->maxDelayMs);

        if ($this->jitter) {
            // Add jitter: ±25% of the delay
            $jitterRange = $delay * 0.25;
            $delay += mt_rand(-$jitterRange, $jitterRange);
        }

        return max(0, (int) $delay);
    }

    /**
     * Convert to array
     * 
     * @return array Array representation
     */
    public function toArray()
    {
        return [
            'maxAttempts' => $this->maxAttempts,
            'baseDelayMs' => $this->baseDelayMs,
            'maxDelayMs' => $this->maxDelayMs,
            'backoffMultiplier' => $this->backoffMultiplier,
            'jitter' => $this->jitter
        ];
    }

    /**
     * Convert to JSON
     * 
     * @return string JSON representation
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Create from array
     * 
     * @param array $data Array data
     * @return self
     */
    public static function fromArray($data)
    {
        return new self(
            $data['maxAttempts'] ?? 3,
            $data['baseDelayMs'] ?? 1000,
            $data['maxDelayMs'] ?? 30000,
            $data['backoffMultiplier'] ?? 2.0,
            $data['jitter'] ?? true
        );
    }

    /**
     * Create default configuration
     * 
     * @return self Default configuration
     */
    public static function defaultConfig()
    {
        return new self();
    }

    /**
     * Create conservative configuration
     * 
     * @return self Conservative configuration
     */
    public static function conservativeConfig()
    {
        return new self(
            5,      // maxAttempts
            2000,   // baseDelayMs
            60000,  // maxDelayMs
            2.0,    // backoffMultiplier
            true    // jitter
        );
    }

    /**
     * Create aggressive configuration
     * 
     * @return self Aggressive configuration
     */
    public static function aggressiveConfig()
    {
        return new self(
            2,      // maxAttempts
            500,    // baseDelayMs
            5000,   // maxDelayMs
            1.5,    // backoffMultiplier
            false   // jitter
        );
    }
}