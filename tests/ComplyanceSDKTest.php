<?php

namespace Complyance\SDK\Tests;

use Complyance\SDK\ComplyanceSDK;
use Complyance\SDK\Config\RetryConfig;
use Complyance\SDK\Config\SDKConfig;
use Complyance\SDK\Exceptions\ApiException;
use Complyance\SDK\Exceptions\ConfigurationException;
use Complyance\SDK\Exceptions\NetworkException;
use Complyance\SDK\Http\ComplyanceHttpClient;
use Complyance\SDK\Models\DocumentType;
use Complyance\SDK\Models\Mode;
use Complyance\SDK\Models\Operation;
use Complyance\SDK\Models\Purpose;
use Complyance\SDK\Models\Source;
use Complyance\SDK\Models\UnifyRequest;
use Complyance\SDK\Models\UnifyResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ComplyanceSDKTest extends TestCase
{
    protected function setUp(): void
    {
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
        $loggerProperty->setValue(new \Psr\Log\NullLogger());
    }

    public function testConfigureAndGetConfig()
    {
        $config = new SDKConfig('test-api-key', 'sandbox');
        ComplyanceSDK::configure($config);
        
        $this->assertTrue(ComplyanceSDK::isConfigured());
        $this->assertSame($config, ComplyanceSDK::getConfig());
    }

    public function testGetConfigThrowsExceptionWhenNotConfigured()
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('SDK not configured');
        
        ComplyanceSDK::getConfig();
    }

    public function testPushToUnify()
    {
        // Create a mock HTTP client
        $mockHttpClient = $this->createMock(ComplyanceHttpClient::class);
        $mockResponse = new UnifyResponse();
        $mockResponse->setStatus('success');
        $mockResponse->setMessage('Document processed successfully');
        $mockResponse->setData(['id' => '123']);
        $mockResponse->setMetadata(['request_id' => 'req-123']);
        
        $mockHttpClient->expects($this->once())
            ->method('sendUnifyRequest')
            ->willReturn($mockResponse);
        
        // Configure the SDK
        $config = new SDKConfig('test-api-key', 'sandbox');
        ComplyanceSDK::configure($config);
        
        // Set the mock HTTP client
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($mockHttpClient);
        
        // Create a request
        $request = new UnifyRequest();
        $request->setDocumentType(DocumentType::TAX_INVOICE);
        $request->setCountry('SA');
        $request->setOperation(Operation::SINGLE);
        $request->setMode(Mode::DOCUMENTS);
        $request->setPurpose(Purpose::INVOICING);
        $request->setPayload(['invoice_number' => 'INV-001']);
        
        // Send the request
        $response = ComplyanceSDK::pushToUnify($request);
        
        // Assert the response
        $this->assertSame('success', $response->getStatus());
        $this->assertSame('Document processed successfully', $response->getMessage());
        $this->assertSame(['id' => '123'], $response->getData());
        $this->assertSame(['request_id' => 'req-123'], $response->getMetadata());
    }

    public function testPushToUnifyAsync()
    {
        // Create a mock HTTP client
        $mockHttpClient = $this->createMock(ComplyanceHttpClient::class);
        $mockResponse = new UnifyResponse();
        $mockResponse->setStatus('success');
        $mockResponse->setMessage('Document processed successfully');
        $mockResponse->setData(['id' => '123']);
        $mockResponse->setMetadata(['request_id' => 'req-123']);
        
        $mockHttpClient->expects($this->once())
            ->method('sendUnifyRequestAsync')
            ->willReturnCallback(function ($request, $onSuccess, $onError) use ($mockResponse) {
                $onSuccess($mockResponse);
            });
        
        // Configure the SDK
        $config = new SDKConfig('test-api-key', 'sandbox');
        ComplyanceSDK::configure($config);
        
        // Set the mock HTTP client
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($mockHttpClient);
        
        // Create a request
        $request = new UnifyRequest();
        $request->setDocumentType(DocumentType::TAX_INVOICE);
        $request->setCountry('SA');
        $request->setOperation(Operation::SINGLE);
        $request->setMode(Mode::DOCUMENTS);
        $request->setPurpose(Purpose::INVOICING);
        $request->setPayload(['invoice_number' => 'INV-001']);
        
        // Send the request asynchronously
        $responseReceived = false;
        $receivedResponse = null;
        
        ComplyanceSDK::pushToUnifyAsync(
            $request,
            function ($response) use (&$responseReceived, &$receivedResponse) {
                $responseReceived = true;
                $receivedResponse = $response;
            },
            function ($error) {
                $this->fail('Error callback should not be called');
            }
        );
        
        // Assert the response was received
        $this->assertTrue($responseReceived);
        $this->assertSame('success', $receivedResponse->getStatus());
        $this->assertSame('Document processed successfully', $receivedResponse->getMessage());
        $this->assertSame(['id' => '123'], $receivedResponse->getData());
        $this->assertSame(['request_id' => 'req-123'], $receivedResponse->getMetadata());
    }

    public function testSubmitInvoice()
    {
        // Create a mock HTTP client
        $mockHttpClient = $this->createMock(ComplyanceHttpClient::class);
        $mockResponse = new UnifyResponse();
        $mockResponse->setStatus('success');
        
        $mockHttpClient->expects($this->once())
            ->method('sendUnifyRequest')
            ->willReturnCallback(function (UnifyRequest $request) use ($mockResponse) {
                // Verify the request was properly constructed
                $this->assertSame(DocumentType::TAX_INVOICE, $request->getDocumentType());
                $this->assertSame('SA', $request->getCountry());
                $this->assertSame(Operation::SINGLE, $request->getOperation());
                $this->assertSame(Mode::DOCUMENTS, $request->getMode());
                $this->assertSame(Purpose::INVOICING, $request->getPurpose());
                $this->assertSame(['invoice_number' => 'INV-001'], $request->getPayload());
                
                return $mockResponse;
            });
        
        // Configure the SDK with a source
        $source = new Source('test-source', 'FIRST_PARTY', 'Test Source');
        $config = new SDKConfig('test-api-key', 'sandbox', [$source]);
        ComplyanceSDK::configure($config);
        
        // Set the mock HTTP client
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($mockHttpClient);
        
        // Submit an invoice
        $response = ComplyanceSDK::submitInvoice('SA', ['invoice_number' => 'INV-001']);
        
        // Assert the response
        $this->assertSame('success', $response->getStatus());
    }

    public function testCreateMapping()
    {
        // Create a mock HTTP client
        $mockHttpClient = $this->createMock(ComplyanceHttpClient::class);
        $mockResponse = new UnifyResponse();
        $mockResponse->setStatus('success');
        
        $mockHttpClient->expects($this->once())
            ->method('sendUnifyRequest')
            ->willReturnCallback(function (UnifyRequest $request) use ($mockResponse) {
                // Verify the request was properly constructed
                $this->assertSame(DocumentType::TAX_INVOICE, $request->getDocumentType());
                $this->assertSame('SA', $request->getCountry());
                $this->assertSame(Operation::SINGLE, $request->getOperation());
                $this->assertSame(Mode::DOCUMENTS, $request->getMode());
                $this->assertSame(Purpose::MAPPING, $request->getPurpose());
                $this->assertSame(['invoice_number' => 'INV-001'], $request->getPayload());
                
                return $mockResponse;
            });
        
        // Configure the SDK with a source
        $source = new Source('test-source', 'FIRST_PARTY', 'Test Source');
        $config = new SDKConfig('test-api-key', 'sandbox', [$source]);
        ComplyanceSDK::configure($config);
        
        // Set the mock HTTP client
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($mockHttpClient);
        
        // Create a mapping
        $response = ComplyanceSDK::createMapping('SA', ['invoice_number' => 'INV-001']);
        
        // Assert the response
        $this->assertSame('success', $response->getStatus());
    }

    public function testErrorHandling()
    {
        // Create a mock HTTP client that throws an exception
        $mockHttpClient = $this->createMock(ComplyanceHttpClient::class);
        $mockHttpClient->expects($this->once())
            ->method('sendUnifyRequest')
            ->willThrowException(new ApiException(
                'Invalid request',
                400,
                ['error' => 'Bad Request'],
                'VALIDATION_ERROR',
                ['field' => 'invoice_number']
            ));
        
        // Configure the SDK
        $config = new SDKConfig('test-api-key', 'sandbox');
        ComplyanceSDK::configure($config);
        
        // Set the mock HTTP client
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($mockHttpClient);
        
        // Create a request
        $request = new UnifyRequest();
        $request->setDocumentType(DocumentType::TAX_INVOICE);
        $request->setCountry('SA');
        $request->setPayload([]);
        
        // Send the request and expect an exception
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid request');
        
        ComplyanceSDK::pushToUnify($request);
    }

    public function testCustomLogger()
    {
        // Create a mock logger
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Sending request to Unify API'),
                $this->arrayHasKey('document_type')
            );
        
        // Create a mock HTTP client
        $mockHttpClient = $this->createMock(ComplyanceHttpClient::class);
        $mockResponse = new UnifyResponse();
        $mockResponse->setStatus('success');
        
        $mockHttpClient->expects($this->once())
            ->method('sendUnifyRequest')
            ->willReturn($mockResponse);
        
        // Configure the SDK with the mock logger
        $config = new SDKConfig('test-api-key', 'sandbox');
        ComplyanceSDK::configure($config, $mockLogger);
        
        // Set the mock HTTP client
        $reflectionClass = new \ReflectionClass(ComplyanceSDK::class);
        $httpClientProperty = $reflectionClass->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($mockHttpClient);
        
        // Create a request
        $request = new UnifyRequest();
        $request->setDocumentType(DocumentType::TAX_INVOICE);
        $request->setCountry('SA');
        $request->setPayload([]);
        
        // Send the request
        ComplyanceSDK::pushToUnify($request);
    }
}