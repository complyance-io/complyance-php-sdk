<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ComplyanceSDK\GETSUnifySDK;
use ComplyanceSDK\Models\SDKConfig;
use ComplyanceSDK\Models\Source;
use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\Environment;
use ComplyanceSDK\Enums\LogicalDocType;
use ComplyanceSDK\Enums\Operation;
use ComplyanceSDK\Enums\Mode;
use ComplyanceSDK\Enums\Purpose;
use ComplyanceSDK\Exceptions\SDKException;

final class RetrievalStatusTest extends TestCase
{
    protected function setUp(): void
    {
        GETSUnifySDK::configure(new SDKConfig(
            'test-key',
            Environment::from(Environment::SANDBOX),
            []
        ));
    }

    public function testGetDocumentStatusRequiresDocumentId(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::getDocumentStatus('   ');
    }

    public function testGetSubmissionStatusIsDeprecated(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::getSubmissionStatus('sub-123');
    }

    public function testGetStatusAliasIsDeprecated(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::getStatus('sub-123');
    }

    public function testSubmitPayloadRequiresPayload(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::submitPayload(
            '   ',
            Country::from(Country::SA),
            DocumentType::from(DocumentType::TAX_INVOICE),
            'src:1'
        );
    }

    public function testSubmitPayloadRejectsUnknownSource(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::submitPayload(
            '{"invoice":"ok"}',
            Country::from(Country::SA),
            DocumentType::from(DocumentType::TAX_INVOICE),
            'src:1'
        );
    }

    public function testSubmitPayloadReturnsMockedSuccessWithValidSource(): void
    {
        GETSUnifySDK::configure(new SDKConfig(
            'test-key',
            Environment::from(Environment::SANDBOX),
            [new Source('src', '1')]
        ));

        $response = GETSUnifySDK::submitPayload(
            '{"invoice":"ok"}',
            Country::from(Country::SA),
            DocumentType::from(DocumentType::TAX_INVOICE),
            'src:1'
        );

        $this->assertIsArray($response);
        $this->assertSame('success', $response['status'] ?? null);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('submissionId', $response['data']);
    }

    public function testPushToUnifyFromJsonRejectsEmptyPayload(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::pushToUnifyFromJson(
            'src',
            '1',
            LogicalDocType::from(LogicalDocType::TAX_INVOICE),
            Country::from(Country::SA),
            Operation::from(Operation::SINGLE),
            Mode::from(Mode::DOCUMENTS),
            Purpose::from(Purpose::INVOICING),
            '   '
        );
    }

    public function testPushToUnifyFromJsonRejectsMalformedPayload(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::pushToUnifyFromJson(
            'src',
            '1',
            LogicalDocType::from(LogicalDocType::TAX_INVOICE),
            Country::from(Country::SA),
            Operation::from(Operation::SINGLE),
            Mode::from(Mode::DOCUMENTS),
            Purpose::from(Purpose::INVOICING),
            '{"broken":'
        );
    }

    public function testPushToUnifyRejectsBlankSourceForInvoicingPurpose(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::pushToUnify(
            ' ',
            '1',
            LogicalDocType::from(LogicalDocType::TAX_INVOICE),
            Country::from(Country::SA),
            Operation::from(Operation::SINGLE),
            Mode::from(Mode::DOCUMENTS),
            Purpose::from(Purpose::INVOICING),
            ['invoiceNumber' => 'INV-001']
        );
    }

    public function testVerifyWebhookSignatureAcceptsValidSignature(): void
    {
        $payload = '{"hello":"world"}';
        $secret = 'test-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue(
            GETSUnifySDK::verifyWebhookSignature($payload, $signature, $secret, 'sha256')
        );
    }

    public function testVerifyWebhookSignatureRejectsUnsupportedAlgorithm(): void
    {
        $this->expectException(SDKException::class);
        GETSUnifySDK::verifyWebhookSignature('{}', '00', 'secret', 'md5');
    }
}
