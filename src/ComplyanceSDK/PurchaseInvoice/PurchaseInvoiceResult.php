<?php

namespace ComplyanceSDK\PurchaseInvoice;

/**
 * Typed hybrid purchase invoice retrieval response.
 */
class PurchaseInvoiceResult
{
    public string $documentId;
    public string $clientId;
    public string $country;
    public string $environment;
    public string $documentNumber;
    public ?string $state;
    public ?bool $isTerminal;
    public ?string $lastUpdatedAt;

    /** @var array<string, mixed> */
    public array $invoice;

    /** @var mixed */
    public $xml;

    /** @var mixed */
    public $xmlResponse;

    public ?PurchaseInvoiceArtifacts $artifacts;
    public ?PurchaseInvoiceGovernmentStatus $government;
    public ?PurchaseInvoiceCompliance $compliance;

    /** @var PurchaseInvoiceValidationErrorEntry[] */
    public array $errors;

    public ?PurchaseInvoiceValidationResults $validationResults;

    /**
     * @param array<string, mixed> $invoice
     * @param mixed $xml
     * @param mixed $xmlResponse
     * @param PurchaseInvoiceValidationErrorEntry[] $errors
     */
    public function __construct(
        string $documentId,
        string $clientId,
        string $country,
        string $environment,
        string $documentNumber,
        ?string $state,
        ?bool $isTerminal,
        ?string $lastUpdatedAt,
        array $invoice,
        $xml = null,
        $xmlResponse = null,
        ?PurchaseInvoiceArtifacts $artifacts = null,
        ?PurchaseInvoiceGovernmentStatus $government = null,
        ?PurchaseInvoiceCompliance $compliance = null,
        array $errors = [],
        ?PurchaseInvoiceValidationResults $validationResults = null
    ) {
        $this->documentId = $documentId;
        $this->clientId = $clientId;
        $this->country = $country;
        $this->environment = $environment;
        $this->documentNumber = $documentNumber;
        $this->state = $state;
        $this->isTerminal = $isTerminal;
        $this->lastUpdatedAt = $lastUpdatedAt;
        $this->invoice = $invoice;
        $this->xml = $xml;
        $this->xmlResponse = $xmlResponse;
        $this->artifacts = $artifacts;
        $this->government = $government;
        $this->compliance = $compliance;
        $this->errors = $errors;
        $this->validationResults = $validationResults;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach (($data['errors'] ?? []) as $error) {
            if (is_array($error)) {
                $errors[] = PurchaseInvoiceValidationErrorEntry::fromArray($error);
            }
        }

        return new self(
            (string) ($data['documentId'] ?? ''),
            (string) ($data['clientId'] ?? ''),
            (string) ($data['country'] ?? ''),
            (string) ($data['environment'] ?? ''),
            (string) ($data['documentNumber'] ?? ''),
            isset($data['state']) ? (string) $data['state'] : null,
            isset($data['isTerminal']) ? (bool) $data['isTerminal'] : null,
            isset($data['lastUpdatedAt']) ? (string) $data['lastUpdatedAt'] : null,
            is_array($data['invoice'] ?? null) ? $data['invoice'] : [],
            $data['xml'] ?? null,
            $data['xmlResponse'] ?? null,
            is_array($data['artifacts'] ?? null) ? PurchaseInvoiceArtifacts::fromArray($data['artifacts']) : null,
            is_array($data['government'] ?? null) ? PurchaseInvoiceGovernmentStatus::fromArray($data['government']) : null,
            is_array($data['compliance'] ?? null) ? PurchaseInvoiceCompliance::fromArray($data['compliance']) : null,
            $errors,
            is_array($data['validationResults'] ?? null)
                ? PurchaseInvoiceValidationResults::fromArray($data['validationResults'])
                : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'documentId' => $this->documentId,
            'clientId' => $this->clientId,
            'country' => $this->country,
            'environment' => $this->environment,
            'documentNumber' => $this->documentNumber,
            'state' => $this->state,
            'isTerminal' => $this->isTerminal,
            'lastUpdatedAt' => $this->lastUpdatedAt,
            'invoice' => $this->invoice,
            'xml' => $this->xml,
            'xmlResponse' => $this->xmlResponse,
            'artifacts' => $this->artifacts ? $this->artifacts->toArray() : null,
            'government' => $this->government ? $this->government->toArray() : null,
            'compliance' => $this->compliance ? $this->compliance->toArray() : null,
            'errors' => array_map(static function (PurchaseInvoiceValidationErrorEntry $error): array {
                return $error->toArray();
            }, $this->errors),
            'validationResults' => $this->validationResults ? $this->validationResults->toArray() : null,
        ];
    }
}

class PurchaseInvoiceArtifacts
{
    public ?string $invoiceXmlBase64;
    public ?string $invoiceXmlEncoding;
    public ?string $tddXmlBase64;

    public function __construct(?string $invoiceXmlBase64, ?string $invoiceXmlEncoding, ?string $tddXmlBase64)
    {
        $this->invoiceXmlBase64 = $invoiceXmlBase64;
        $this->invoiceXmlEncoding = $invoiceXmlEncoding;
        $this->tddXmlBase64 = $tddXmlBase64;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['invoiceXmlBase64']) ? (string) $data['invoiceXmlBase64'] : null,
            isset($data['invoiceXmlEncoding']) ? (string) $data['invoiceXmlEncoding'] : null,
            isset($data['tddXmlBase64']) ? (string) $data['tddXmlBase64'] : null
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'invoiceXmlBase64' => $this->invoiceXmlBase64,
            'invoiceXmlEncoding' => $this->invoiceXmlEncoding,
            'tddXmlBase64' => $this->tddXmlBase64,
        ];
    }
}

class PurchaseInvoiceGovernmentStatus
{
    public string $documentId;
    public string $country;
    public string $environment;
    public bool $success;
    public ?string $errorCode;
    public ?string $errorMessage;
    public string $timestamp;
    public string $status;

    public function __construct(
        string $documentId,
        string $country,
        string $environment,
        bool $success,
        ?string $errorCode,
        ?string $errorMessage,
        string $timestamp,
        string $status
    ) {
        $this->documentId = $documentId;
        $this->country = $country;
        $this->environment = $environment;
        $this->success = $success;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->timestamp = $timestamp;
        $this->status = $status;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['documentId'] ?? ''),
            (string) ($data['country'] ?? ''),
            (string) ($data['environment'] ?? ''),
            (bool) ($data['success'] ?? false),
            isset($data['errorCode']) ? (string) $data['errorCode'] : null,
            isset($data['errorMessage']) ? (string) $data['errorMessage'] : null,
            (string) ($data['timestamp'] ?? ''),
            (string) ($data['status'] ?? '')
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'documentId' => $this->documentId,
            'country' => $this->country,
            'environment' => $this->environment,
            'success' => $this->success,
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'timestamp' => $this->timestamp,
            'status' => $this->status,
        ];
    }
}

class PurchaseInvoiceCompliance
{
    public ?string $uuid;
    public string $ftaApprovedStatus;
    public ?string $businessProcessIdentifier;
    public ?string $specificationIdentifier;

    public function __construct(
        ?string $uuid,
        string $ftaApprovedStatus,
        ?string $businessProcessIdentifier,
        ?string $specificationIdentifier
    ) {
        $this->uuid = $uuid;
        $this->ftaApprovedStatus = $ftaApprovedStatus;
        $this->businessProcessIdentifier = $businessProcessIdentifier;
        $this->specificationIdentifier = $specificationIdentifier;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['uuid']) ? (string) $data['uuid'] : null,
            (string) ($data['ftaApprovedStatus'] ?? ''),
            isset($data['businessProcessIdentifier']) ? (string) $data['businessProcessIdentifier'] : null,
            isset($data['specificationIdentifier']) ? (string) $data['specificationIdentifier'] : null
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'ftaApprovedStatus' => $this->ftaApprovedStatus,
            'businessProcessIdentifier' => $this->businessProcessIdentifier,
            'specificationIdentifier' => $this->specificationIdentifier,
        ];
    }
}

class PurchaseInvoiceValidationErrorEntry
{
    public string $code;
    public string $message;

    /** @var array<int, mixed> */
    public array $path;

    public ?string $source;

    /** @param array<int, mixed> $path */
    public function __construct(string $code, string $message, array $path, ?string $source)
    {
        $this->code = $code;
        $this->message = $message;
        $this->path = $path;
        $this->source = $source;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['code'] ?? ''),
            (string) ($data['message'] ?? ''),
            is_array($data['path'] ?? null) ? array_values($data['path']) : [],
            isset($data['source']) ? (string) $data['source'] : null
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'path' => $this->path,
            'source' => $this->source,
        ];
    }
}

class PurchaseInvoiceValidationStep
{
    public string $name;
    public string $status;
    public ?string $error;

    public function __construct(string $name, string $status, ?string $error)
    {
        $this->name = $name;
        $this->status = $status;
        $this->error = $error;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['name'] ?? ''),
            (string) ($data['status'] ?? ''),
            isset($data['error']) ? (string) $data['error'] : null
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}

class PurchaseInvoiceValidationResults
{
    public string $status;

    /** @var PurchaseInvoiceValidationStep[] */
    public array $validationSteps;

    /** @param PurchaseInvoiceValidationStep[] $validationSteps */
    public function __construct(string $status, array $validationSteps)
    {
        $this->status = $status;
        $this->validationSteps = $validationSteps;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $steps = [];
        foreach (($data['validationSteps'] ?? []) as $step) {
            if (is_array($step)) {
                $steps[] = PurchaseInvoiceValidationStep::fromArray($step);
            }
        }

        return new self(
            (string) ($data['status'] ?? ''),
            $steps
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'validationSteps' => array_map(static function (PurchaseInvoiceValidationStep $step): array {
                return $step->toArray();
            }, $this->validationSteps),
        ];
    }
}
