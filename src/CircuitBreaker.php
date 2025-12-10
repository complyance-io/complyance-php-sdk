<?php

namespace Complyance\SDK;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Circuit Breaker implementation for handling service failures
 */
class CircuitBreaker {
    use LoggerAwareTrait;

    private const STATE_CLOSED = 'CLOSED';
    private const STATE_OPEN = 'OPEN';
    private const STATE_HALF_OPEN = 'HALF_OPEN';

    private $state;
    private $failureCount;
    private $lastFailureTime;
    private $failureThreshold;
    private $resetTimeout;

    /**
     * @param array $config Circuit breaker configuration
     *                      - failureThreshold: int Number of failures before opening circuit
     *                      - resetTimeout: int Timeout in seconds before attempting reset
     */
    public function __construct(array $config = []) {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = 0;
        $this->failureThreshold = $config['failureThreshold'] ?? 3;
        $this->resetTimeout = $config['resetTimeout'] ?? 60;
        $this->logger = new NullLogger();
    }

    /**
     * Execute a function with circuit breaker protection
     * 
     * @param callable $operation Function to execute
     * @return mixed Result of operation
     * @throws \RuntimeException If circuit is open
     */
    public function execute(callable $operation) {
        $this->checkState();

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    /**
     * Check if circuit breaker should allow operation
     * 
     * @throws \RuntimeException If circuit is open
     */
    private function checkState(): void {
        if ($this->state === self::STATE_OPEN) {
            $remainingTime = $this->getRemainingTimeout();
            if ($remainingTime > 0) {
                $this->logger->debug("Circuit breaker is OPEN - {$remainingTime} seconds remaining");
                throw new \RuntimeException("Circuit breaker is open - {$remainingTime} seconds remaining");
            }

            // Reset timeout expired, move to half-open
            $this->state = self::STATE_HALF_OPEN;
            $this->logger->info("Circuit breaker state changed to HALF-OPEN");
        }
    }

    /**
     * Record a successful operation
     */
    private function recordSuccess(): void {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->state = self::STATE_CLOSED;
            $this->failureCount = 0;
            $this->lastFailureTime = 0;
            $this->logger->info("Circuit breaker reset - state changed to CLOSED");
        }
    }

    /**
     * Record a failed operation
     */
    private function recordFailure(): void {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->state === self::STATE_HALF_OPEN || 
            ($this->state === self::STATE_CLOSED && $this->failureCount >= $this->failureThreshold)) {
            $this->state = self::STATE_OPEN;
            $this->logger->warning("Circuit breaker opened after {$this->failureCount} failures");
        }
    }

    /**
     * Get remaining time before circuit breaker resets
     * 
     * @return int Seconds remaining, 0 if timeout expired
     */
    private function getRemainingTimeout(): int {
        if ($this->lastFailureTime === 0) {
            return 0;
        }

        $elapsed = time() - $this->lastFailureTime;
        $remaining = $this->resetTimeout - $elapsed;
        return max(0, $remaining);
    }

    /**
     * Check if circuit breaker is currently open
     * 
     * @return bool True if open
     */
    public function isOpen(): bool {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Get current circuit breaker state
     * 
     * @return string Current state (OPEN, CLOSED, HALF-OPEN)
     */
    public function getState(): string {
        return $this->state;
    }

    /**
     * Get current failure count
     * 
     * @return int Number of consecutive failures
     */
    public function getFailureCount(): int {
        return $this->failureCount;
    }

    /**
     * Get timestamp of last failure
     * 
     * @return int Unix timestamp of last failure, 0 if no failures
     */
    public function getLastFailureTime(): int {
        return $this->lastFailureTime;
    }
}
