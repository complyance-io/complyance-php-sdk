<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Purpose;

/**
 * UnifyRequest class for building API requests
 * 
 * @package ComplyanceSDK
 */
class UnifyRequest
{
    public $source;
    public $documentType;
    public $documentTypeString;
    public $documentTypeV2;
    public $country;
    public $operation;
    public $mode;
    public $purpose;
    public $payload;
    public $destinations;
    public $apiKey;
    public $requestId;
    public $timestamp;
    public $env;
    public $correlationId;

    /**
     * Private constructor - use builder
     */
    public function __construct()
    {
    }

    /**
     * Create a new builder instance
     * 
     * @return UnifyRequestBuilder
     */
    public static function builder(): UnifyRequestBuilder
    {
        return new UnifyRequestBuilder();
    }

    // Getters
    public function getSource(): array
    {
        return $this->source;
    }

    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    public function getDocumentTypeString(): string
    {
        return $this->documentTypeString;
    }

    public function getDocumentTypeV2(): ?array
    {
        return $this->documentTypeV2;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getDestinations(): array
    {
        return $this->destinations;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getEnv(): string
    {
        return $this->env;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    // Setter methods for queue processing
    public function setSource($source): void
    {
        $this->source = $source;
    }

    public function setDocumentType($documentType): void
    {
        $this->documentType = $documentType;
    }

    public function setDocumentTypeString(string $documentTypeString): void
    {
        $this->documentTypeString = $documentTypeString;
    }

    public function setDocumentTypeV2(?array $documentTypeV2): void
    {
        $this->documentTypeV2 = $documentTypeV2;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function setOperation($operation): void
    {
        $this->operation = $operation;
    }

    public function setMode($mode): void
    {
        $this->mode = $mode;
    }

    public function setPurpose($purpose): void
    {
        $this->purpose = $purpose;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function setDestinations(array $destinations): void
    {
        $this->destinations = $destinations;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function setTimestamp(string $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function setEnv(string $env): void
    {
        $this->env = $env;
    }

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    /**
     * Convert to array for JSON serialization
     * Matches Java SDK structure with @JsonProperty annotations
     * 
     * @return array
     */
    public function toArray(): array
    {
        $documentType = $this->documentTypeString;
        if (is_array($this->documentTypeV2) && !empty($this->documentTypeV2)) {
            $documentType = $this->documentTypeV2;
        }

        return [
            'source' => $this->source,
            'documentType' => $documentType, // Matches Java V1/V2 @JsonProperty("documentType")
            'country' => $this->country,
            'operation' => $this->operation,
            'mode' => $this->mode,
            'purpose' => $this->purpose,
            'payload' => $this->payload,
            'apiKey' => $this->apiKey,
            'requestId' => $this->requestId,
            'timestamp' => $this->timestamp,
            'env' => $this->env,
            'destinations' => $this->destinations,
            'correlationId' => $this->correlationId
        ];
    }

}
