<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\Environment;

/**
 * Environment configuration for custom base URLs
 */
class EnvironmentConfig
{
    private $baseUrlOverrides;
    private $defaultEnvironment;

    private function __construct($baseUrlOverrides, $defaultEnvironment)
    {
        $this->baseUrlOverrides = $baseUrlOverrides ?: [];
        $this->defaultEnvironment = $defaultEnvironment;
    }

    public function getBaseUrl($environment)
    {
        if (isset($this->baseUrlOverrides[$environment->getCode()])) {
            return $this->baseUrlOverrides[$environment->getCode()];
        }
        return $environment->getBaseUrl();
    }

    public function getDefaultEnvironment()
    {
        return $this->defaultEnvironment;
    }

    public static function builder()
    {
        return new EnvironmentConfigBuilder();
    }
}

class EnvironmentConfigBuilder
{
    private $baseUrlOverrides = [];
    private $defaultEnvironment;

    public function __construct()
    {
        $this->defaultEnvironment = Environment::from(Environment::DEV);
    }

    public function overrideBaseUrl($env, $url)
    {
        $this->baseUrlOverrides[$env->getCode()] = $url;
        return $this;
    }

    public function defaultEnvironment($env)
    {
        $this->defaultEnvironment = $env;
        return $this;
    }

    public function build()
    {
        return new EnvironmentConfig($this->baseUrlOverrides, $this->defaultEnvironment);
    }
}
