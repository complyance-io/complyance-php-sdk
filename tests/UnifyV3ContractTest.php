<?php

declare(strict_types=1);

use ComplyanceSDK\APIClient;
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
use ComplyanceSDK\UnifyV3RequestSerializer;
use PHPUnit\Framework\TestCase;

final class CapturingV3APIClient extends APIClient
{
    public $capturedUrl;
    public $capturedBody;
    public $capturedHeaders;

    protected function executeHttpRequest(string $url, string $body, array $headers): array
    {
        $this->capturedUrl = $url;
        $this->capturedBody = $body;
        $this->capturedHeaders = $headers;

        return [
            'body' => '{"documentId":"doc-1","message":"Your invoice was validated."}',
            'status' => 200,
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

        $response = $client->sendUnifyRequest($request);

        $this->assertSame(
            '{"documentId":"doc-1","message":"Your invoice was validated."}',
            $response
        );
        $this->assertSame('http://127.0.0.1:4000/api/v3/unify', $client->capturedUrl);
        $this->assertContains('Authorization: Bearer configured-api-key', $client->capturedHeaders);
        $this->assertContains('Content-Type: application/json', $client->capturedHeaders);
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
            Environment::from(Environment::LOCAL)
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
        $this->assertSame('tax_invoice', $body['documentType']['base']);
        $this->assertSame([], $body['documentType']['modifiers']);
    }

    public function testLegacySendPayloadMapsSimplifiedInvoiceBase(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );

        $client->sendPayload(
            '{"invoice_data":{"document_number":"INV-002"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::SIMPLIFIED_TAX_INVOICE)
        );

        $body = json_decode($client->capturedBody, true);
        $this->assertSame('simplified_invoice', $body['documentType']['base']);
    }

    public function testLegacySendPayloadMapsSelfBilledModifier(): void
    {
        $client = new CapturingV3APIClient(
            'configured-api-key',
            Environment::from(Environment::LOCAL)
        );

        $client->sendPayload(
            '{"invoice_data":{"document_number":"INV-003"}}',
            new Source('AES', '1'),
            Country::from(Country::AE),
            DocumentType::from(DocumentType::SELF_BILLED_INVOICE)
        );

        $body = json_decode($client->capturedBody, true);
        $this->assertSame('tax_invoice', $body['documentType']['base']);
        $this->assertSame(['self_billed'], $body['documentType']['modifiers']);
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
