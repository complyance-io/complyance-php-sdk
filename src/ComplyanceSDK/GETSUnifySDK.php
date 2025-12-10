<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Exceptions\ConfigurationException;
use ComplyanceSDK\Exceptions\ValidationException;
use ComplyanceSDK\Models\Source;
use ComplyanceSDK\Models\SDKConfig;
use ComplyanceSDK\Models\SourceRef;
use ComplyanceSDK\Models\Destination;
use ComplyanceSDK\Models\CountryPolicyRegistry;
use ComplyanceSDK\Models\PolicyResult;
use ComplyanceSDK\Models\PersistentQueueManager;
use ComplyanceSDK\Models\StatusManager;
use ComplyanceSDK\Models\CircuitBreaker;
use ComplyanceSDK\Models\CircuitBreakerConfig;
use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\LogicalDocType;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Purpose;
use ComplyanceSDK\Enums\Environment;
use ComplyanceSDK\Enums\SubmissionStatus;

/**
 * Main entry point for the Complyance GETS Unify PHP SDK.
 * 
 * This SDK provides easy-to-use APIs for document submission, payload processing,
 * and multi-country compliance management.
 * 
 * @package ComplyanceSDK
 * @version 3.0.0
 */
class GETSUnifySDK
{
    /**
     * @var SDKConfig|null SDK configuration
     */
    private static $config = null;

    /**
     * @var APIClient|null API client
     */
    private static $apiClient = null;

    /**
     * @var PersistentQueueManager|null Queue manager for failed submissions
     */
    private static $queueManager = null;

    /**
     * Configure the SDK with API key, environment, and sources.
     * 
     * @param SDKConfig $sdkConfig SDK configuration
     * @throws ConfigurationException
     */
    public static function configure(SDKConfig $sdkConfig)
    {
        self::$config = $sdkConfig;
        
        if ($sdkConfig === null) {
            throw new ConfigurationException('SDKConfig is required');
        }

        // Validate country restrictions for production environments
        self::validateEnvironmentCountryRestrictions($sdkConfig->getEnvironment());
        
        // Create a shared circuit breaker instance
        $sharedCircuitBreaker = new CircuitBreaker(new CircuitBreakerConfig(3, 60));

        // Initialize APIClient with shared circuit breaker
        self::$apiClient = new APIClient(
            $sdkConfig->getApiKey(),
            $sdkConfig->getEnvironment(),
            $sdkConfig->getRetryConfig(),
            $sharedCircuitBreaker
        );

        // Initialize PersistentQueueManager with shared circuit breaker
        self::$queueManager = new PersistentQueueManager(
            $sdkConfig->getApiKey(),
            $sdkConfig->getEnvironment()->getCode() === Environment::LOCAL,
            $sharedCircuitBreaker
        );
    }

    /**
     * Validate country restrictions based on environment
     * 
     * @param Environment $environment
     */
    private static function validateEnvironmentCountryRestrictions(Environment $environment)
    {
        $productionEnvironments = [
            Environment::SANDBOX,
            Environment::SIMULATION,
            Environment::PRODUCTION
        ];

        if (in_array($environment->getCode(), $productionEnvironments)) {
        } else {
        }
    }

    /**
     * Submit a payload to the GETS Unify API.
     * 
     * @param string $clientPayloadJson The raw JSON payload
     * @param string $sourceId The source ID
     * @param Country $country The country
     * @param DocumentType $documentType The document type
     * @return array Response data
     * @throws SDKException
     */
    public static function submitPayload(
        $clientPayloadJson,
        Country $country,
        DocumentType $documentType,
        $sourceId = null
    ) {
        self::validateConfiguration();

        if (empty(trim($clientPayloadJson))) {
            throw new ValidationException('Payload is required', 'Provide a valid JSON payload.');
        }

        if (empty(trim($sourceId))) {
            throw new ValidationException('Source ID is required', 'Provide a valid source ID.');
        }

        // Find source by ID
        $source = self::findSourceById($sourceId);
        if ($source === null) {
            throw new ValidationException('Source not found', 'Check the source ID or configure the source.');
        }

        // Validate country restrictions for current environment
        self::validateCountryForEnvironment($country, self::$config->getEnvironment());

        // For now, return a mock response - in real implementation, this would call the API
        return [
            'status' => 'success',
            'message' => 'Payload submitted successfully',
            'data' => [
                'submissionId' => 'sub_' . time() . '_' . mt_rand(),
                'source' => $source->toArray(),
                'country' => $country->getCode(),
                'documentType' => $documentType->getCode()
            ]
        ];
    }

    /**
     * Push to Unify API with logical document types and full control.
     * 
     * @param string $sourceName Source name
     * @param string $sourceVersion Source version
     * @param LogicalDocType $logicalType Logical document type
     * @param Country $country Country
     * @param Operation $operation Operation
     * @param Mode $mode Mode
     * @param Purpose $purpose Purpose
     * @param array $payload Payload data
     * @param array|null $destinations Optional destinations
     * @return array Response data
     * @throws SDKException
     */
    public static function pushToUnify(
        $sourceName,
        $sourceVersion,
        LogicalDocType $logicalType,
        Country $country,
        Operation $operation,
        Mode $mode,
        Purpose $purpose,
        array $payload,
        $destinations = null
    ) {
        // Log SDK operation start
        error_log("SDK Operation - pushToUnify: " . $sourceName . ":" . $sourceVersion . 
                  ", DocType: " . $logicalType->getCode() . ", Country: " . $country->getCode());
                   
        if (self::$config === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "SDK not configured",
                "Call GETSUnifySDK.configure() first."
            ));
        }

        // Process queued submissions first before handling new requests
        self::processQueuedSubmissionsFirst();

        // Validate required parameters
        // Handle sourceName and sourceVersion based on purpose
        $finalSourceName = "";
        $finalSourceVersion = "";
        
        if ($purpose->getCode() === Purpose::MAPPING) {
            // For MAPPING purpose, sourceName and sourceVersion are optional - set to empty string if null
            $finalSourceName = ($sourceName === null) ? "" : $sourceName;
            $finalSourceVersion = ($sourceVersion === null) ? "" : $sourceVersion;
        } else {
            // For all other purposes, sourceName and sourceVersion are mandatory
            if ($sourceName === null || trim($sourceName) === "") {
                throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                    \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                    "Source name is required",
                    "Provide a valid source name."
                ));
            }
            if ($sourceVersion === null || trim($sourceVersion) === "") {
                throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                    \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                    "Source version is required",
                    "Provide a valid source version."
                ));
            }
            $finalSourceName = $sourceName;
            $finalSourceVersion = $sourceVersion;
        }
        if ($logicalType === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Logical document type is required",
                "Provide a valid logical document type."
            ));
        }
        if ($country === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Country is required",
                "Provide a valid country."
            ));
        }
        if ($operation === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Operation is required",
                "Provide a valid operation."
            ));
        }
        if ($mode === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Mode is required",
                "Provide a valid mode."
            ));
        }
        if ($purpose === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Purpose is required",
                "Provide a valid purpose."
            ));
        }
        if ($payload === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Payload is required",
                "Provide a valid payload."
            ));
        }

        // Validate country restrictions for current environment
        self::validateCountryForEnvironment($country, self::$config->getEnvironment());

        // Evaluate country policy to get base document type and meta.config flags
        $policy = CountryPolicyRegistry::evaluate($country, $logicalType);
        
        // Merge meta.config flags into payload
        $mergedPayload = self::deepMergeIntoMetaConfig($payload, $policy->getMetaConfigFlags());
        
        // Auto-set invoice_data.document_type based on LogicalDocType
        self::setInvoiceDataDocumentType($mergedPayload, $logicalType);
        
        // Create source reference
        $sourceRef = new SourceRef($finalSourceName, $finalSourceVersion);
        
        // Auto-generate destinations if none provided and auto-generation is enabled
        $finalDestinations = $destinations !== null ? $destinations : 
            (self::$config->isAutoGenerateTaxDestination() ? self::generateDefaultDestinations($country->getCode(), $policy->getDocumentType()) : []);
        
        // Build and send request using the resolved base document type
        return self::pushToUnifyInternalWithDocumentType($sourceRef, $policy->getBaseType(), self::getMetaConfigDocumentType($logicalType), $country, $operation, $mode, $purpose, $mergedPayload, $finalDestinations);
    }

    /**
     * Push to Unify API with logical document types using JSON string payload.
     * 
     * @param string $sourceName Source name
     * @param string $sourceVersion Source version
     * @param LogicalDocType $logicalType Logical document type
     * @param Country $country Country
     * @param Operation $operation Operation
     * @param Mode $mode Mode
     * @param Purpose $purpose Purpose
     * @param string $jsonPayload JSON string payload
     * @param array|null $destinations Optional destinations
     * @return array Response data
     * @throws SDKException
     */
    public static function pushToUnifyFromJson(
        $sourceName,
        $sourceVersion,
        LogicalDocType $logicalType,
        Country $country,
        Operation $operation,
        Mode $mode,
        Purpose $purpose,
        $jsonPayload,
        $destinations = null
    ) {
        if (empty($jsonPayload) || trim($jsonPayload) === '') {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::EMPTY_PAYLOAD,
                "Payload is required but was null or empty",
                'Provide a non-empty JSON payload string. Example: \'{"invoiceNumber":"INV-123","amount":1000}\''
            ));
        }

        $payloadArray = json_decode($jsonPayload, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MALFORMED_JSON,
                "Failed to parse JSON payload: " . json_last_error_msg(),
                'Ensure the payload is valid JSON. Example: \'{"invoiceNumber":"INV-123","amount":1000}\''
            );
            
            // Add context for debugging
            $payloadSnippet = strlen($jsonPayload) > 100 ? substr($jsonPayload, 0, 100) . '...' : $jsonPayload;
            $error->addContextValue('payloadSnippet', $payloadSnippet);
            $error->addContextValue('parseError', json_last_error_msg());
            
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail($error);
        }
        
        if (!is_array($payloadArray)) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MALFORMED_JSON,
                "Failed to parse JSON payload: parsed result is not an array",
                'Ensure the payload is valid JSON and represents an object structure. Example: \'{"invoiceNumber":"INV-123"}\''
            ));
        }

        return self::pushToUnify(
            $sourceName, $sourceVersion, $logicalType, $country,
            $operation, $mode, $purpose, $payloadArray, $destinations
        );
    }

    /**
     * Push to Unify API with logical document types using object payload.
     * 
     * @param string $sourceName Source name
     * @param string $sourceVersion Source version
     * @param LogicalDocType $logicalType Logical document type
     * @param Country $country Country
     * @param Operation $operation Operation
     * @param Mode $mode Mode
     * @param Purpose $purpose Purpose
     * @param mixed $payloadObject Object payload (stdClass, array, or any object)
     * @param array|null $destinations Optional destinations
     * @return array Response data
     * @throws SDKException
     */
    public static function pushToUnifyFromObject(
        $sourceName,
        $sourceVersion,
        LogicalDocType $logicalType,
        Country $country,
        Operation $operation,
        Mode $mode,
        Purpose $purpose,
        $payloadObject,
        $destinations = null
    ) {
        if ($payloadObject === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD,
                "Payload object is required but was null",
                "Provide a valid payload object. Example: ['invoiceNumber' => 'INV-123', 'amount' => 1000]"
            ));
        }

        try {
            // Convert object to array
            if (is_array($payloadObject)) {
                $payloadArray = $payloadObject;
            } elseif (is_object($payloadObject)) {
                // Convert object to array via JSON serialization
                $jsonString = json_encode($payloadObject);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON encoding failed: " . json_last_error_msg());
                }
                
                $payloadArray = json_decode($jsonString, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON decoding failed: " . json_last_error_msg());
                }
            } else {
                // Try to convert scalar values or other types
                $payloadArray = (array) $payloadObject;
            }

            if (!is_array($payloadArray)) {
                throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                    \ComplyanceSDK\Enums\ErrorCode::INVALID_PAYLOAD_FORMAT,
                    "Failed to convert payload object to array: conversion returned invalid result",
                    "Ensure the object structure is compatible with the SDK payload format. " .
                    "The object should be convertible to an array structure."
                ));
            }

            return self::pushToUnify(
                $sourceName, $sourceVersion, $logicalType, $country,
                $operation, $mode, $purpose, $payloadArray, $destinations
            );
        } catch (\ComplyanceSDK\Exceptions\SDKException $e) {
            throw $e;
        } catch (\Exception $conversionError) {
            $error = new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::INVALID_PAYLOAD_FORMAT,
                "Failed to convert payload object to array: " . $conversionError->getMessage(),
                "Ensure the object structure is compatible with the SDK payload format. " .
                "The object should be convertible to an array. " .
                "Example: ['invoiceNumber' => 'INV-123', 'amount' => 1000] or a class with public properties."
            );
            
            // Add context for debugging
            $error->addContextValue('objectType', gettype($payloadObject));
            $error->addContextValue('conversionError', $conversionError->getMessage());
            
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail($error);
        }
    }

    /**
     * Build the unified JSON payload matching Java SDK structure
     * 
     * @param array $payload Original payload
     * @return array Unified payload
     */
    private static function buildUnifiedPayload(array $payload): array
    {
        $unifiedPayload = [
            'source' => [
                'name' => $finalSourceName,
                'version' => $finalSourceVersion,
                'type' => 'FIRST_PARTY',
                'id' => $finalSourceName . ':' . $finalSourceVersion,
                'identity' => $finalSourceName . ':' . $finalSourceVersion
            ],
            'country' => $country->getCode(),
            'operation' => $operation->getCode(),
            'mode' => $mode->getCode(),
            'payload' => $payload,
            'apiKey' => self::$config->getApiKey(),
            'requestId' => 'req_' . time() . '_' . mt_rand(),
            'timestamp' => gmdate('Y-m-d\TH:i:s.u\Z'),
            'env' => strtolower(self::$config->getEnvironment()->getCode()),
            'destinations' => self::formatDestinationsForJava($finalDestinations),
            'correlationId' => null,
            'documentType' => strtolower($logicalType->getCode()),
            'purpose' => strtolower($purpose->getCode())
        ];

        // Determine API URL based on environment
        $apiUrl = self::$config->getEnvironment()->getBaseUrl();
        

        // Build UnifyRequest like Java SDK
        $request = UnifyRequest::builder()
            ->source($unifiedPayload['source'])
            ->documentType($logicalType->getBaseDocumentType())
            ->documentTypeString($unifiedPayload['documentType'])
            ->country($unifiedPayload['country'])
            ->operation(Operation::from($unifiedPayload['operation']))
            ->mode(Mode::from($unifiedPayload['mode']))
            ->purpose(Purpose::from($unifiedPayload['purpose']))
            ->payload($unifiedPayload['payload'])
            ->destinations($unifiedPayload['destinations'])
            ->apiKey($unifiedPayload['apiKey'])
            ->requestId($unifiedPayload['requestId'])
            ->timestamp($unifiedPayload['timestamp'])
            ->env($unifiedPayload['env'])
            ->correlationId($unifiedPayload['correlationId'])
            ->build();

        // Use APIClient to send the request like Java SDK
        $response = self::$apiClient->sendUnifyRequest($request);

        // Convert UnifyResponse to array for backward compatibility
        return $response->toArray();
    }

    /**
     * Simulate API call with realistic response structure
     * 
     * @param array $unifiedPayload The unified payload
     * @param string $apiUrl The API URL
     * @return array API response
     */
    private static function simulateAPICall(array $unifiedPayload, string $apiUrl): array
    {
        // Simulate different response scenarios based on payload
        $isError = false;
        $errorMessage = '';
        
        // Simulate validation errors
        if (empty($unifiedPayload['payload']['invoice_data']['invoice_number'])) {
            $isError = true;
            $errorMessage = 'Invoice number is required';
        }
        
        if ($isError) {
            return [
                'status' => 'error',
                'message' => $errorMessage,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $errorMessage,
                    'suggestion' => 'Please provide a valid invoice number'
                ],
                'requestId' => $unifiedPayload['requestId'],
                'timestamp' => gmdate('Y-m-d\TH:i:s.u\Z'),
                'apiUrl' => $apiUrl
            ];
        }
        
        // Success response
        return [
            'status' => 'success',
            'message' => 'Request processed successfully',
            'data' => [
                'submissionId' => 'sub_' . time() . '_' . mt_rand(),
                'requestId' => $unifiedPayload['requestId'],
                'source' => [
                    'name' => $unifiedPayload['source']['name'],
                    'version' => $unifiedPayload['source']['version']
                ],
                'logicalType' => $unifiedPayload['documentType'],
                'country' => $unifiedPayload['country'],
                'operation' => $unifiedPayload['operation'],
                'mode' => $unifiedPayload['mode'],
                'purpose' => $unifiedPayload['purpose'],
                'destinations' => $unifiedPayload['destinations'],
                'payload' => $unifiedPayload['payload'],
                'processingTime' => mt_rand(100, 500) . 'ms',
                'timestamp' => gmdate('Y-m-d\TH:i:s.u\Z')
            ],
            'requestId' => $unifiedPayload['requestId'],
            'timestamp' => gmdate('Y-m-d\TH:i:s.u\Z'),
            'apiUrl' => $apiUrl
        ];
    }

    /**
     * Format destinations to match Java SDK structure
     * 
     * @param array $destinations Destinations array
     * @return array Formatted destinations
     */
    private static function formatDestinationsForJava(array $destinations): array
    {
        $formattedDestinations = [];
        
        foreach ($destinations as $destination) {
            $formattedDestination = [
                'type' => strtoupper($destination['type'] ?? 'TAX_AUTHORITY'),
                'details' => [
                    'country' => $destination['details']['country'] ?? '',
                    'authority' => $destination['details']['authority'] ?? '',
                    'documentType' => $destination['details']['document_type'] ?? $destination['details']['documentType'] ?? ''
                ]
            ];
            
            // Add extensions if they exist
            if (isset($destination['extensions'])) {
                $formattedDestination['extensions'] = $destination['extensions'];
            }
            
            $formattedDestinations[] = $formattedDestination;
        }
        
        return $formattedDestinations;
    }

    /**
     * Convenience method to submit an invoice with logical document type.
     * 
     * @param string $sourceName Source name
     * @param string $sourceVersion Source version
     * @param Country $country Country
     * @param LogicalDocType $logicalType Logical document type
     * @param array $payload Payload data
     * @return array Response data
     * @throws SDKException
     */
    public static function submitInvoice(
        $sourceName,
        $sourceVersion,
        Country $country,
        LogicalDocType $logicalType,
        array $payload
    ) {
        return self::pushToUnify(
            $sourceName,
            $sourceVersion,
            $logicalType,
            $country,
            Operation::SINGLE,
            Mode::DOCUMENTS,
            Purpose::INVOICING,
            $payload
        );
    }

    /**
     * Convenience method to create a mapping template.
     * 
     * @param string $sourceName Source name
     * @param string $sourceVersion Source version
     * @param Country $country Country
     * @param LogicalDocType $logicalType Logical document type
     * @param array $payload Payload data
     * @return array Response data
     * @throws SDKException
     */
    public static function createMapping(
        $sourceName,
        $sourceVersion,
        Country $country,
        LogicalDocType $logicalType,
        array $payload
    ) {
        return self::pushToUnify(
            $sourceName,
            $sourceVersion,
            $logicalType,
            $country,
            Operation::SINGLE,
            Mode::DOCUMENTS,
            Purpose::MAPPING,
            $payload
        );
    }

    /**
     * Validate that SDK is properly configured
     * 
     * @throws ConfigurationException
     */
    private static function validateConfiguration()
    {
        if (self::$config === null) {
            throw new ConfigurationException('SDK not configured. Call GETSUnifySDK::configure() first.');
        }
    }

    /**
     * Find source by ID
     * 
     * @param string $sourceId Source ID
     * @return Source|null
     */
    private static function findSourceById($sourceId)
    {
        foreach (self::$config->getSources() as $source) {
            if ($source->getId() === $sourceId) {
                return $source;
            }
        }
        return null;
    }

    /**
     * Validate country restrictions based on current environment
     * 
     * @param Country $country Country to validate
     * @param Environment $environment Current environment
     * @throws ValidationException
     */
    private static function validateCountryForEnvironment(Country $country, Environment $environment)
    {
        $productionEnvironments = [
            Environment::SANDBOX,
            Environment::SIMULATION,
            Environment::PRODUCTION
        ];

        if (in_array($environment, $productionEnvironments)) {
            // SA is allowed in all production environments
            if ($country === Country::SA) {
                return;
            }

            // MY is only allowed in SANDBOX and PRODUCTION (not SIMULATION)
            if ($country === Country::MY) {
                if ($environment === Environment::SIMULATION) {
                    throw new ValidationException(
                        'Country not allowed for simulation environment',
                        'MY (Malaysia) is not allowed in SIMULATION environment. Use SANDBOX or PRODUCTION.'
                    );
                }
                return;
            }

            // All other countries are blocked in production environments
            throw new ValidationException(
                'Country not allowed for production environment',
                'Only SA and MY are allowed for ' . $environment->getCode() . '. Use DEV/TEST/STAGE for other countries.'
            );
        }
    }

    /**
     * Generate default destinations for a country and document type
     * 
     * @param string $country Country code
     * @param string $documentType Document type
     * @return array Array of destinations
     */
    private static function generateDefaultDestinations($country, $documentType)
    {
        $destinations = [];

        // Auto-generate tax authority destination
        $authority = self::getDefaultTaxAuthority($country);
        if ($authority !== null) {
            $destinations[] = [
                'type' => 'TAX_AUTHORITY',
                'details' => [
                    'authority' => $authority,
                    'country' => strtoupper($country),
                    'documentType' => strtolower($documentType)
                ]
            ];
        }

        return $destinations;
    }

    /**
     * Get default tax authority for a country
     * 
     * @param string $country Country code
     * @return string|null Tax authority or null
     */
    private static function getDefaultTaxAuthority($country)
    {
        switch (strtoupper($country)) {
            case 'SA':
                return 'ZATCA';
            case 'MY':
                return 'LHDN';
            case 'AE':
                return 'FTA';
            case 'SG':
                return 'IRAS';
            default:
                return null;
        }
    }

    /**
     * Get the status of a submission by its ID.
     * 
     * @param string $submissionId Submission ID
     * @return SubmissionStatus Status
     */
    public static function getStatus($submissionId)
    {
        // Stub: In a real implementation, this would query the API or local cache.
        return SubmissionStatus::fromString(SubmissionStatus::QUEUED);
    }

    /**
     * Get queue status and statistics
     * 
     * @return string Queue status
     */
    public static function getQueueStatus()
    {
        if (self::$queueManager !== null) {
            $status = self::$queueManager->getQueueStatus();
            return "Persistent Queue Status: " . $status->__toString();
        } else {
            return "Queue Manager is not initialized";
        }
    }
    
    /**
     * Get detailed queue status
     * 
     * @return \ComplyanceSDK\Models\QueueStatus|null Queue status object
     */
    public static function getDetailedQueueStatus()
    {
        if (self::$queueManager !== null) {
            return self::$queueManager->getQueueStatus();
        } else {
            return new \ComplyanceSDK\Models\QueueStatus(0, 0, 0, 0, false);
        }
    }
    
    /**
     * Retry failed submissions
     */
    public static function retryFailedSubmissions()
    {
        if (self::$queueManager !== null) {
            self::$queueManager->retryFailedSubmissions();
        }
    }
    
    /**
     * Cleanup old success files
     * 
     * @param int $daysToKeep Days to keep success files
     */
    public static function cleanupOldSuccessFiles($daysToKeep)
    {
        if (self::$queueManager !== null) {
            self::$queueManager->cleanupOldSuccessFiles($daysToKeep);
        }
    }
    
    /**
     * Clear all files from the queue (emergency cleanup)
     */
    public static function clearAllQueues()
    {
        if (self::$queueManager !== null) {
            self::$queueManager->clearAllQueues();
        } else {
        }
    }
    
    /**
     * Clean up duplicate files across queue directories
     */
    public static function cleanupDuplicateFiles()
    {
        if (self::$queueManager !== null) {
            self::$queueManager->cleanupDuplicateFiles();
        } else {
        }
    }
    
    /**
     * Process pending submissions
     */
    public static function processPendingSubmissions()
    {
        if (self::$queueManager !== null) {
            self::$queueManager->processPendingSubmissionsNow();
        }
    }

    /**
     * Process queued submissions before handling new requests
     */
    public static function processQueuedSubmissionsFirst()
    {
        if (self::$queueManager !== null) {
            self::$queueManager->processPendingSubmissionsNow();
        }
    }

    /**
     * Push to Unify API using a stored UnifyRequest object directly
     * This is used for retrying queued submissions
     * 
     * @param UnifyRequest $unifyRequest UnifyRequest object
     * @return UnifyResponse Response
     * @throws SDKException
     */
    public static function pushToUnifyRequest(UnifyRequest $unifyRequest)
    {
        if (self::$config === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "SDK not configured",
                "Call GETSUnifySDK.configure() first."
            ));
        }

        if ($unifyRequest === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "UnifyRequest is required",
                "Provide a valid UnifyRequest object."
            ));
        }


        // Use APIClient to send the stored UnifyRequest directly
        $apiClient = new APIClient(
            self::$config->getApiKey(), 
            self::$config->getEnvironment(), 
            self::$config->getRetryConfig()
        );
        return $apiClient->sendUnifyRequest($unifyRequest);
    }

    /**
     * Push to Unify API with logical document types.
     * This combines the benefits of logical document types with the flexibility of standard pushToUnify.
     * 
     * @param string $sourceName Source name
     * @param string $sourceVersion Source version
     * @param LogicalDocType $logicalType Logical document type
     * @param Country $country Country
     * @param Operation $operation Operation
     * @param Mode $mode Mode
     * @param Purpose $purpose Purpose
     * @param array $payload Payload data
     * @param array|null $destinations Destinations
     * @return UnifyResponse Response
     * @throws SDKException
     */
    public static function pushToUnifyLogical(
        $sourceName,
        $sourceVersion,
        LogicalDocType $logicalType,
        Country $country,
        Operation $operation,
        Mode $mode,
        Purpose $purpose,
        array $payload,
        ?array $destinations = null
    ) {
        if (self::$config === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "SDK not configured",
                "Call GETSUnifySDK.configure() first."
            ));
        }

        // Process queued submissions first before handling new requests
        self::processQueuedSubmissionsFirst();

        // Validate required parameters
        // Handle sourceName and sourceVersion based on purpose
        $finalSourceName;
        $finalSourceVersion;
        
        if ($purpose->getCode() === Purpose::MAPPING) {
            // For MAPPING purpose, sourceName and sourceVersion are optional - set to empty string if null
            $finalSourceName = ($sourceName === null) ? "" : $sourceName;
            $finalSourceVersion = ($sourceVersion === null) ? "" : $sourceVersion;
        } else {
            // For all other purposes, sourceName and sourceVersion are mandatory
            if ($sourceName === null || trim($sourceName) === '') {
                throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                    \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                    "Source name is required",
                    "Provide a valid source name."
                ));
            }
            if ($sourceVersion === null || trim($sourceVersion) === '') {
                throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                    \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                    "Source version is required",
                    "Provide a valid source version."
                ));
            }
            $finalSourceName = $sourceName;
            $finalSourceVersion = $sourceVersion;
        }

        // Validate other required parameters
        if ($logicalType === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "Logical document type is required",
                "Provide a valid logical document type."
            ));
        }
        if ($country === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "Country is required",
                "Provide a valid country."
            ));
        }
        if ($operation === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "Operation is required",
                "Provide a valid operation."
            ));
        }
        if ($mode === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "Mode is required",
                "Provide a valid mode."
            ));
        }
        if ($purpose === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "Purpose is required",
                "Provide a valid purpose."
            ));
        }
        if ($payload === null) {
            throw \ComplyanceSDK\Exceptions\SDKException::fromErrorDetail(new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::MISSING_FIELD, 
                "Payload is required",
                "Provide a valid payload."
            ));
        }

        // Validate country restrictions for current environment
        self::validateCountryForEnvironment($country, self::$config->getEnvironment());

        // Evaluate country policy to get base document type and meta.config flags
        $policy = CountryPolicyRegistry::evaluate($country, $logicalType);
        
        // Merge meta.config flags into payload
        $mergedPayload = self::deepMergeIntoMetaConfig($payload, $policy->getMetaConfigFlags());
        
        // Auto-set invoice_data.document_type based on LogicalDocType
        self::setInvoiceDataDocumentType($mergedPayload, $logicalType);
        
        // Create source reference
        $sourceRef = new SourceRef($finalSourceName, $finalSourceVersion);
        
        // Auto-generate destinations if none provided and auto-generation is enabled
        $finalDestinations = $destinations !== null ? $destinations : 
            (self::$config->isAutoGenerateTaxDestination() ? 
                self::generateDefaultDestinations($country->getCode(), $policy->getDocumentType()) : []);
        
        // Build and send request using the resolved base document type
        return self::pushToUnifyInternalWithDocumentType(
            $sourceRef, 
            $policy->getBaseType(), 
            self::getMetaConfigDocumentType($logicalType), 
            $country, 
            $operation, 
            $mode, 
            $purpose, 
            $mergedPayload, 
            $finalDestinations
        );
    }

    /**
     * Get meta config document type from logical type
     * 
     * @param LogicalDocType $logicalType Logical document type
     * @return string Document type string
     */
    private static function getMetaConfigDocumentType(LogicalDocType $logicalType)
    {
        $typeName = $logicalType->getCode();
        if (strpos($typeName, "CREDIT_NOTE") !== false) {
            return "credit_note";
        } else if (strpos($typeName, "DEBIT_NOTE") !== false) {
            return "debit_note";
        } else {
            return "tax_invoice";
        }
    }
    
    /**
     * Automatically sets the invoice_data.document_type field based on LogicalDocType
     * 
     * @param array $payload Payload data
     * @param LogicalDocType $logicalType Logical document type
     */
    private static function setInvoiceDataDocumentType(array &$payload, LogicalDocType $logicalType)
    {
        if ($payload === null) return;
        
        if (!isset($payload['invoice_data'])) return;
        
        // Determine document type string based on LogicalDocType
        $typeName = $logicalType->getCode();
        $documentType;
        if (strpos($typeName, "CREDIT_NOTE") !== false) {
            $documentType = "credit_note";
        } else if (strpos($typeName, "DEBIT_NOTE") !== false) {
            $documentType = "debit_note";
        } else {
            $documentType = "tax_invoice"; // Default for TAX_INVOICE and SIMPLIFIED_TAX_INVOICE
        }
        
        // Set the document_type field
        $payload['invoice_data']['document_type'] = $documentType;
    }

    /**
     * Deep merge meta.config flags into payload.
     * User values take precedence over policy defaults.
     * 
     * @param array $payload Original payload
     * @param array $configFlags Config flags to merge
     * @return array Merged payload
     */
    private static function deepMergeIntoMetaConfig(array $payload, array $configFlags)
    {
        $merged = $payload;
        
        $meta = $merged['meta'] ?? [];
        $config = $meta['config'] ?? [];
        
        // Merge config flags (user values take precedence)
        $mergedConfig = array_merge($configFlags, $config);
        
        $meta['config'] = $mergedConfig;
        $merged['meta'] = $meta;
        
        return $merged;
    }

    /**
     * Internal method to push to Unify API with custom document type string.
     * 
     * @param SourceRef $sourceRef Source reference
     * @param DocumentType $baseDocumentType Base document type
     * @param string $documentTypeString Document type string
     * @param Country $country Country
     * @param Operation $operation Operation
     * @param Mode $mode Mode
     * @param Purpose $purpose Purpose
     * @param array $payload Payload data
     * @param array $destinations Destinations
     * @return UnifyResponse Response
     * @throws SDKException
     */
    private static function pushToUnifyInternalWithDocumentType(
        SourceRef $sourceRef,
        DocumentType $baseDocumentType,
        string $documentTypeString,
        Country $country,
        Operation $operation,
        Mode $mode,
        Purpose $purpose,
        array $payload,
        array $destinations
    ) {
        // Build UnifyRequest with custom document type string
        $request = UnifyRequestBuilder::builder()
            ->source(self::buildSourceObject($sourceRef))
            ->documentType($baseDocumentType)
            ->documentTypeString(strtolower($documentTypeString)) // Add custom document type string
            ->country($country->getCode())
            ->operation($operation)
            ->mode($mode)
            ->purpose($purpose)
            ->payload($payload)
            ->destinations($destinations)
            ->apiKey(self::$config->getApiKey())
            ->requestId("req_" . (time() * 1000) . "_" . mt_rand())
            ->timestamp(date('c'))
            ->env(strtolower(self::$config->getEnvironment()->getCode()))
            ->correlationId(self::$config->getCorrelationId())
            ->build();
        
        try {
            return self::$apiClient->sendUnifyRequest($request);
        } catch (SDKException $e) {
            // Check if the error is a 500-range server error and queue is enabled
            if (self::isServerError($e) && self::$queueManager !== null) {
                error_log("SDK Queueing - Retryable error, adding to queue: " . $e->getMessage());
                // Store the complete UnifyRequest as JSON to maintain exact API format
                $completeRequestJson = json_encode($request->toArray());
                
                // Create a Source object for backward compatibility with queue
                $source = new Source(
                    $sourceRef->getName(), 
                    $sourceRef->getVersion(), 
                    \ComplyanceSDK\Enums\SourceType::fromString(\ComplyanceSDK\Enums\SourceType::FIRST_PARTY)
                );
                
                $submission = new \ComplyanceSDK\Models\PayloadSubmission(
                    $completeRequestJson, // Store complete UnifyRequest as JSON to maintain exact API format
                    $source,
                    $country,
                    $baseDocumentType
                );
                
                
                // Enqueue the failed submission for background retry
                self::$queueManager->enqueue($submission);
                
                // Return a response indicating the submission was queued
                $queuedResponse = new \ComplyanceSDK\UnifyResponse();
                $queuedResponse->setStatus("queued");
                $queuedResponse->setMessage("Request failed but has been queued for retry. Submission ID: " . $request->getRequestId());
                
                // Set submission ID in the submission response data
                $submissionResponse = new \ComplyanceSDK\UnifyResponseSubmissionResponse();
                $submissionResponse->setSubmissionId($request->getRequestId());
                
                $responseData = new \ComplyanceSDK\UnifyResponseData();
                $responseData->setSubmission($submissionResponse);
                $queuedResponse->setData($responseData);
                
                return $queuedResponse;
            }
            
            // If not a server error or queue not available, re-throw the exception
            throw $e;
        }
    }

    /**
     * Determines if an SDK exception represents a server error (500-range HTTP status codes).
     * Only 500-range errors (500-599) should trigger queue access.
     * 
     * @param SDKException $e The SDK exception
     * @return bool True if it's a 500-range HTTP error; otherwise, false
     */
    private static function isServerError(SDKException $e)
    {
        if ($e->getErrorDetail() === null) {
            return false;
        }

        // Check HTTP status code in context
        $httpStatusObj = $e->getErrorDetail()->getContextValue('httpStatus');
        if ($httpStatusObj !== null) {
            try {
                $statusCode = is_numeric($httpStatusObj) ? (int)$httpStatusObj : 0;
                
                // Only 500-range errors (500-599) should trigger queue access
                $isServerStatus = $statusCode >= 500 && $statusCode < 600;
                if (!$isServerStatus) {
                    error_log("HTTP status {$statusCode} detected (non 500-range) - skipping queue");
                } else {
                    error_log("Server error detected from HTTP status: {$statusCode}");
                }
                return $isServerStatus;
            } catch (Exception $ex) {
                error_log("Invalid HTTP status format: {$httpStatusObj}");
                // Ignore invalid status
            }
        } else {
            error_log("No httpStatus in ErrorDetail context, not counting as server error");
        }

        // Fallback: use error codes only when HTTP status is unavailable
        $errorCode = $e->getErrorDetail()->getCode();
        return $errorCode === \ComplyanceSDK\Enums\ErrorCode::INTERNAL_SERVER_ERROR ||
               $errorCode === \ComplyanceSDK\Enums\ErrorCode::SERVICE_UNAVAILABLE;
    }

    /**
     * Build source object from source reference
     * 
     * @param SourceRef $sourceRef Source reference
     * @return Source Source object
     */
    private static function buildSourceObject(SourceRef $sourceRef)
    {
        return new Source(
            $sourceRef->getName(),
            $sourceRef->getVersion(),
            \ComplyanceSDK\Enums\SourceType::fromString(\ComplyanceSDK\Enums\SourceType::FIRST_PARTY)
        );
    }

}