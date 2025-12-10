<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Enums\ErrorCode;
use ComplyanceSDK\Models\ErrorDetail;

/**
 * Circuit breaker implementation
 */
class CircuitBreaker
{
    private $config;
    private $state = CircuitState::CLOSED;
    private $failureCount = 0;
    private $lastFailureTime = 0;
    private $queueManager;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function execute($operation)
    {
        if ($this->state === CircuitState::OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->state = CircuitState::HALF_OPEN;
            } else {
                $currentTime = time() * 1000;
                $timeSinceLastFailure = $currentTime - $this->lastFailureTime;
                $timeoutMs = $this->config->getTimeout() * 1000;
                $remainingTime = $timeoutMs - $timeSinceLastFailure;
                $remainingSeconds = max(0, intval($remainingTime / 1000));
                
                // Show countdown at specific intervals
                $countdownPoints = [58, 30, 15, 10, 5, 3, 2, 1];
                if (in_array($remainingSeconds, $countdownPoints)) {
                    echo "⏳ Circuit breaker timeout: {$remainingSeconds} seconds remaining...\n";
                }
                
                // Create error detail for circuit breaker open state
                $error = new ErrorDetail(ErrorCode::CIRCUIT_BREAKER_OPEN, 
                    "Circuit breaker is open - {$remainingSeconds} seconds remaining", 
                    "Too many failures, retry after timeout", null, null, null, [], [], true, $remainingSeconds);
                
                // Circuit breaker is open - just throw the exception
                // The higher-level code will handle enqueuing the request
                
                throw new SDKException($error);
            }
        }

        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure();
            
            // Circuit breaker just records the failure
            // The higher-level code will handle enqueuing the request
            
            throw $e;
        }
    }
    
    public function setQueueManager($queueManager)
    {
        $this->queueManager = $queueManager;
    }

    private function onSuccess()
    {
        if ($this->state === CircuitState::HALF_OPEN) {
            $this->state = CircuitState::CLOSED;
            $this->failureCount = 0;
        }
    }

    private function onFailure()
    {
        $this->failureCount++;
        $this->lastFailureTime = time() * 1000; // Convert to milliseconds

        if ($this->failureCount >= $this->config->getFailureThreshold()) {
            $this->state = CircuitState::OPEN;
        }
    }

    private function shouldAttemptReset()
    {
        $currentTime = time() * 1000; // Convert to milliseconds
        $timeSinceLastFailure = $currentTime - $this->lastFailureTime;
        $timeoutMs = $this->config->getTimeout() * 1000; // Convert seconds to milliseconds
        
        return $timeSinceLastFailure >= $timeoutMs;
    }

    // Circuit breaker state query methods
    public function getState() { return $this->state; }
    public function getFailureCount() { return $this->failureCount; }
    public function getLastFailureTime() { return $this->lastFailureTime; }
    public function isOpen() { return $this->state === CircuitState::OPEN; }
    public function isClosed() { return $this->state === CircuitState::CLOSED; }
    public function isHalfOpen() { return $this->state === CircuitState::HALF_OPEN; }
}

class CircuitState
{
    const CLOSED = "CLOSED";
    const OPEN = "OPEN";
    const HALF_OPEN = "HALF_OPEN";
}
