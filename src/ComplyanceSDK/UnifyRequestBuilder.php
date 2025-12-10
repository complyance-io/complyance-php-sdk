<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Purpose;

/**
 * Builder class for UnifyRequest
 * 
 * @package ComplyanceSDK
 */
class UnifyRequestBuilder
{
    private $request;

    public function __construct()
    {
        $this->request = new UnifyRequest();
    }

    public static function builder(): self
    {
        return new self();
    }

    public function source($source): self
    {
        // Convert Source object to array if needed
        if (is_object($source) && method_exists($source, 'toArray')) {
            $this->request->source = $source->toArray();
        } else {
            $this->request->source = $source;
        }
        return $this;
    }

    public function documentType(DocumentType $documentType): self
    {
        $this->request->documentType = $documentType;
        return $this;
    }

    public function documentTypeString(string $documentTypeString): self
    {
        $this->request->documentTypeString = $documentTypeString;
        return $this;
    }

    public function country(string $country): self
    {
        $this->request->country = $country;
        return $this;
    }

    public function operation(Operation $operation): self
    {
        $this->request->operation = $operation->getCode();
        return $this;
    }

    public function mode(Mode $mode): self
    {
        $this->request->mode = $mode->getCode();
        return $this;
    }

    public function purpose(Purpose $purpose): self
    {
        $this->request->purpose = strtolower($purpose->getCode());
        return $this;
    }

    public function payload(array $payload): self
    {
        $this->request->payload = $payload;
        return $this;
    }

    public function destinations(array $destinations): self
    {
        $this->request->destinations = $destinations;
        return $this;
    }

    public function apiKey(string $apiKey): self
    {
        $this->request->apiKey = $apiKey;
        return $this;
    }

    public function requestId(string $requestId): self
    {
        $this->request->requestId = $requestId;
        return $this;
    }

    public function timestamp(string $timestamp): self
    {
        $this->request->timestamp = $timestamp;
        return $this;
    }

    public function env(string $env): self
    {
        $this->request->env = $env;
        return $this;
    }

    public function correlationId(?string $correlationId): self
    {
        $this->request->correlationId = $correlationId;
        return $this;
    }

    public function build(): UnifyRequest
    {
        return $this->request;
    }
}
