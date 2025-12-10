<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Enums\ErrorCode;
use ComplyanceSDK\Models\ErrorDetail;
use ComplyanceSDK\Models\CircuitState;

/**
 * Advanced retry strategy with exponential backoff, jitter, and circuit breaker
 */
class RetryStrategy
{
    private $config;
    private $circuitBreaker;

    public function __construct($config, $circuitBreaker = null)
    {
        $this->config = $config;
        if ($circuitBreaker !== null) {
            $this->circuitBreaker = $circuitBreaker;
        } else {
            $this->circuitBreaker = $config->isCircuitBreakerEnabled() 
                ? new CircuitBreaker($config->getCircuitBreakerConfig())
                : null;
        }
    }

    /**
     * Execute a function with retry logic
     */
    public function execute($operation, $operationName)
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->config->getMaxAttempts()) {
            try {

                $result;
                
                // Use circuit breaker if enabled
                if ($this->circuitBreaker !== null) {
                    echo "Circuit breaker state: " . $this->circuitBreaker->getState() . ", failures: " . $this->circuitBreaker->getFailureCount() . "\n";
                    $result = $this->circuitBreaker->execute(function() use ($operation) {
                        try {
                            return $operation();
                        } catch (\Exception $e) {
                            // Wrap all exceptions, but preserve SDKException type in cause
                            if ($e instanceof SDKException) {
                                throw new \RuntimeException("SDK_EXCEPTION_WRAPPER", 0, $e);
                            } else {
                                throw new \RuntimeException($e->getMessage(), 0, $e);
                            }
                        }
                    });
                } else {
                    $result = $operation();
                }

                if ($attempt > 1) {
                    // Check if this was a circuit breaker recovery
                    if ($this->circuitBreaker !== null && $this->circuitBreaker->getState() === CircuitState::HALF_OPEN) {
                        echo "🎉 SUCCESS: Operation '$operationName' succeeded after circuit breaker recovery on attempt $attempt\n";
                    } else {
                        echo "🎉 SUCCESS: Operation '$operationName' succeeded on attempt $attempt\n";
                    }
                } else {
                    echo "✅ SUCCESS: Operation '$operationName' succeeded on first attempt\n";
                }

                return $result;

            } catch (SDKException $e) {
                $lastException = $e;

                // Check if this error should be retried
                if (!$this->shouldRetry($e, $attempt)) {
                    throw $e;
                }

                // Special case: if circuit breaker is open, we need to continue retrying
                // even if we've exceeded max attempts, because we need to wait for timeout
                $detail = $e->getErrorDetail();
                $isCircuitBreakerOpen = $detail && $detail->getCode() === \ComplyanceSDK\Enums\ErrorCode::CIRCUIT_BREAKER_OPEN;
                
                // If this was the last attempt and it's not a circuit breaker open error, don't wait
                if ($attempt >= $this->config->getMaxAttempts() && !$isCircuitBreakerOpen) {
                    break;
                }

                // If circuit breaker is open, the shouldRetry method already handled the timeout
                // so we don't need additional delay here
                if (!$isCircuitBreakerOpen) {
                    // Calculate delay and wait for normal retries
                    $delay = $this->calculateDelay($attempt);
                    usleep($delay * 1000); // Convert to microseconds
                }

            } catch (\Exception $e) {
                // Check if this is a wrapped SDKException from circuit breaker
                if ($e instanceof \RuntimeException && $e->getMessage() === "SDK_EXCEPTION_WRAPPER" && $e->getPrevious() instanceof SDKException) {
                    // Handle our special SDKException wrapper
                    $sdkException = $e->getPrevious();
                    $lastException = $sdkException;
                    
                    // Check if this error should be retried
                    if (!$this->shouldRetry($sdkException, $attempt)) {
                        throw $sdkException;
                    }
                    
                    // Special case: if circuit breaker is open, we need to continue retrying
                    // even if we've exceeded max attempts, because we need to wait for timeout
                    $detail = $sdkException->getErrorDetail();
                    $isCircuitBreakerOpen = $detail && $detail->getCode() === \ComplyanceSDK\Enums\ErrorCode::CIRCUIT_BREAKER_OPEN;
                    
                    // If this was the last attempt and it's not a circuit breaker open error, don't wait
                    if ($attempt >= $this->config->getMaxAttempts() && !$isCircuitBreakerOpen) {
                        break;
                    }
                    
                    // If circuit breaker is open, the shouldRetry method already handled the timeout
                    // so we don't need additional delay here
                    if (!$isCircuitBreakerOpen) {
                        // Calculate delay and wait for normal retries
                        $delay = $this->calculateDelay($attempt);
                        usleep($delay * 1000); // Convert to microseconds
                    }
                } else {
                    // Unexpected non-SDK exception

                    $error = new ErrorDetail(ErrorCode::PROCESSING_ERROR, "Unexpected error: " . $e->getMessage());
                    $error->setSuggestion("This appears to be an unexpected error. Please contact support if it persists");
                    $error->addContextValue("originalException", get_class($e));
                    throw new SDKException($error);
                }
            }

            $attempt++;
        }

        // All retries exhausted
        throw $lastException !== null ? $lastException : new SDKException(ErrorDetail::maxRetriesExceeded($this->config->getMaxAttempts()));
    }

    /**
     * Determine if an error should be retried
     */
    private function shouldRetry(SDKException $e, $attempt)
    {
        $detail = $e->getErrorDetail();
        $isCircuitBreakerOpen = $detail && $detail->getCode() === \ComplyanceSDK\Enums\ErrorCode::CIRCUIT_BREAKER_OPEN;
        
        // Allow retry for circuit breaker open errors even if we've exceeded max attempts
        if ($attempt >= $this->config->getMaxAttempts() && !$isCircuitBreakerOpen) {
            return false;
        }

        // Always retry at least once, even for non-retryable errors
        if ($attempt == 1) {
            echo "First retry attempt for error: " . $e->getMessage() . "\n";
            return true;
        }

        if ($detail === null) {
            return false;
        }

        // Special handling for circuit breaker open - wait for timeout and attempt recovery
        if ($detail->getCode() === \ComplyanceSDK\Enums\ErrorCode::CIRCUIT_BREAKER_OPEN) {
            // Extract remaining time from error message
            if (preg_match('/(\d+) seconds remaining/', $e->getMessage(), $matches)) {
                $remainingSeconds = (int) $matches[1];
                echo "⏳ Circuit breaker is OPEN - waiting {$remainingSeconds} seconds for timeout...\n";
                
                // Wait for the remaining timeout period with countdown
                if ($remainingSeconds > 0) {
                    echo "🕐 Waiting {$remainingSeconds} seconds before attempting recovery...\n";
                    
                    // Show countdown timer
                    $countdownPoints = [58, 30, 15, 10, 5, 3, 2, 1];
                    $lastShown = $remainingSeconds;
                    
                    for ($i = 0; $i < $remainingSeconds; $i++) {
                        sleep(1);
                        $currentRemaining = $remainingSeconds - $i - 1;
                        
                        // Show countdown at specific intervals
                        if (in_array($currentRemaining, $countdownPoints) && $currentRemaining < $lastShown) {
                            echo "⏰ {$currentRemaining} seconds remaining\n";
                            $lastShown = $currentRemaining;
                        }
                    }
                }
                
                echo "🔄 Circuit breaker timeout completed - attempting recovery request...\n";
                return true; // Allow retry after timeout
            }
            return false;
        }

        // Check if explicitly marked as retryable
        if ($detail->isRetryable()) {
            return true;
        }

        // Check if error code is in retryable list
        if ($this->config->shouldRetry($detail->getCode())) {
            return true;
        }

        // Check if HTTP status is retryable
        $httpStatusObj = $detail->getContextValue("httpStatus");
        if ($httpStatusObj !== null) {
            try {
                $statusCode = is_int($httpStatusObj) ? $httpStatusObj : intval($httpStatusObj);
                return $this->config->shouldRetryHttpCode($statusCode);
            } catch (\Exception $ex) {
                // Ignore invalid status
            }
        }

        return false;
    }

    /**
     * Calculate delay for next retry with exponential backoff and jitter
     */
    private function calculateDelay($attempt)
    {
        // Start with base delay and apply exponential backoff
        $delayMs = $this->config->getBaseDelay() * pow($this->config->getBackoffMultiplier(), $attempt - 1);

        // Apply jitter to avoid thundering herd
        if ($this->config->getJitterFactor() > 0) {
            $jitter = (mt_rand() / mt_getrandmax() * 2 - 1) * $this->config->getJitterFactor(); // -jitterFactor to +jitterFactor
            $delayMs = $delayMs * (1 + $jitter);
        }

        // Cap at max delay
        $delayMs = min($delayMs, $this->config->getMaxDelay());

        // Ensure minimum delay
        $delayMs = max($delayMs, 0);

        return intval($delayMs);
    }

    /**
     * Get the current circuit breaker state (for monitoring)
     */
    public function getCircuitBreakerState()
    {
        return $this->circuitBreaker !== null ? $this->circuitBreaker->getState() : null;
    }

    /**
     * Get circuit breaker statistics (for monitoring)
     */
    public function getCircuitBreakerStats()
    {
        if ($this->circuitBreaker !== null) {
            return sprintf("CircuitBreaker{state=%s, failures=%d, lastFailure=%d}",
                    $this->circuitBreaker->getState(), $this->circuitBreaker->getFailureCount(), 
                    $this->circuitBreaker->getLastFailureTime());
        }
        return "Circuit breaker disabled";
    }

    /**
     * Reset the circuit breaker (for testing/administrative purposes)
     */
    public function resetCircuitBreaker()
    {
        // Note: The new CircuitBreaker implementation doesn't have a reset method
        // as it automatically transitions based on success/failure patterns
    }
}
