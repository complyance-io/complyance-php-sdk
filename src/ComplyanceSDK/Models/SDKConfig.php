<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\Environment;

/**
 * SDK Configuration model
 * 
 * @package ComplyanceSDK\Models
 */
class SDKConfig
{
    private $apiKey;
    private Environment $environment;
    private $sources;
    private $retryConfig;
    private $autoGenerateTaxDestination;
    private $correlationId;
    private $debug;

    /**
     * Constructor
     * 
     * @param string $apiKey API key
     * @param Environment $environment Environment
     * @param array $sources Array of sources
     * @param RetryConfig|null $retryConfig Retry configuration
     * @param bool $autoGenerateTaxDestination Auto-generate tax destination
     * @param string|null $correlationId Correlation ID
     * @param bool $debug Include v3 API debug information in responses
     */
    public function __construct(
        $apiKey,
        $environment,
        $sources = [],
        $retryConfig = null,
        $autoGenerateTaxDestination = true,
        $correlationId = null,
        $debug = false
    ) {
        $this->apiKey = $apiKey;
        $this->environment = $environment;
        $this->sources = $sources;
        $this->retryConfig = $retryConfig ?? RetryConfig::defaultConfig();
        $this->autoGenerateTaxDestination = $autoGenerateTaxDestination;
        $this->correlationId = $correlationId;
        $this->debug = (bool)$debug;
    }

    /**
     * Get API key
     * 
     * @return string API key
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Get environment
     * 
     * @return Environment Environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Get sources
     * 
     * @return array Array of sources
     */
    public function getSources()
    {
        return $this->sources;
    }

    /**
     * Get retry configuration
     * 
     * @return RetryConfig Retry configuration
     */
    public function getRetryConfig()
    {
        return $this->retryConfig;
    }
    
    /**
     * Check if auto-generate tax destination is enabled
     * 
     * @return bool True if enabled
     */
    public function isAutoGenerateTaxDestination()
    {
        return $this->autoGenerateTaxDestination;
    }
    
    /**
     * Get correlation ID
     * 
     * @return string|null Correlation ID
     */
    public function getCorrelationId()
    {
        return $this->correlationId;
    }

    /**
     * Check whether v3 API debug output is enabled.
     *
     * @return bool
     */
    public function isDebug()
    {
        return $this->debug;
    }

    /**
     * Set API key
     * 
     * @param string $apiKey API key
     * @return self
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Set environment
     * 
     * @param string $environment Environment
     * @return self
     */
    public function setEnvironment($environment)
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * Set sources
     * 
     * @param array $sources Array of sources
     * @return self
     */
    public function setSources($sources)
    {
        $this->sources = $sources;
        return $this;
    }

    /**
     * Set retry configuration
     * 
     * @param RetryConfig $retryConfig Retry configuration
     * @return self
     */
    public function setRetryConfig($retryConfig)
    {
        $this->retryConfig = $retryConfig;
        return $this;
    }

    /**
     * Set auto-generate tax destination
     * 
     * @param bool $autoGenerateTaxDestination Auto-generate tax destination
     * @return self
     */
    public function setAutoGenerateTaxDestination($autoGenerateTaxDestination)
    {
        $this->autoGenerateTaxDestination = $autoGenerateTaxDestination;
        return $this;
    }

    /**
     * Set correlation ID
     * 
     * @param string|null $correlationId Correlation ID
     * @return self
     */
    public function setCorrelationId($correlationId)
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    /**
     * Enable or disable v3 API debug output.
     *
     * @param bool $debug
     * @return self
     */
    public function setDebug($debug)
    {
        $this->debug = (bool)$debug;
        return $this;
    }

    /**
     * Convert to array
     * 
     * @return array Array representation
     */
    public function toArray()
    {
        return [
            'apiKey' => $this->apiKey,
            'environment' => $this->environment,
            'sources' => array_map(function($source) { return $source->toArray(); }, $this->sources),
            'retryConfig' => $this->retryConfig->toArray(),
            'autoGenerateTaxDestination' => $this->autoGenerateTaxDestination,
            'correlationId' => $this->correlationId,
            'debug' => $this->debug
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
        $sources = [];
        if (isset($data['sources']) && is_array($data['sources'])) {
            $sources = array_map(function($sourceData) { return Source::fromArray($sourceData); }, $data['sources']);
        }

        $retryConfig = null;
        if (isset($data['retryConfig']) && is_array($data['retryConfig'])) {
            $retryConfig = RetryConfig::fromArray($data['retryConfig']);
        }

        return new self(
            $data['apiKey'] ?? '',
            $data['environment'] ?? Environment::DEV,
            $sources,
            $retryConfig,
            $data['autoGenerateTaxDestination'] ?? true,
            $data['correlationId'] ?? null,
            $data['debug'] ?? false
        );
    }

    /**
     * Builder pattern
     * 
     * @return SDKConfigBuilder Builder instance
     */
    public static function builder()
    {
        return new SDKConfigBuilder();
    }
}

/**
 * SDK Configuration Builder
 * 
 * @package ComplyanceSDK\Models
 */
class SDKConfigBuilder
{
    private $apiKey = '';
    private $environment = Environment::DEV;
    private $sources = [];
    private $retryConfig = null;
    private $autoGenerateTaxDestination = true;
    private $correlationId = null;
    private $debug = false;
    
    /**
     * Set API key
     * 
     * @param string $apiKey API key
     * @return self
     */
    public function apiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Set environment
     * 
     * @param string $environment Environment
     * @return self
     */
    public function environment($environment)
    {
        $this->environment = $environment;
        return $this;
    }
    
    /**
     * Set sources
     * 
     * @param array $sources Array of sources
     * @return self
     */
    public function sources($sources)
    {
        $this->sources = $sources !== null ? $sources : [];
        return $this;
    }
    
    /**
     * Set retry configuration
     * 
     * @param RetryConfig $retryConfig Retry configuration
     * @return self
     */
    public function retryConfig($retryConfig)
    {
        $this->retryConfig = $retryConfig;
        return $this;
    }
    
    /**
     * Set auto-generate tax destination
     * 
     * @param bool $autoGenerateTaxDestination Auto-generate tax destination
     * @return self
     */
    public function autoGenerateTaxDestination($autoGenerateTaxDestination)
    {
        $this->autoGenerateTaxDestination = $autoGenerateTaxDestination;
        return $this;
    }
    
    /**
     * Set correlation ID
     * 
     * @param string|null $correlationId Correlation ID
     * @return self
     */
    public function correlationId($correlationId)
    {
        $this->correlationId = $correlationId;
        return $this;
    }

    /**
     * Enable or disable v3 API debug output.
     *
     * @param bool $debug
     * @return self
     */
    public function debug($debug)
    {
        $this->debug = (bool)$debug;
        return $this;
    }
    
    /**
     * Build SDK configuration
     * 
     * @return SDKConfig SDK configuration
     */
    public function build()
    {
        return new SDKConfig(
            $this->apiKey,
            $this->environment,
            $this->sources,
            $this->retryConfig,
            $this->autoGenerateTaxDestination,
            $this->correlationId,
            $this->debug
        );
    }
}