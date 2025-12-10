<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Enums\Environment;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Purpose;
use ComplyanceSDK\Models\RetryConfig;
use ComplyanceSDK\Models\RetryStrategy;
use ComplyanceSDK\Models\CircuitBreaker;
use ComplyanceSDK\Models\Source;
use ComplyanceSDK\Models\SubmissionResponse;
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

    /**
     * Constructor
     * 
     * @param string $apiKey API key
     * @param Environment $environment Environment
     * @param RetryConfig|null $retryConfig Retry configuration
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker instance
     */
    public function __construct(string $apiKey, Environment $environment, ?RetryConfig $retryConfig = null, ?CircuitBreaker $circuitBreaker = null)
    {
        $this->apiKey = $apiKey;
        $this->environment = $environment;
        $this->retryConfig = $retryConfig ?? RetryConfig::defaultConfig();
        $this->retryStrategy = new RetryStrategy($this->retryConfig, $circuitBreaker);
        $this->baseUrl = $environment->getBaseUrl();
        
        // Log configuration details prominently
        echo "🔧 SDK Configuration:\n";
        echo "   🌐 Environment: " . $environment->getCode() . "\n";
        echo "   🔗 Base URL: " . $this->baseUrl . "\n";
        echo "   🔑 API Key: " . substr($apiKey, 0, 8) . "...\n";
        echo "   🔄 Retry Config: " . $this->retryConfig->toJson() . "\n";
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
        // Execute the request with retry logic like Java SDK
        return $this->retryStrategy->execute(function() use ($request) {
            return $this->sendUnifyRequestInternal($request);
        }, "unify-request-" . $request->getSource()['name']);
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
        // Log essential request details
        echo "🌐 API Request URL: " . $this->baseUrl . "\n";
        echo "📡 Sending POST request to: " . $this->baseUrl . "\n";
        error_log("API Request - URL: " . $this->baseUrl . ", RequestID: " . $request->getRequestId() . 
                  ", DocType: " . $request->getDocumentTypeString() . ", Country: " . $request->getCountry());
        
        // Debug console output (will appear in VS Code Debug Console)
        if (function_exists('xdebug_break')) {
            xdebug_break(); // This will show in debug console
        }
        
        // Log request JSON
        $requestJson = json_encode($request->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        error_log("API Request JSON:\n" . $requestJson);

        // Make real HTTP API call
        $response = $this->makeHttpRequest($request);

        // Log response status and raw response
        $responseData = json_decode($response, true);
        $status = $responseData['status'] ?? 'unknown';
        error_log("API Response - RequestID: " . $request->getRequestId() . ", Status: " . $status);
        error_log("API Raw Response:\n" . $response);

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
        // Convert legacy parameters to UnifyRequest
        $request = UnifyRequest::builder()
            ->source([
                'name' => $source->getName(),
                'version' => $source->getVersion(),
                'type' => 'FIRST_PARTY',
                'id' => $source->getName() . ':' . $source->getVersion(),
                'identity' => $source->getName() . ':' . $source->getVersion()
            ])
            ->documentType($documentType)
            ->country($country->getCode())
            ->operation(Operation::SINGLE)
            ->mode(Mode::DOCUMENTS)
            ->purpose(Purpose::INVOICING)
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
        $status = $responseData['status'] ?? 'unknown';
        $message = $responseData['message'] ?? 'No message';
        
        return new SubmissionResponse($status, $message);
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
        $ch = curl_init();
        
        // Set up cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request->toArray()),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $request->getApiKey(),
                'X-Request-ID: ' . $request->getRequestId(),
                'Origin: SDK'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // For development only
            CURLOPT_SSL_VERIFYHOST => false, // For development only
        ]);
        
        // Add correlation ID header if available
        if ($request->getCorrelationId() !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
                'Content-Type: application/json',
                'Authorization: Bearer ' . $request->getApiKey(),
                'X-Request-ID: ' . $request->getRequestId(),
                'X-Correlation-ID: ' . $request->getCorrelationId(),
                'Origin: SDK'
            ]));
        }
        
        // Execute the request
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Handle cURL errors
        if ($responseBody === false || !empty($error)) {
            $errorDetail = new \ComplyanceSDK\Models\ErrorDetail(
                \ComplyanceSDK\Enums\ErrorCode::NETWORK_ERROR,
                'Network error: ' . $error,
                'Check your network connection and try again'
            );
            $errorDetail->setRetryable(true); // Explicitly set retryable flag
            throw SDKException::fromErrorDetail($errorDetail);
        }
        
        // Handle HTTP status codes
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->handleErrorResponse($httpCode, $responseBody);
        }
        
        // Log the raw response from API
        
        // Return raw response as-is without any parsing
        return $responseBody;
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
        error_log("API request failed with HTTP {$httpCode}: {$responseBody}");

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
