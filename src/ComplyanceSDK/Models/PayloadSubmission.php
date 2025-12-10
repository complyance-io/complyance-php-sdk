<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\DocumentType;

/**
 * Represents a payload submission for the persistent queue
 */
class PayloadSubmission
{
    private $payload;
    private $source;
    private $country;
    private $documentType;
    private $enqueuedAt;
    private $timestamp;

    public function __construct(string $payload, Source $source, Country $country, $documentType)
    {
        $this->payload = $payload;
        $this->source = $source;
        $this->country = $country;
        // Convert DocumentType enum to string if needed
        if (is_object($documentType) && method_exists($documentType, 'getCode')) {
            $this->documentType = $documentType->getCode();
        } else {
            $this->documentType = (string)$documentType;
        }
        $this->enqueuedAt = time();
        $this->timestamp = date('c');
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getCountry(): Country
    {
        return $this->country;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getEnqueuedAt(): int
    {
        return $this->enqueuedAt;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'payload' => $this->payload,
            'source' => [
                'name' => $this->source->getName(),
                'version' => $this->source->getVersion(),
                'type' => $this->source->getType()->value
            ],
            'country' => $this->country->value,
            'documentType' => $this->documentType,
            'enqueuedAt' => $this->enqueuedAt,
            'timestamp' => $this->timestamp
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
