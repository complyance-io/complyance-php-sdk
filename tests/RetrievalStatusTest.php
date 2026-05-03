<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use ComplyanceSDK\GETSUnifySDK;
use ComplyanceSDK\Models\SDKConfig;
use ComplyanceSDK\Models\Source;
use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Enums\Environment;
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
}
