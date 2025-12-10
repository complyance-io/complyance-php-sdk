<?php

namespace Complyance\SDK\Tests\Integration;

use Complyance\SDK\ComplyanceSDK;
use Complyance\SDK\Config\RetryConfig;
use Complyance\SDK\Config\SDKConfig;
use Complyance\SDK\Enums\DocumentType;
use Complyance\SDK\Enums\Environment;
use Complyance\SDK\Enums\Mode;
use Complyance\SDK\Enums\Operation;
use Complyance\SDK\Enums\Purpose;
use Complyance\SDK\Exceptions\ApiException;
use Complyance\SDK\Exceptions\NetworkException;
use Complyance\SDK\Http\GuzzleHttpClient;
use Complyance\SDK\Models\Source;
use Complyance\SDK\Models\UnifyRequest;
use Complyance\SDK\Tests\TestDataProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration tests for the ComplyanceSDK class
 */
class ComplyanceSDKIntegrationTest extends TestCase
{
    /**
     * @var MockHandler
     */
    private $mockHandler;
    
    /**
     * @var array
     */
    private $requestHistory = [];
    
    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset SDK configuration before each test
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $configProperty = $reflectionClass->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue(null);
        
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue(null);
        
        $loggerProperty = $reflectionClass->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue(new NullLogger());
        
        // Set up mock handler and request history
        $this->mockHandler = new MockHandler();
        $this->requestHistory = [];
        $history = Middleware::history($this->requestHistory);
        
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);
        
        $guzzleClient = new Client(['handler' => $handlerStack]);
        
        // Create HTTP client with mock handler
        $httpClient = new GuzzleHttpClient();
        $reflectionClass = new \ReflectionClass(GuzzleHttpClient::class);
        $clientProperty = $reflectionClass->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($httpClient, $guzzleClient);
        
        // Set HTTP client in SDK
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($httpClient);
    }
    
    /**
     * Test successful invoice submission
     */
    public function testSuccessfulInvoiceSubmission()
    {
        // Configure mock response
        $responseData = [
            'status' => 'success',
            'message' => 'Document processed successfully',
            'data' => [
                'id' => 'doc-123456',
                'status' => 'PROCESSED',
                'validation_results' => [
                    'valid' => true,
                    'warnings' => [],
                    'errors' => []
                ]
            ],
            'metadata' => [
                'request_id' => 'req-123456',
                'processing_time' => 0.234
            ]
        ];
        
        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );
        
        // Configure SDK
        $config = TestDataProvider::createSDKConfig();
        ComplyanceSDK::configure($config);
        
        // Create request
        $request = TestDataProvider::createUnifyRequest();
        
        // Submit request
        $response = ComplyanceSDK::pushToUnify($request);
        
        // Verify response
        $this->assertEquals('success', $response->getStatus());
        $this->assertEquals('Document processed successfully', $response->getMessage());
        $this->assertEquals('doc-123456', $response->getData()['id']);
        $this->assertEquals('req-123456', $response->getMetadata()['request_id']);
        
        // Verify request
        $this->assertCount(1, $this->requestHistory);
        $sentRequest = $this->requestHistory[0]['request'];
        $this->assertEquals('POST', $sentRequest->getMethod());
        $this->assertEquals('application/json', $sentRequest->getHeaderLine('Content-Type'));
        $this->assertEquals('Bearer test-api-key', $sentRequest->getHeaderLine('Authorization'));
        
        // Verify request body
        $requestBody = json_decode((string) $sentRequest->getBody(), true);
        $this->assertEquals(DocumentType::TAX_INVOICE, $requestBody['document_type']);
        $this->assertEquals('SA', $requestBody['country']);
        $this->assertEquals(Operation::SINGLE, $requestBody['operation']);
        $this->assertEquals(Mode::DOCUMENTS, $requestBody['mode']);
        $this->assertEquals(Purpose::INVOICING, $requestBody['purpose']);
        $this->assertArrayHasKey('payload', $requestBody);
        $this->assertArrayHasKey('source', $requestBody);
    }
    
    /**
     * Test invoice submission with validation error
     */
    public function testInvoiceSubmissionWithValidationError()
    {
        // Configure mock response
        $responseData = [
            'status' => 'error',
            'message' => 'Validation failed',
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Required field missing',
                'suggestion' => 'Please provide invoice_number',
                'context' => [
                    'field' => 'invoice_number',
                    'rule' => 'required'
                ]
            ],
            'metadata' => [
                'request_id' => 'req-123456',
                'processing_time' => 0.123
            ]
        ];
        
        $this->mockHandler->append(
            new Response(400, ['Content-Type' => 'application/json'], json_encode($responseData))
        );
        
        // Configure SDK
        $config = TestDataProvider::createSDKConfig();
        ComplyanceSDK::configure($config);
        
        // Create request with missing invoice number
        $payload = TestDataProvider::createInvoicePayload();
        unset($payload['invoice_number']);
        $request = TestDataProvider::createUnifyRequest(null, null, null, null, null, $payload);
        
        // Submit request and expect exception
        try {
            ComplyanceSDK::pushToUnify($request);
            $this->fail('Expected ApiException was not thrown');
        } catch (ApiException $e) {
            $this->assertEquals('VALIDATION_ERROR', $e->getErrorCode());
            $this->assertEquals('Required field missing', $e->getMessage());
            $this->assertEquals('Please provide invoice_number', $e->getSuggestion());
            $this->assertEquals(['field' => 'invoice_number', 'rule' => 'required'], $e->getContext());
        }
        
        // Verify request was sent
        $this->assertCount(1, $this->requestHistory);
    }
    
    /**
     * Test invoice submission with network error and retry
     */
    public function testInvoiceSubmissionWithNetworkErrorAndRetry()
    {
        // Configure mock responses - first a network error, then success
        $this->mockHandler->append(
            new ConnectException('Connection timeout', new Request('POST', '/unify')),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'success',
                'message' => 'Document processed successfully',
                'data' => ['id' => 'doc-123456'],
                'metadata' => ['request_id' => 'req-123456']
            ]))
        );
        
        // Configure SDK with aggressive retry policy
        $retryConfig = RetryConfig::aggressive();
        $config = TestDataProvider::createSDKConfig(null, null, null, $retryConfig);
        ComplyanceSDK::configure($config);
        
        // Create request
        $request = TestDataProvider::createUnifyRequest();
        
        // Submit request
        $response = ComplyanceSDK::pushToUnify($request);
        
        // Verify response
        $this->assertEquals('success', $response->getStatus());
        $this->assertEquals('Document processed successfully', $response->getMessage());
        
        // Verify two requests were sent (original + retry)
        $this->assertCount(2, $this->requestHistory);
    }
    
    /**
     * Test invoice submission with server error and circuit breaker
     */
    public function testInvoiceSubmissionWithServerErrorAndCircuitBreaker()
    {
        // Configure mock responses - multiple server errors
        for ($i = 0; $i < 5; $i++) {
            $this->mockHandler->append(
                new Response(500, ['Content-Type' => 'application/json'], json_encode([
                    'status' => 'error',
                    'message' => 'Internal server error',
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => 'Internal server error'
                    ]
                ]))
            );
        }
        
        // Configure SDK with circuit breaker enabled
        $retryConfig = new RetryConfig(3, 0.1, 1.0, 2.0, 0.2, true, 3, 60);
        $config = TestDataProvider::createSDKConfig(null, null, null, $retryConfig);
        ComplyanceSDK::configure($config);
        
        // Create request
        $request = TestDataProvider::createUnifyRequest();
        
        // First request - should retry and fail
        try {
            ComplyanceSDK::pushToUnify($request);
            $this->fail('Expected ApiException was not thrown');
        } catch (ApiException $e) {
            $this->assertEquals('SERVER_ERROR', $e->getErrorCode());
            $this->assertEquals('Internal server error', $e->getMessage());
        }
        
        // Second request - should be rejected by circuit breaker
        try {
            ComplyanceSDK::pushToUnify($request);
            $this->fail('Expected NetworkException was not thrown');
        } catch (NetworkException $e) {
            $this->assertStringContainsString('Circuit breaker is open', $e->getMessage());
        }
        
        // Verify requests were sent for the first call but not the second
        $this->assertLessThanOrEqual(4, count($this->requestHistory)); // Original + up to 3 retries
    }
    
    /**
     * Test mapping creation
     */
    public function testMappingCreation()
    {
        // Configure mock response
        $mappingResponse = [
            'status' => 'success',
            'message' => 'Mapping created successfully',
            'data' => [
                'mapping' => TestDataProvider::createCountryFieldMapping('SA'),
                'missing_fields' => [],
                'extra_fields' => []
            ],
            'metadata' => [
                'request_id' => 'req-123456',
                'processing_time' => 0.123
            ]
        ];
        
        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mappingResponse))
        );
        
        // Configure SDK
        $config = TestDataProvider::createSDKConfig();
        ComplyanceSDK::configure($config);
        
        // Create mapping
        $payload = TestDataProvider::createInvoicePayload();
        $response = ComplyanceSDK::createMapping('SA', $payload);
        
        // Verify response
        $this->assertEquals('success', $response->getStatus());
        $this->assertEquals('Mapping created successfully', $response->getMessage());
        $this->assertArrayHasKey('mapping', $response->getData());
        
        // Verify request
        $this->assertCount(1, $this->requestHistory);
        $sentRequest = $this->requestHistory[0]['request'];
        $requestBody = json_decode((string) $sentRequest->getBody(), true);
        $this->assertEquals(Purpose::MAPPING, $requestBody['purpose']);
    }
    
    /**
     * Test async request processing
     */
    public function testAsyncRequestProcessing()
    {
        // Configure mock response
        $responseData = [
            'status' => 'success',
            'message' => 'Document processed successfully',
            'data' => ['id' => 'doc-123456'],
            'metadata' => ['request_id' => 'req-123456']
        ];
        
        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );
        
        // Configure SDK
        $config = TestDataProvider::createSDKConfig();
        ComplyanceSDK::configure($config);
        
        // Create request
        $request = TestDataProvider::createUnifyRequest();
        
        // Submit request asynchronously
        $responseReceived = false;
        $receivedResponse = null;
        
        ComplyanceSDK::pushToUnifyAsync(
            $request,
            function ($response) use (&$responseReceived, &$receivedResponse) {
                $responseReceived = true;
                $receivedResponse = $response;
            },
            function ($error) {
                $this->fail('Error callback should not be called: ' . $error->getMessage());
            }
        );
        
        // Verify response was received
        $this->assertTrue($responseReceived);
        $this->assertEquals('success', $receivedResponse->getStatus());
        $this->assertEquals('Document processed successfully', $receivedResponse->getMessage());
        
        // Verify request was sent
        $this->assertCount(1, $this->requestHistory);
    }
    
    /**
     * Test different document types
     * 
     * @dataProvider documentTypeProvider
     */
    public function testDifferentDocumentTypes(string $documentType, array $payload)
    {
        // Configure mock response
        $responseData = [
            'status' => 'success',
            'message' => 'Document processed successfully',
            'data' => ['id' => 'doc-123456'],
            'metadata' => ['request_id' => 'req-123456']
        ];
        
        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );
        
        // Configure SDK
        $config = TestDataProvider::createSDKConfig();
        ComplyanceSDK::configure($config);
        
        // Create request
        $request = TestDataProvider::createUnifyRequest($documentType, null, null, null, null, $payload);
        
        // Submit request
        $response = ComplyanceSDK::pushToUnify($request);
        
        // Verify response
        $this->assertEquals('success', $response->getStatus());
        
        // Verify request
        $this->assertCount(1, $this->requestHistory);
        $sentRequest = $this->requestHistory[0]['request'];
        $requestBody = json_decode((string) $sentRequest->getBody(), true);
        $this->assertEquals($documentType, $requestBody['document_type']);
    }
    
    /**
     * Data provider for document types
     */
    public function documentTypeProvider(): array
    {
        return [
            'Tax Invoice' => [DocumentType::TAX_INVOICE, TestDataProvider::createInvoicePayload()],
            'Credit Note' => [DocumentType::CREDIT_NOTE, TestDataProvider::createCreditNotePayload()],
            'Debit Note' => [DocumentType::DEBIT_NOTE, TestDataProvider::createDebitNotePayload()]
        ];
    }
    
    /**
     * Test different countries
     * 
     * @dataProvider countryProvider
     */
    public function testDifferentCountries(string $country, array $mapping)
    {
        // Configure mock response
        $mappingResponse = [
            'status' => 'success',
            'message' => 'Mapping created successfully',
            'data' => [
                'mapping' => $mapping,
                'missing_fields' => [],
                'extra_fields' => []
            ],
            'metadata' => [
                'request_id' => 'req-123456',
                'processing_time' => 0.123
            ]
        ];
        
        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($mappingResponse))
        );
        
        // Configure SDK
        $config = TestDataProvider::createSDKConfig();
        ComplyanceSDK::configure($config);
        
        // Create mapping
        $payload = TestDataProvider::createInvoicePayload();
        $response = ComplyanceSDK::createMapping($country, $payload);
        
        // Verify response
        $this->assertEquals('success', $response->getStatus());
        
        // Verify request
        $this->assertCount(1, $this->requestHistory);
        $sentRequest = $this->requestHistory[0]['request'];
        $requestBody = json_decode((string) $sentRequest->getBody(), true);
        $this->assertEquals($country, $requestBody['country']);
    }
    
    /**
     * Data provider for countries
     */
    public function countryProvider(): array
    {
        return [
            'Saudi Arabia' => ['SA', TestDataProvider::createCountryFieldMapping('SA')],
            'UAE' => ['AE', TestDataProvider::createCountryFieldMapping('AE')],
            'Poland' => ['PL', TestDataProvider::createCountryFieldMapping('PL')]
        ];
    }
    
    /**
     * Test different environments
     * 
     * @dataProvider environmentProvider
     */
    public function testDifferentEnvironments(string $environment, string $expectedBaseUrl)
    {
        // Configure mock response
        $responseData = [
            'status' => 'success',
            'message' => 'Document processed successfully',
            'data' => ['id' => 'doc-123456'],
            'metadata' => ['request_id' => 'req-123456']
        ];
        
        $this->mockHandler->append(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($responseData))
        );
        
        // Configure SDK with specific environment
        $config = TestDataProvider::createSDKConfig(null, $environment);
        ComplyanceSDK::configure($config);
        
        // Create request
        $request = TestDataProvider::createUnifyRequest();
        
        // Submit request
        $response = ComplyanceSDK::pushToUnify($request);
        
        // Verify response
        $this->assertEquals('success', $response->getStatus());
        
        // Verify request URL contains the expected base URL
        $this->assertCount(1, $this->requestHistory);
        $sentRequest = $this->requestHistory[0]['request'];
        $this->assertStringContainsString($expectedBaseUrl, (string) $sentRequest->getUri());
    }
    
    /**
     * Data provider for environments
     */
    public function environmentProvider(): array
    {
        return [
            'Sandbox' => [Environment::SANDBOX, 'api.sandbox.complyance.io'],
            'Production' => [Environment::PRODUCTION, 'api.complyance.io'],
            'Local' => [Environment::LOCAL, 'localhost']
        ];
    }
}