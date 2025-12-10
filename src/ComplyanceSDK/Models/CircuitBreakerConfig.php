<?php

namespace ComplyanceSDK\Models;

/**
 * Circuit breaker configuration
 */
class CircuitBreakerConfig
{
    private $failureThreshold;
    private $timeout;

    public function __construct($failureThreshold, $timeout)
    {
        $this->failureThreshold = $failureThreshold;
        $this->timeout = $timeout;
    }

    public function getFailureThreshold()
    {
        return $this->failureThreshold;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public static function defaultConfig()
    {
        return new self(3, 60); // 3 failures, 60 seconds timeout (matching Java SDK)
    }

    public static function builder()
    {
        return new CircuitBreakerConfigBuilder();
    }
}

class CircuitBreakerConfigBuilder
{
    private $failureThreshold = 3;
    private $timeout = 60;

    public function failureThreshold($failureThreshold)
    {
        $this->failureThreshold = $failureThreshold;
        return $this;
    }

    public function timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function build()
    {
        return new CircuitBreakerConfig($this->failureThreshold, $this->timeout);
    }
}
