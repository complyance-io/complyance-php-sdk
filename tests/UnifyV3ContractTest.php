<?php

declare(strict_types=1);

use ComplyanceSDK\APIClient;
use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\Environment;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Purpose;
use ComplyanceSDK\Models\PersistentQueueManager;
use ComplyanceSDK\Models\SDKConfig;
use ComplyanceSDK\Models\Source;
use ComplyanceSDK\UnifyRequest;
use ComplyanceSDK\UnifyLegacyRequestSerializer;
use ComplyanceSDK\UnifyBulkResponse;
use ComplyanceSDK\UnifyV3RequestSerializer;
use PHPUnit\Framework\TestCase;

final class CapturingV3APIClient extends APIClient
{
    public $capturedUrl;
    public $capturedBody;
    public $capturedHeaders;
    public $responseBody = '{"status":"success","code":"OK","message":"Accepted for processing","requestId":"request-1","timestamp":"2026-09-03T10:00:00.000Z","data":{"summary":{"total":1,"success":1,"failed":0},"results":[{"status":"success","country":"AE","sourceid":"source-1","payloadId":"payload-1","documentId":"doc-1","documentNumber":"INV-001","templateId":"template-1","validation":{"success":true,"errors":[]},"submission":{"success":true,"state":"submitted","errors":[],"submittedAt":"2026-09-03T10:00:00.000Z"},"errors":[]}]},"errors":[]}';
    public $responseStatus = 200;

    protected function executeHttpRequest(string $url, string $body, array $headers): array
    {
        $this->capturedUrl = $url;
        $this->capturedBody = $body;
        $this->capturedHeaders = $headers;

        return [
            'body' => $this->responseBody,
            'status' => $this->responseStatus,
            'error' => '',
        ];
    }
}

final class UnifyV3ContractTest extends TestCase
{
    public function testClientSendsCanonicalV3Request(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL),
            null,
            null,
            true
        );
        $payload = [
            'documentType' => ['base' => 'tax_invoice', 'modifiers' => []],
            'invoice_data' => ['document_number' => 'INV-001'],
        ];
        $request = $this->buildRequest($payload);
        $request->setNewApi(true);
        $client->responseBody = '{"documentId":"doc-1","message":"Your invoice was validated."}';

        $response = $client->sendUnifyRequest($request);

        $this->assertSame(
            '{"documentId":"doc-1","message":"Your invoice was validated."}',
            $response
        );
        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $this->assertContains('new-api: true', $client->capturedHeaders);
        $this->assertContains('Authorization: Bearer configured-api-key', $client->capturedHeaders);
        $this->assertContains('Content-Type: application/json', $client->capturedHeaders);
        $this->assertContains('new-api: true', $client->capturedHeaders);
        $this->assertNotContains('Authorization: Bearer stale-queued-key', $client->capturedHeaders);

        $body = json_decode($client->capturedBody, true);
        $this->assertSame([
            'debug' => true,
            'country' => 'AE',
            'environment' => 'sandbox',
            'purpose' => 'invoicing',
            'ingestionMethod' => 'sdk',
            'source' => 'AES:1',
            'documentType' => [
                'base' => 'tax_invoice',
                'modifiers' => [],
            ],
            'payload' => $payload,
        ], $body);

        foreach (['apiKey', 'operation', 'mode', 'requestId', 'timestamp', 'env', 'destinations', 'correlationId'] as $legacyKey) {
            $this->assertArrayNotHasKey($legacyKey, $body);
        }
    }

    public function testMissingSelectorSendsLegacyRequestToV3Endpoint(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $request = $this->buildRequest(['invoice_data' => ['document_number' => 'INV-LEGACY']]);

        $response = json_decode($client->sendUnifyRequest($request), true);

        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $body = json_decode($client->capturedBody, true);
        $this->assertSame('submit', $body['action']);
        $this->assertSame('invoicing', $body['purpose']);
        $this->assertSame('sandbox', $body['env']);
        $this->assertSame('AE', $body['defaults']['country']);
        $this->assertSame(
            ['base' => 'tax_invoice', 'modifiers' => []],
            $body['defaults']['logicalDocumentType']
        );
        $this->assertSame(['name' => 'AES', 'version' => '1'], $body['defaults']['source']);
        $this->assertSame(
            ['invoice_data' => ['document_number' => 'INV-LEGACY']],
            $body['invoices'][0]['payload']
        );
        $this->assertArrayNotHasKey('apiKey', $body);
        $this->assertArrayNotHasKey('requestId', $body);
        $this->assertArrayNotHasKey('operation', $body);
        $this->assertArrayNotHasKey('mode', $body);
        $this->assertArrayNotHasKey('environment', $body);
        $this->assertArrayNotHasKey('ingestionMethod', $body);
        $this->assertArrayNotHasKey('new-api', $body);
        $this->assertNotContains('new-api: true', $client->capturedHeaders);
        $this->assertNotContains('new-api: false', $client->capturedHeaders);
        $this->assertSame('doc-1', $response['data']['document']['documentId']);
        $this->assertSame('SUBMITTED', $response['data']['document']['status']);
        $this->assertSame(true, $response['data']['submission']['success']);
        $this->assertSame(
            [
                'documentId' => 'doc-1',
                'state' => 'submitted',
                'country' => 'AE',
                'environment' => 'sandbox',
            ],
            $response['data']['submission']['governmentResponse']
        );
        $this->assertSame(
            '2026-09-03T10:00:00.000Z',
            $response['data']['submission']['submittedAt']
        );
        $this->assertArrayNotHasKey('state', $response['data']['submission']);
        $this->assertArrayNotHasKey('errors', $response['data']['submission']);
        $this->assertSame('request-1', $response['metadata']['requestId']);
        $this->assertArrayNotHasKey('results', $response['data']);
    }

    public function testFalseSelectorSendsLegacyRequestAndExplicitQuery(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $request = $this->buildRequest([]);
        $request->setNewApi(false);

        $response = json_decode($client->sendUnifyRequest($request), true);

        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $this->assertContains('new-api: false', $client->capturedHeaders);
        $body = json_decode($client->capturedBody, true);
        $this->assertSame('submit', $body['action']);
        $this->assertArrayHasKey('env', $body);
        $this->assertArrayHasKey('defaults', $body);
        $this->assertArrayHasKey('invoices', $body);
        $this->assertArrayNotHasKey('operation', $body);
        $this->assertArrayNotHasKey('new-api', $body);
        $this->assertSame('doc-1', $response['data']['document']['documentId']);
        $this->assertArrayNotHasKey('results', $response['data']);
    }

    public function testTypedLegacySerializerPreservesExplicitDestinations(): void
    {
        $request = $this->buildRequest(['header' => ['documentNumber' => 'INV-1']]);
        $request->setDestinations([[
            'type' => 'TAX_AUTHORITY',
            'details' => ['authority' => 'FTA'],
        ]]);

        $body = UnifyLegacyRequestSerializer::serialize($request);

        $this->assertSame(
            $request->getDestinations(),
            $body['defaults']['destinations']
        );
        $this->assertArrayNotHasKey('options', $body);
    }

    /**
     * @dataProvider invalidTypedLegacyRequestProvider
     */
    public function testTypedLegacySerializerRejectsMissingOrMalformedAuthoritativeFields(
        callable $mutate
    ): void {
        $request = $this->buildRequest(['header' => ['documentNumber' => 'INV-1']]);
        $mutate($request);

        $this->expectException(InvalidArgumentException::class);
        UnifyLegacyRequestSerializer::serialize($request);
    }

    public function invalidTypedLegacyRequestProvider(): array
    {
        return [
            'country is missing' => [static function (UnifyRequest $request): void {
                $request->country = null;
            }],
            'environment is unsupported' => [static function (UnifyRequest $request): void {
                $request->setEnv('local');
            }],
            'source version is missing' => [static function (UnifyRequest $request): void {
                $request->setSource(['name' => 'AES', 'version' => '']);
            }],
            'document type base is missing' => [static function (UnifyRequest $request): void {
                $request->setDocumentTypeV2(['modifiers' => []]);
            }],
            'modifiers are not an array' => [static function (UnifyRequest $request): void {
                $request->setDocumentTypeV2(['base' => 'tax_invoice', 'modifiers' => 'summary']);
            }],
            'payload is a list' => [static function (UnifyRequest $request): void {
                $request->setPayload([['documentNumber' => 'INV-1']]);
            }],
        ];
    }

    public function testTypedLegacyValidationFailureIsProjectedToHistoricalShape(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $validationFailure = [
            'status' => 'success',
            'code' => 'OK',
            'message' => 'Accepted for processing',
            'data' => [
                'summary' => ['total' => 1, 'success' => 0, 'failed' => 1],
                'results' => [[
                    'status' => 'failed',
                    'validation' => [
                        'success' => false,
                        'errors' => [['code' => 'AE-MANDATORY-012']],
                    ],
                    'errors' => [],
                ]],
            ],
            'errors' => [],
        ];
        $client->responseBody = json_encode($validationFailure);

        $raw = $client->sendUnifyRequest($this->buildRequest([]));

        $projected = json_decode($raw, true);
        $this->assertSame('success', $projected['status']);
        $this->assertSame('Validation rejected', $projected['message']);
        $this->assertFalse($projected['data']['validation']['success']);
        $this->assertSame(
            'AE-MANDATORY-012',
            $projected['data']['validation']['errors'][0]['code']
        );
        $this->assertArrayNotHasKey('results', $projected['data']);
    }

    public function testTypedLegacyTopLevelErrorIsProjectedToHistoricalShape(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $topLevelError = [
            'status' => 'error',
            'code' => 'VALIDATION_ERROR',
            'message' => 'Request validation failed',
            'data' => [
                'summary' => ['total' => 0, 'success' => 0, 'failed' => 0],
                'results' => [],
            ],
            'errors' => [['code' => 'INVALID_REQUEST']],
        ];
        $client->responseBody = json_encode($topLevelError);

        $raw = $client->sendUnifyRequest($this->buildRequest([]));

        $this->assertSame([
            'status' => 'error',
            'message' => 'Request validation failed',
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Request validation failed',
            ],
        ], json_decode($raw, true));
    }

    public function testTypedLegacyProcessingFailureIsProjectedToHistoricalError(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $client->responseBody = json_encode([
            'status' => 'success',
            'code' => 'OK',
            'message' => 'Accepted for processing',
            'data' => [
                'summary' => ['total' => 1, 'success' => 0, 'failed' => 1],
                'results' => [[
                    'status' => 'failed',
                    'errors' => [[
                        'code' => 'SOURCE_NOT_FOUND',
                        'message' => 'Source mapping was not found.',
                    ]],
                ]],
            ],
            'errors' => [],
        ]);

        $response = json_decode(
            $client->sendUnifyRequest($this->buildRequest([])),
            true
        );

        $this->assertSame('error', $response['status']);
        $this->assertSame('Source mapping was not found.', $response['message']);
        $this->assertSame('SOURCE_NOT_FOUND', $response['error']['code']);
        $this->assertArrayNotHasKey('data', $response);
    }

    public function testTypedLegacyHttpErrorExposesRawResponseBodyInException(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $client->responseStatus = 422;
        $client->responseBody = '{"status":"error","code":"VALIDATION_ERROR"}';

        try {
            $client->sendUnifyRequest($this->buildRequest([]));
            $this->fail('Expected SDKException for HTTP 422.');
        } catch (SDKException $exception) {
            $this->assertSame(
                $client->responseBody,
                $exception->getErrorDetail()->getContextValue('responseBody')
            );
            $this->assertSame(
                422,
                $exception->getErrorDetail()->getContextValue('httpStatus')
            );
        }
    }

    public function testRawLegacyBatchRequestIsSentWithoutTransformation(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $legacy = [
            'action' => 'submit',
            'purpose' => 'invoicing',
            'env' => 'sandbox',
            'defaults' => [
                'country' => 'AE',
                'logicalDocumentType' => [
                    'base' => 'tax_invoice',
                    'modifiers' => [''],
                    'variant' => '',
                ],
                'source' => ['name' => 'newage-source-002', 'version' => '1.0'],
            ],
            'invoices' => [['payload' => ['header' => ['documentNumber' => '0100202303']]]],
            'options' => ['continueOnError' => true, 'submitToGovernment' => true],
        ];

        $client->sendRawUnifyRequest($legacy);

        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $this->assertSame($legacy, json_decode($client->capturedBody, true));
        $this->assertNotContains('new-api: true', $client->capturedHeaders);
        $this->assertNotContains('new-api: false', $client->capturedHeaders);
    }

    public function testRawLegacyBatchPreservesExactContractAndRawResponse(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $legacy = [
            'action' => 'submit',
            'purpose' => 'invoicing',
            'env' => 'sandbox',
            'defaults' => [
                'country' => 'AE',
                'logicalDocumentType' => [
                    'base' => 'tax_invoice',
                    'modifiers' => [],
                    'variant' => '',
                ],
                'source' => ['name' => 'AES', 'version' => '1'],
            ],
            'invoices' => [[
                'payload' => [
                    'delivery' => [
                        'deliveryLocation' => [
                            'addressLine1' => 'Corniche Road',
                            'city' => 'Abu Dhabi',
                            'stateOrProvince' => 'AUH',
                            'country' => 'AE',
                        ],
                    ],
                    'header' => [
                        'documentId' => 'INV-AE-SUM-1001',
                        'documentNumber' => 'TEST-LEGACY-2210',
                    ],
                    'lineItems' => [[
                        'id' => '1',
                        'customFields' => ['ae_vatLineAmountInAED' => 112.5],
                    ]],
                    'totals' => [
                        'amountDue' => 2362.5,
                        'totalTaxAmount' => 112.5,
                    ],
                ],
            ]],
            'options' => ['continueOnError' => true, 'submitToGovernment' => true],
        ];
        $legacyResponse = [
            'status' => 'success',
            'code' => 'OK',
            'message' => 'Accepted for processing',
            'data' => [
                'summary' => ['total' => 1, 'success' => 1, 'failed' => 0],
                'results' => [[
                    'status' => 'success',
                    'documentId' => 'legacy-document-1',
                    'errors' => [],
                ]],
            ],
            'errors' => [],
        ];
        $client->responseBody = json_encode($legacyResponse);

        $rawResponse = $client->sendRawUnifyRequest($legacy, false);

        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $this->assertSame($legacy, json_decode($client->capturedBody, true));
        $this->assertContains('new-api: false', $client->capturedHeaders);
        $this->assertSame($client->responseBody, $rawResponse);
        $this->assertSame($legacyResponse, json_decode($rawResponse, true));
    }

    public function testRawRevampedRequestUsesHeaderAndPreservesBody(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $revamped = [
            'country' => 'AE',
            'environment' => 'sandbox',
            'purpose' => 'invoicing',
            'source' => 'AES:1',
            'documentType' => ['base' => 'tax_invoice', 'modifiers' => []],
            'payload' => ['invoice_data' => ['document_number' => 'INV-AE-SUM-2303']],
        ];

        $client->sendRawUnifyRequest($revamped, true);

        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $this->assertSame($revamped, json_decode($client->capturedBody, true));
        $this->assertContains('new-api: true', $client->capturedHeaders);
    }

    public function testRevampedBulkReturnsOrderedSuccessResults(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $client->responseBody = json_encode([
            'summary' => ['total' => 2, 'succeeded' => 2, 'failed' => 0],
            'results' => [
                [
                    'index' => 0,
                    'success' => true,
                    'result' => [
                        'documentId' => 'document-1',
                        'message' => 'First invoice validated.',
                        'Base64XML' => 'PEludm9pY2UgLz4=',
                    ],
                ],
                [
                    'index' => 1,
                    'success' => true,
                    'result' => [
                        'documentId' => 'document-2',
                        'message' => 'Second invoice validated.',
                        'Base64XML' => 'PEludm9pY2UgLz4=',
                    ],
                ],
            ],
        ]);

        $response = $client->sendRevampedBulkUnifyRequest([
            $this->buildRequest(['invoice_data' => ['document_number' => 'INV-001']]),
            $this->buildRequest(['invoice_data' => ['document_number' => 'INV-002']]),
        ]);

        $this->assertInstanceOf(UnifyBulkResponse::class, $response);
        $this->assertSame(2, $response->getTotal());
        $this->assertSame(2, $response->getSucceeded());
        $this->assertSame(0, $response->getFailed());
        $this->assertSame(0, $response->getResults()[0]->getIndex());
        $this->assertSame('document-1', $response->getResults()[0]->getResult()['documentId']);
        $this->assertSame(1, $response->getResults()[1]->getIndex());
        $this->assertSame('document-2', $response->getResults()[1]->getResult()['documentId']);
        $this->assertContains('new-api: true', $client->capturedHeaders);
        $this->assertSame(
            ['INV-001', 'INV-002'],
            array_map(
                static function (array $invoice): string {
                    return $invoice['payload']['invoice_data']['document_number'];
                },
                json_decode($client->capturedBody, true)['invoices']
            )
        );
    }

    public function testRevampedBulkExposesValidationFailure(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $validationErrors = [[
            'code' => 'IBR-015',
            'message' => 'Amount due is required.',
            'getsPath' => 'totals.amountDue',
        ]];
        $client->responseBody = json_encode([
            'summary' => ['total' => 1, 'succeeded' => 0, 'failed' => 1],
            'results' => [[
                'index' => 0,
                'success' => false,
                'result' => [
                    'documentId' => 'document-invalid',
                    'message' => 'Invoice validation failed.',
                    'errors' => $validationErrors,
                ],
            ]],
        ]);

        $response = $client->sendRevampedBulkUnifyRequest([$this->buildRequest([])]);
        $result = $response->getResults()[0];

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isValidationFailure());
        $this->assertFalse($result->isProcessingFailure());
        $this->assertSame($validationErrors, $result->getResult()['errors']);
        $this->assertNull($result->getError());
    }

    public function testRevampedBulkExposesProcessingFailure(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $processingError = [
            'code' => 'not_found',
            'message' => 'Source mapping was not found.',
        ];
        $client->responseBody = json_encode([
            'summary' => ['total' => 1, 'succeeded' => 0, 'failed' => 1],
            'results' => [[
                'index' => 0,
                'success' => false,
                'error' => $processingError,
            ]],
        ]);

        $response = $client->sendRevampedBulkUnifyRequest([$this->buildRequest([])]);
        $result = $response->getResults()[0];

        $this->assertFalse($result->isSuccess());
        $this->assertFalse($result->isValidationFailure());
        $this->assertTrue($result->isProcessingFailure());
        $this->assertNull($result->getResult());
        $this->assertSame($processingError, $result->getError());
    }

    public function testRevampedBulkRejectsOutOfOrderResults(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );
        $client->responseBody = json_encode([
            'summary' => ['total' => 2, 'succeeded' => 2, 'failed' => 0],
            'results' => [
                ['index' => 1, 'success' => true, 'result' => ['documentId' => 'document-2']],
                ['index' => 0, 'success' => true, 'result' => ['documentId' => 'document-1']],
            ],
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('request order');
        $client->sendRevampedBulkUnifyRequest([
            $this->buildRequest(['invoice_data' => ['document_number' => 'INV-001']]),
            $this->buildRequest(['invoice_data' => ['document_number' => 'INV-002']]),
        ]);
    }

    public function testSerializerNormalizesProductionAndLegacyDocumentType(): void
    {
        $request = $this->buildRequest(['invoice_data' => []]);
        $request->setDocumentTypeV2(null);
        $request->setDocumentTypeString('TAX_INVOICE');
        $request->setSource([
            'name' => 'erp:regional',
            'version' => '1.0',
            'identity' => 'erp:regional:1.0',
        ]);

        $body = UnifyV3RequestSerializer::serialize(
            $request,
            Environment::from(Environment::PRODUCTION)
        );

        $this->assertFalse($body['debug']);
        $this->assertSame('production', $body['environment']);
        $this->assertSame('erp:regional:1.0', $body['source']);
        $this->assertSame([
            'base' => 'tax_invoice',
            'modifiers' => [],
        ], $body['documentType']);
    }

    public function testSerializerOmitsEmptySourceForMapping(): void
    {
        $request = $this->buildRequest([]);
        $request->setSource([
            'name' => '',
            'version' => '',
            'identity' => ':',
        ]);
        $request->setPurpose('mapping');

        $body = UnifyV3RequestSerializer::serialize(
            $request,
            Environment::from(Environment::SANDBOX)
        );

        $this->assertArrayNotHasKey('source', $body);
        $this->assertSame('mapping', $body['purpose']);
        $this->assertInstanceOf(stdClass::class, $body['payload']);
        $this->assertStringContainsString('"payload":{}', json_encode($body));
    }

    public function testSerializerRejectsListPayload(): void
    {
        $request = $this->buildRequest([['document_number' => 'INV-001']]);

        $this->expectException(InvalidArgumentException::class);
        UnifyV3RequestSerializer::serialize(
            $request,
            Environment::from(Environment::SANDBOX)
        );
    }

    public function testLegacySendPayloadUnderstandsV3Success(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::SANDBOX)
        );

        $response = $client->sendPayload(
            '{"invoice_data":{"document_number":"INV-001"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::TAX_INVOICE)
        );

        $this->assertSame('success', $response->getStatus());
        $this->assertSame('doc-1', $response->getSubmissionId());
        $body = json_decode($client->capturedBody, true);
        $this->assertSame('tax_invoice', $body['defaults']['logicalDocumentType']['base']);
        $this->assertSame([], $body['defaults']['logicalDocumentType']['modifiers']);
    }

    public function testLegacySendPayloadMapsSimplifiedInvoiceBase(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::SANDBOX)
        );

        $client->sendPayload(
            '{"invoice_data":{"document_number":"INV-002"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::SIMPLIFIED_TAX_INVOICE)
        );

        $body = json_decode($client->capturedBody, true);
        $this->assertSame('simplified_invoice', $body['defaults']['logicalDocumentType']['base']);
    }

    public function testLegacySendPayloadUnderstandsNestedBatchSuccess(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::SANDBOX)
        );
        $client->responseBody = json_encode([
            'status' => 'success',
            'message' => 'Accepted for processing',
            'data' => [
                'summary' => ['total' => 1, 'success' => 1, 'failed' => 0],
                'results' => [[
                    'status' => 'success',
                    'payloadId' => 'payload-legacy-1',
                    'documentId' => 'document-legacy-1',
                    'errors' => [],
                ]],
            ],
            'errors' => [],
        ]);

        $response = $client->sendPayload(
            '{"header":{"documentNumber":"INV-005"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::TAX_INVOICE)
        );

        $this->assertSame('success', $response->getStatus());
        $this->assertSame('document-legacy-1', $response->getSubmissionId());
        $this->assertSame('Accepted for processing', $response->getMessage());
    }

    public function testLegacySendPayloadExposesNestedValidationFailure(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::SANDBOX)
        );
        $client->responseBody = json_encode([
            'status' => 'success',
            'message' => 'Accepted for processing',
            'data' => [
                'summary' => ['total' => 1, 'success' => 0, 'failed' => 1],
                'results' => [[
                    'status' => 'failed',
                    'documentId' => 'document-failed-1',
                    'validation' => [
                        'success' => false,
                        'errors' => [[
                            'code' => 'AE-MANDATORY-012',
                            'message' => 'Due Date is mandatory.',
                        ]],
                    ],
                    'errors' => [],
                ]],
            ],
            'errors' => [],
        ]);

        $response = $client->sendPayload(
            '{"header":{"documentNumber":"INV-006"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::TAX_INVOICE)
        );

        $this->assertSame('failed', $response->getStatus());
        $this->assertSame('document-failed-1', $response->getSubmissionId());
        $this->assertSame('Due Date is mandatory.', $response->getMessage());
    }

    public function testLegacySendPayloadMapsSelfBilledModifier(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::SANDBOX)
        );

        $client->sendPayload(
            '{"invoice_data":{"document_number":"INV-003"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::SELF_BILLED_INVOICE)
        );

        $body = json_decode($client->capturedBody, true);
        $this->assertSame('tax_invoice', $body['defaults']['logicalDocumentType']['base']);
        $this->assertSame(['self_billed'], $body['defaults']['logicalDocumentType']['modifiers']);
    }

    public function testLegacySendPayloadRejectsAmbiguousDocumentType(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );

        $this->expectException(InvalidArgumentException::class);
        $client->sendPayload(
            '{"invoice_data":{"document_number":"INV-004"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::CORRECTION_DOCUMENT)
        );
    }

    public function testDebugConfigurationRoundTrips(): void
    {
        $config = new SDKConfig(
            'test-key',
            Environment::from(Environment::SANDBOX),
            [],
            null,
            true,
            null,
            true
        );

        $this->assertTrue($config->isDebug());
        $this->assertTrue($config->toArray()['debug']);
        $this->assertTrue(SDKConfig::fromArray($config->toArray())->isDebug());
    }

    public function testQueueRebuildsV2DocumentTypeWithoutStringCasting(): void
    {
        $reflection = new ReflectionClass(PersistentQueueManager::class);
        $queueManager = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildUnifyRequestFromArray');
        $method->setAccessible(true);

        $request = $method->invoke($queueManager, $this->buildRequest([])->toArray());
        $body = UnifyV3RequestSerializer::serialize(
            $request,
            Environment::from(Environment::SANDBOX)
        );

        $this->assertSame('tax_invoice', $body['documentType']['base']);
        $this->assertSame([], $body['documentType']['modifiers']);
    }

    public function testQueueRecognizesV3SuccessResponses(): void
    {
        $reflection = new ReflectionClass(PersistentQueueManager::class);
        $queueManager = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isSuccessfulResponse');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($queueManager, ['documentId' => 'doc-1']));
        $this->assertTrue($method->invoke($queueManager, ['payloadId' => 'payload-1']));
        $this->assertTrue($method->invoke($queueManager, ['status' => 'success']));
        $this->assertFalse($method->invoke($queueManager, ['message' => 'failed']));
    }

    private function buildRequest(array $payload): UnifyRequest
    {
        return UnifyRequest::builder()
            ->source([
                'name' => 'AES',
                'version' => '1',
                'type' => 'FIRST_PARTY',
                'id' => 'AES:1',
                'identity' => 'AES:1',
            ])
            ->documentType(DocumentType::from(DocumentType::TAX_INVOICE))
            ->documentTypeString('tax_invoice')
            ->documentTypeV2([
                'base' => 'tax_invoice',
                'modifiers' => [],
            ])
            ->country(Country::from(Country::AE)->getCode())
            ->operation(Operation::from(Operation::SINGLE))
            ->mode(Mode::from(Mode::DOCUMENTS))
            ->purpose(Purpose::from(Purpose::INVOICING))
            ->payload($payload)
            ->destinations([])
            ->apiKey('stale-queued-key')
            ->requestId('req-1')
            ->timestamp('2026-07-10T00:00:00+00:00')
            ->env('sandbox')
            ->correlationId('corr-1')
            ->build();
    }
}
