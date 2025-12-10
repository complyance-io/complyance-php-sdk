<?php

namespace Complyance\SDK;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Advanced retry strategy with exponential backoff, jitter, and circuit breaker
 */
class RetryStrategy {
    use LoggerAwareTrait;

    private $config;
    private $circuitBreaker;

    /**
     * @param array $config Retry configuration
     *                     - maxAttempts: int Maximum number of retry attempts
     *                     - baseDelay: int Base delay in milliseconds
     *                     - maxDelay: int Maximum delay in milliseconds
     *                     - backoffMultiplier: float Multiplier for exponential backoff
     *                     - jitterFactor: float Random jitter factor (0-1)
     *                     - retryableErrors: array List of error codes to retry
     *                     - retryableHttpCodes: array List of HTTP status codes to retry
     *                     - circuitBreakerEnabled: bool Whether to use circuit breaker
     *                     - circuitBreakerConfig: array Circuit breaker configuration
     */
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'maxAttempts' => 3,
            'baseDelay' => 1000,
            'maxDelay' => 30000,
            'backoffMultiplier' => 2.0,
            'jitterFactor' => 0.2,
            'retryableErrors' => ['RATE_LIMIT_EXCEEDED', 'SERVICE_UNAVAILABLE'],
            'retryableHttpCodes' => [408, 429, 500, 502, 503, 504],
            'circuitBreakerEnabled' => true,
            'circuitBreakerConfig' => [
                'failureThreshold' => 3,
                'resetTimeout' => 60
            ]
        ], $config);

        $this->logger = new NullLogger();

        if ($this->config['circuitBreakerEnabled']) {
            $this->circuitBreaker = new CircuitBreaker($this->config['circuitBreakerConfig']);
            $this->circuitBreaker->setLogger($this->logger);
        }
    }

    /**
     * Execute a function with retry logic
     * 
     * @param callable $operation Function to execute
     * @param string $operationName Name of operation for logging
     * @return mixed Result of operation
     * @throws SDKException If all retries fail
     */
    public function execute(callable $operation, string $operationName) {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->config['maxAttempts']) {
            try {
                $this->logger->debug("Attempting operation '{$operationName}' (attempt {$attempt}/{$this->config['maxAttempts']})");

                // Use circuit breaker if enabled
                if ($this->circuitBreaker) {
                    try {
                        $result = $this->circuitBreaker->execute($operation);
                    } catch (\RuntimeException $e) {
                        if (strpos($e->getMessage(), 'Circuit breaker is open') !== false) {
                            throw new SDKException(new ErrorDetail(
                                'CIRCUIT_BREAKER_OPEN',
                                $e->getMessage()
                            ));
                        }
                        throw $e;
                    }
                } else {
                    $result = $operation();
                }

                if ($attempt > 1) {
                    $this->logger->info("Operation '{$operationName}' succeeded on attempt {$attempt}");
                }

                return $result;

            } catch (SDKException $e) {
                $lastException = $e;

                // Check if this error should be retried
                if (!$this->shouldRetry($e, $attempt)) {
                    $this->logger->debug("Operation '{$operationName}' failed with non-retryable error: {$e->getMessage()}");
                    throw $e;
                }

                // If this was the last attempt, don't wait
                if ($attempt >= $this->config['maxAttempts']) {
                    break;
                }

                // Calculate delay and wait
                $delay = $this->calculateDelay($attempt);
                $this->logger->warning("Operation '{$operationName}' failed on attempt {$attempt} ({$e->getMessage()}), retrying in {$delay}ms");

                usleep($delay * 1000); // Convert ms to microseconds

            } catch (\Exception $e) {
                $this->logger->error("Unexpected error in operation '{$operationName}': {$e->getMessage()}");

                $error = new ErrorDetail(
                    'PROCESSING_ERROR',
                    "Unexpected error: {$e->getMessage()}"
                );
                $error->setSuggestion("This appears to be an unexpected error. Please contact support if it persists");
                $error->addContextValue('originalException', get_class($e));
                throw new SDKException($error);
            }

            $attempt++;
        }

        // All retries exhausted
        $this->logger->error("Operation '{$operationName}' failed after {$this->config['maxAttempts']} attempts");
        throw $lastException ?? new SDKException(new ErrorDetail(
            'MAX_RETRIES_EXCEEDED',
            "Maximum retry attempts ({$this->config['maxAttempts']}) exceeded"
        ));
    }

    /**
     * Determine if an error should be retried
     * 
     * @param SDKException $e Exception to check
     * @param int $attempt Current attempt number
     * @return bool True if error should be retried
     */
    private function shouldRetry(SDKException $e, int $attempt): bool {
        if ($attempt >= $this->config['maxAttempts']) {
            return false;
        }

        $detail = $e->getErrorDetail();
        if (!$detail) {
            return false;
        }

        // If circuit breaker is open, check if we should wait for timeout
        if ($detail->getCode() === 'CIRCUIT_BREAKER_OPEN') {
            if (preg_match('/(\d+) seconds remaining/', $e->getMessage(), $matches)) {
                $remainingSeconds = (int) $matches[1];
                $delay = $remainingSeconds * 1000;
                $this->logger->info("Circuit breaker is open - waiting for {$remainingSeconds} seconds before retrying");
                usleep($delay * 1000); // Convert ms to microseconds
                return true;
            }
            return false;
        }

        // Check if explicitly marked as retryable
        if ($detail->isRetryable()) {
            return true;
        }

        // Check if error code is in retryable list
        if (in_array($detail->getCode(), $this->config['retryableErrors'])) {
            return true;
        }

        // Check if HTTP status is retryable
        $httpStatus = $detail->getContextValue('httpStatus');
        if ($httpStatus !== null) {
            $statusCode = is_numeric($httpStatus) ? (int) $httpStatus : null;
            if ($statusCode && in_array($statusCode, $this->config['retryableHttpCodes'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate delay for next retry with exponential backoff and jitter
     * 
     * @param int $attempt Current attempt number
     * @return int Delay in milliseconds
     */
    private function calculateDelay(int $attempt): int {
        // Start with base delay and apply exponential backoff
        $delayMs = $this->config['baseDelay'] * pow($this->config['backoffMultiplier'], $attempt - 1);

        // Apply jitter to avoid thundering herd
        if ($this->config['jitterFactor'] > 0) {
            $jitter = (mt_rand() / mt_getrandmax() * 2 - 1) * $this->config['jitterFactor'];
            $delayMs = $delayMs * (1 + $jitter);
        }

        // Cap at max delay
        $delayMs = min($delayMs, $this->config['maxDelay']);

        // Ensure minimum delay
        $delayMs = max($delayMs, 0);

        return (int) $delayMs;
    }

    /**
     * Get the current circuit breaker state (for monitoring)
     * 
     * @return string|null Current circuit breaker state
     */
    public function getCircuitBreakerState(): ?string {
        return $this->circuitBreaker ? $this->circuitBreaker->getState() : null;
    }

    /**
     * Get circuit breaker statistics (for monitoring)
     * 
     * @return string Circuit breaker stats
     */
    public function getCircuitBreakerStats(): string {
        if ($this->circuitBreaker) {
            return sprintf(
                "CircuitBreaker{state=%s, failures=%d, lastFailure=%d}",
                $this->circuitBreaker->getState(),
                $this->circuitBreaker->getFailureCount(),
                $this->circuitBreaker->getLastFailureTime()
            );
        }
        return "Circuit breaker disabled";
    }
}
