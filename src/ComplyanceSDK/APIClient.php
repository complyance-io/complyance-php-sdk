<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\Environment;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Purpose;
use ComplyanceSDK\Models\RetryConfig;
use ComplyanceSDK\Models\RetryStrategy;
use ComplyanceSDK\Models\CircuitBreaker;
use ComplyanceSDK\Models\Source;
use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Exceptions\APIException;

/**
 * API Client for making HTTP requests to the Complyance API
 * 
 * @package ComplyanceSDK
 */
class APIClient
{
    private $apiKey;
    private $environment;
    private $retryConfig;
    private $retryStrategy;
    private $baseUrl;
    private $debug;

    /**
     * Constructor
     * 
     * @param string $apiKey API key
     * @param Environment $environment Environment
     * @param RetryConfig|null $retryConfig Retry configuration
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker instance
     * @param bool $debug Include v3 API debug information in responses
     */
    public function __construct(
        string $apiKey,
        Environment $environment,
        ?RetryConfig $retryConfig = null,
        ?CircuitBreaker $circuitBreaker = null,
        bool $debug = false
    ) {
        $this->apiKey = $this->sanitizeHeaderValue($apiKey);
        $this->environment = $environment;
        $this->retryConfig = $retryConfig ?? RetryConfig::defaultConfig();
        $this->retryStrategy = new RetryStrategy($this->retryConfig, $circuitBreaker);
        $this->baseUrl = $environment->getUnifyV3Url();
        $this->debug = $debug;
        
        if ($this->debug) {
            error_log(
                'SDK Configuration - Environment: ' . $environment->getCode() .
                ', Base URL: ' . $this->baseUrl
            );
        }
    }

    /**
     * Send a UnifyRequest to the API
     * 
     * @param UnifyRequest $request The request to send
     * @return string The raw API response
     * @throws SDKException
     */
    public function sendUnifyRequest(UnifyRequest $request): string
    {
        $source = $request->source ?? [];
        $sourceName = is_array($source)
            ? (string)($source['name'] ?? ($source['identity'] ?? 'unknown'))
            : (string)$source;

        return $this->retryStrategy->execute(function() use ($request) {
            return $this->sendUnifyRequestInternal($request);
        }, 'unify-request-' . $sourceName);
    }

    /**
     * Send an already-shaped Unify request without changing its fields.
     *
     * Use this for the legacy batch envelope and for callers that already own
     * the complete revamped envelope.
     *
     * @param array $request Complete Unify request body
     * @param bool|null $newApi True for revamped, false for legacy, null to omit the selector
     */
    public function sendRawUnifyRequest(array $request, ?bool $newApi = null): string
    {
        $requestBody = json_encode($request);
        if ($requestBody === false) {
            throw new \RuntimeException('Failed to encode Unify request: ' . json_last_error_msg());
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
        if ($newApi !== null) {
            $headers[] = 'new-api: ' . ($newApi ? 'true' : 'false');
        }

        return $this->sendEncodedRequest($requestBody, $headers);
    }

    /**
     * Submit multiple typed invoices through the revamped Unify bulk contract.
     *
     * @param UnifyRequest[] $requests Ordered invoice requests (maximum 10)
     */
    public function sendRevampedBulkUnifyRequest(array $requests): UnifyBulkResponse
    {
        $requestCount = count($requests);
        if ($requestCount === 0) {
            throw new \InvalidArgumentException('At least one UnifyRequest is required.');
        }
        if ($requestCount > 10) {
            throw new \InvalidArgumentException('A maximum of 10 UnifyRequest objects is allowed.');
        }

        $invoices = [];
        $expectedPosition = 0;
        foreach ($requests as $position => $request) {
            if ($position !== $expectedPosition) {
                throw new \InvalidArgumentException(
                    'Bulk Unify requests must use consecutive zero-based indexes.'
                );
            }
            if (!$request instanceof UnifyRequest) {
                throw new \InvalidArgumentException(
                    "Bulk request item {$position} must be an instance of UnifyRequest."
                );
            }
            $invoices[] = UnifyV3RequestSerializer::serialize(
                $request,
                $this->environment,
                $this->debug
            );
            $expectedPosition++;
        }

        $responseBody = $this->sendRawUnifyRequest(['invoices' => $invoices], true);
        return UnifyBulkResponse::fromJson($responseBody);
    }

    /**
     * Internal method to send UnifyRequest with detailed logging
     * 
     * @param UnifyRequest $request The request to send
     * @return string The raw API response
     * @throws SDKException
     */
    private function sendUnifyRequestInternal(UnifyRequest $request): string
    {
        if ($this->debug) {
            error_log(
                'API Request - URL: ' . $this->baseUrl .
                ', RequestID: ' . $request->getRequestId() .
                ', DocType: ' . $request->getDocumentTypeString() .
                ', Country: ' . $request->getCountry()
            );
        }

        // Make real HTTP API call
        $response = $this->makeHttpRequest($request);

        if ($this->debug) {
            $responseData = json_decode($response, true);
            $documentId = is_array($responseData) ? ($responseData['documentId'] ?? 'none') : 'none';
            error_log(
                'API Response - RequestID: ' . $request->getRequestId() .
                ', DocumentID: ' . $documentId
            );
        }

        return $response;
    }

    /**
     * Send payload using legacy method (for backward compatibility)
     * 
     * @param string $clientPayloadJson JSON payload
     * @param Source $source Source object
     * @param Country $country Country
     * @param DocumentType $documentType Document type
     * @return SubmissionResponse Response
     * @throws SDKException
     */
    public function sendPayload(string $clientPayloadJson, Source $source, Country $country, DocumentType $documentType): SubmissionResponse
    {
        // Convert legacy parameters to UnifyRequest.
        $v3DocumentType = $this->mapLegacyDocumentType($documentType);
        $request = UnifyRequest::builder()
            ->source([
                'name' => $source->getName(),
                'version' => $source->getVersion(),
                'type' => 'FIRST_PARTY',
                'id' => $source->getName() . ':' . $source->getVersion(),
                'identity' => $source->getName() . ':' . $source->getVersion()
            ])
            ->documentType($documentType)
            ->documentTypeString($v3DocumentType['base'])
            ->documentTypeV2($v3DocumentType)
            ->country($country->getCode())
            ->operation(Operation::from(Operation::SINGLE))
            ->mode(Mode::from(Mode::DOCUMENTS))
            ->purpose(Purpose::from(Purpose::INVOICING))
            ->payload(json_decode($clientPayloadJson, true))
            ->destinations([])
            ->apiKey($this->apiKey)
            ->requestId('req_' . time() . '_' . mt_rand())
            ->timestamp(gmdate('Y-m-d\TH:i:s.u\Z'))
            ->env(strtolower($this->environment->getCode()))
            ->correlationId(null)
            ->build();

        $response = $this->sendUnifyRequest($request);
        
        // Parse response to get status and message for backward compatibility
        $responseData = json_decode($response, true);
        $responseData = is_array($responseData) ? $responseData : [];
        $legacyResult = null;
        if (
            isset($responseData['data']['results'][0]) &&
            is_array($responseData['data']['results'][0])
        ) {
            $legacyResult = $responseData['data']['results'][0];
        }

        $status = 'unknown';
        if (!empty($responseData['documentId']) || !empty($responseData['payloadId'])) {
            $status = 'success';
        } elseif (is_array($legacyResult) && isset($legacyResult['status'])) {
            $status = (string)$legacyResult['status'];
        } elseif (isset($responseData['status'])) {
            $status = (string)$responseData['status'];
        }

        $message = isset($responseData['message'])
            ? (string)$responseData['message']
            : 'No message';
        if (
            $status === 'failed' &&
            is_array($legacyResult) &&
            isset($legacyResult['validation']['errors'][0]['message'])
        ) {
            $message = (string)$legacyResult['validation']['errors'][0]['message'];
        } elseif (
            $status === 'failed' &&
            is_array($legacyResult) &&
            isset($legacyResult['errors'][0]['message'])
        ) {
            $message = (string)$legacyResult['errors'][0]['message'];
        }

        $submissionResponse = new SubmissionResponse($status, $message);
        $submissionId = null;
        if (!empty($responseData['documentId'])) {
            $submissionId = $responseData['documentId'];
        } elseif (!empty($responseData['payloadId'])) {
            $submissionId = $responseData['payloadId'];
        } elseif (is_array($legacyResult) && !empty($legacyResult['documentId'])) {
            $submissionId = $legacyResult['documentId'];
        } elseif (is_array($legacyResult) && !empty($legacyResult['payloadId'])) {
            $submissionId = $legacyResult['payloadId'];
        }
        if (is_string($submissionId) && $submissionId !== '') {
            $submissionResponse->setSubmissionId($submissionId);
        }

        return $submissionResponse;
    }

    private function mapLegacyDocumentType(DocumentType $documentType): array
    {
        switch ($documentType->getCode()) {
            case DocumentType::TAX_INVOICE:
                return ['base' => 'tax_invoice', 'modifiers' => []];
            case DocumentType::CREDIT_NOTE:
                return ['base' => 'credit_note', 'modifiers' => []];
            case DocumentType::DEBIT_NOTE:
                return ['base' => 'debit_note', 'modifiers' => []];
            case DocumentType::SIMPLIFIED_TAX_INVOICE:
                return ['base' => 'simplified_invoice', 'modifiers' => []];
            case DocumentType::SIMPLIFIED_CREDIT_NOTE:
                return ['base' => 'simplified_credit_note', 'modifiers' => []];
            case DocumentType::SIMPLIFIED_DEBIT_NOTE:
                return ['base' => 'simplified_debit_note', 'modifiers' => []];
            case DocumentType::SELF_BILLED_INVOICE:
                return ['base' => 'tax_invoice', 'modifiers' => ['self_billed']];
            default:
                throw new \InvalidArgumentException(
                    'This legacy document type has no unambiguous v3 mapping. ' .
                    'Use GETSUnifySDK::pushToUnifyWithDocumentType() instead.'
                );
        }
    }

    /**
     * Make real HTTP request to the API
     * 
     * @param UnifyRequest $request The request
     * @return string The raw response
     * @throws SDKException
     */
    private function makeHttpRequest(UnifyRequest $request): string
    {
        $newApi = $request->getNewApi();
        $requestData = $newApi === true
            ? UnifyV3RequestSerializer::serialize($request, $this->environment, $this->debug)
            : UnifyLegacyRequestSerializer::serialize($request);
        $requestBody = json_encode($requestData);

        if ($requestBody === false) {
            throw new \RuntimeException('Failed to encode Unify request: ' . json_last_error_msg());
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        if (isset($request->requestId) && trim((string)$request->requestId) !== '') {
            $headers[] = 'X-Request-ID: ' . $this->sanitizeHeaderValue((string)$request->requestId);
        }
        if (isset($request->correlationId) && trim((string)$request->correlationId) !== '') {
            $headers[] = 'X-Correlation-ID: ' . $this->sanitizeHeaderValue((string)$request->correlationId);
        }
        if ($newApi !== null) {
            $headers[] = 'new-api: ' . ($newApi ? 'true' : 'false');
        }

        return $this->sendEncodedRequest($requestBody, $headers);
    }

    private function sendEncodedRequest(string $requestBody, array $headers): string
    {
        $result = $this->executeHttpRequest($this->baseUrl, $requestBody, $headers);
        $responseBody = $result['body'];
        $httpCode = $result['status'];
        $error = $result['error'];

        if ($responseBody === false || $error !== '') {
            $errorDetail = new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::NETWORK_ERROR,
                'Network error: ' . $error,
                'Check your network connection and try again'
            );
            $errorDetail->setRetryable(true);
            throw SDKException::fromErrorDetail($errorDetail);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->handleErrorResponse($httpCode, $responseBody);
        }

        return $responseBody;
    }

    private function sanitizeHeaderValue(string $value): string
    {
        $value = trim($value);
        if (preg_match('/[\r\n]/', $value)) {
            throw new \InvalidArgumentException('HTTP header values cannot contain line breaks.');
        }

        return $value;
    }

    /**
     * Execute the HTTP request. Protected to allow network-free contract tests.
     *
     * @return array{body:string|false,status:int,error:string}
     */
    protected function executeHttpRequest(string $url, string $body, array $headers): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }

        return [
            'body' => $responseBody,
            'status' => (int)$httpCode,
            'error' => (string)$error,
        ];
    }

    /**
     * Handle HTTP error responses
     * 
     * @param int $httpCode HTTP status code
     * @param string $responseBody Response body
     * @throws SDKException
     */
    private function handleErrorResponse($httpCode, $responseBody)
    {
        if ($this->debug) {
            error_log("API request failed with HTTP {$httpCode}");
        }

        // Create base error detail
        $errorDetail = new \ComplyanceSDK\Models\ErrorDetail(
            \ComplyanceSDK\Enums\ErrorCode::API_ERROR,
            "HTTP {$httpCode}: API request failed",
            "Check the error details and try again"
        );

        // Handle specific HTTP status codes
        switch ($httpCode) {
            case 400:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::VALIDATION_FAILED);
                $errorDetail->setMessage("Bad Request: Invalid request parameters");
                $errorDetail->setSuggestion("Check your request parameters and payload format");
                break;
            case 401:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::AUTHENTICATION_FAILED);
                $errorDetail->setMessage("Unauthorized: Authentication failed");
                $errorDetail->setSuggestion("Check your API key and ensure it's valid");
                break;
            case 403:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::AUTHORIZATION_FAILED);
                $errorDetail->setMessage("Forbidden: Authorization denied");
                $errorDetail->setSuggestion("Your API key doesn't have permission for this operation");
                break;
            case 404:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::API_ERROR);
                $errorDetail->setMessage("Not Found: The requested endpoint was not found");
                $errorDetail->setSuggestion("The requested endpoint was not found. Check your SDK version");
                break;
            case 422:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::VALIDATION_FAILED);
                $errorDetail->setMessage("Unprocessable Entity: Request data failed validation");
                $errorDetail->setSuggestion("Your request data failed validation. Check the error details");
                break;
            case 429:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::RATE_LIMIT_EXCEEDED);
                $errorDetail->setMessage("Too Many Requests: Rate limit exceeded");
                $errorDetail->setSuggestion("Too many requests. Please wait before retrying");
                $errorDetail->setRetryable(true);
                $errorDetail->setRetryAfterSeconds(60); // Default retry after 60 seconds
                break;
            case 500:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::SERVER_ERROR);
                $errorDetail->setMessage("Internal Server Error: Server encountered an error");
                $errorDetail->setSuggestion("Server error occurred. The request can be retried");
                $errorDetail->setRetryable(true);
                break;
            case 502:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::SERVICE_UNAVAILABLE);
                $errorDetail->setMessage("Bad Gateway: Service temporarily unavailable");
                $errorDetail->setSuggestion("Service is temporarily unavailable. Please retry after some time");
                $errorDetail->setRetryable(true);
                break;
            case 503:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::SERVICE_UNAVAILABLE);
                $errorDetail->setMessage("Service Unavailable: Service temporarily unavailable");
                $errorDetail->setSuggestion("Service is temporarily unavailable. Please retry after some time");
                $errorDetail->setRetryable(true);
                break;
            case 504:
                $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::TIMEOUT_ERROR);
                $errorDetail->setMessage("Gateway Timeout: Request timed out");
                $errorDetail->setSuggestion("Request timed out. Please retry");
                $errorDetail->setRetryable(true);
                break;
            default:
                if ($httpCode >= 500) {
                    $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::SERVER_ERROR);
                    $errorDetail->setMessage("Server Error: HTTP {$httpCode}");
                    $errorDetail->setSuggestion("Server error occurred. The request can be retried");
                    $errorDetail->setRetryable(true);
                } else {
                    $errorDetail->setCode(\ComplyanceSDK\Enums\ErrorCode::API_ERROR);
                    $errorDetail->setMessage("API Error: HTTP {$httpCode}");
                    $errorDetail->setSuggestion("Check your request and try again");
                }
                break;
        }

        // Add context information
        $errorDetail->addContextValue("httpStatus", $httpCode);
        $errorDetail->addContextValue("responseBody", $responseBody);


        throw SDKException::fromErrorDetail($errorDetail);
    }

    /**
     * Simulate API call with realistic response (for testing/fallback)
     * 
     * @param UnifyRequest $request The request
     * @return UnifyResponse The response
     */
    private function simulateAPICall(UnifyRequest $request): UnifyResponse
    {
        // Simulate different response scenarios based on request
        $isError = false;
        $errorMessage = '';
        
        // Simulate validation errors
        $payload = $request->getPayload();
        if (empty($payload['invoice_data']['invoice_number'])) {
            $isError = true;
            $errorMessage = 'Invoice number is required';
        }
        
        if ($isError) {
            $response = new UnifyResponse();
            $response->setStatus('error');
            $response->setMessage($errorMessage);
            
            $error = new ErrorDetail();
            $error->setCode('VALIDATION_ERROR');
            $error->setMessage($errorMessage);
            $error->setSuggestion('Please provide a valid invoice number');
            $response->setError($error);
            
            return $response;
        }
        
        // Success response
        echo "✅ SUCCESS: RAW API RESPONSE: " . $responseBody . "\n";
        echo "🎉 SUCCESS: API request completed successfully with status: success\n";
        
        $response = new UnifyResponse();
        $response->setStatus('success');
        $response->setMessage('Request processed successfully');
        
        $data = new UnifyResponseData();
        $submission = new UnifyResponseSubmissionResponse();
        $submission->setSubmissionId('sub_' . time() . '_' . mt_rand());
        $data->setSubmission($submission);
        $response->setData($data);
        
        return $response;
    }

}
