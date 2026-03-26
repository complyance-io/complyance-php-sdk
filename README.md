# Complyance PHP SDK

The official PHP SDK for the Complyance Unified e-invoicing platform.

## Installation

You can install the SDK via Composer:

```bash
composer require io.complyance/unify-sdk
```

## Basic Usage

```php
<?php

require 'vendor/autoload.php';

use Complyance\SDK\ComplyanceSDK;
use Complyance\SDK\Config\SDKConfig;
use Complyance\SDK\Models\Source;
use Complyance\SDK\Models\UnifyRequest;

// Configure the SDK
$source = Source::firstParty('my-erp', 'My ERP System');
$config = new SDKConfig('your-api-key', 'sandbox', [$source]);

ComplyanceSDK::configure($config);

// Create a request
$request = new UnifyRequest();
$request->setSource($source);
$request->setDocumentType('TAX_INVOICE');
$request->setCountry('SA');
$request->setPayload([
    'invoice_number' => 'INV-001',
    'issue_date' => '2023-01-01',
    // Add more invoice data here
]);

// Send the request
try {
    $response = ComplyanceSDK::pushToUnify($request);
    echo "Success: " . $response->getMessage() . "\n";
    var_dump($response->getData());
} catch (\Complyance\SDK\Exceptions\SDKException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getErrorCode() . "\n";
    var_dump($e->getContext());
}
```

## Configuration

### From Array

```php
$config = SDKConfig::fromArray([
    'api_key' => 'your-api-key',
    'environment' => 'sandbox',
    'sources' => [
        [
            'id' => 'my-erp',
            'type' => 'FIRST_PARTY',
            'name' => 'My ERP System',
            'version' => '1.0',
        ],
    ],
    'retry_config' => [
        'max_attempts' => 3,
        'base_delay' => 500,
        'max_delay' => 5000,
    ],
]);
```

### From Environment Variables

```php
// Set environment variables
putenv('COMPLYANCE_API_KEY=your-api-key');
putenv('COMPLYANCE_ENVIRONMENT=sandbox');
putenv('COMPLYANCE_RETRY_MAX_ATTEMPTS=3');

// Load configuration from environment
$config = SDKConfig::fromEnv();
```

## Framework Integration

### Laravel

The SDK provides a Laravel service provider and facade for easy integration.

1. Register the service provider in `config/app.php`:

```php
'providers' => [
    // ...
    Complyance\SDK\Laravel\ComplyanceServiceProvider::class,
],

'aliases' => [
    // ...
    'Complyance' => Complyance\SDK\Laravel\ComplyanceFacade::class,
],
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --provider="Complyance\SDK\Laravel\ComplyanceServiceProvider" --tag="complyance-config"
```

3. Configure the SDK in `.env`:

```
COMPLYANCE_API_KEY=your-api-key
COMPLYANCE_ENVIRONMENT=sandbox
```

4. Use the facade in your code:

```php
use Complyance\SDK\Laravel\ComplyanceFacade as Complyance;

// Create a request
$response = Complyance::submitInvoice('SA', [
    'invoice_number' => 'INV-001',
    'issue_date' => '2023-01-01',
    // Add more invoice data here
]);
```

### Symfony

The SDK provides a Symfony bundle for dependency injection.

1. Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Complyance\SDK\Symfony\ComplyanceBundle::class => ['all' => true],
];
```

2. Create a configuration file in `config/packages/complyance.yaml`:

```yaml
complyance:
  api_key: '%env(COMPLYANCE_API_KEY)%'
  environment: '%env(default:sandbox:COMPLYANCE_ENVIRONMENT)%'
  register_event_subscriber: true
  sources:
    - id: my-erp
      type: FIRST_PARTY
      name: My ERP System
      version: '1.0'
```

3. Configure the SDK in `.env`:

```
COMPLYANCE_API_KEY=your-api-key
COMPLYANCE_ENVIRONMENT=sandbox
```

4. Use the SDK in your services:

```php
use Complyance\SDK\ComplyanceSDK;
use Complyance\SDK\Models\UnifyRequest;

class MyService
{
    public function processInvoice(array $data)
    {
        $request = new UnifyRequest();
        $request->setDocumentType('TAX_INVOICE');
        $request->setCountry('SA');
        $request->setPayload($data);

        return ComplyanceSDK::pushToUnify($request);
    }
}
```

Or inject it using dependency injection:

```php
use Complyance\SDK\ComplyanceSDK;

class MyService
{
    private $sdk;

    public function __construct(ComplyanceSDK $sdk)
    {
        $this->sdk = $sdk;
    }
}
```

## Advanced Features

- Retry mechanism with exponential backoff
- Circuit breaker for resilience
- Comprehensive error handling
- Logging and observability

## Testing

The SDK includes a comprehensive testing suite to ensure reliability and correctness.

### Running Tests

```bash
# Run all tests
php run_tests.php

# Run specific test suite
php run_tests.php --suite Unit

# Run tests with code coverage
php run_tests.php --coverage
```

For more detailed information about testing, see [tests/README.md](tests/README.md).

### Code Coverage

The test suite is configured to generate code coverage reports in multiple formats:

- HTML reports in `coverage/html/`
- Clover XML in `coverage/clover.xml`
- Text summary in console output

### Quality Metrics

The SDK maintains high quality standards:

- 90%+ code coverage
- Static analysis with PHPStan
- Coding standards with PHP_CodeSniffer

## Documentation

For more detailed documentation, please visit [https://docs.complyance.io/php-sdk](https://docs.complyance.io/php-sdk).

## License

This SDK is released under the MIT License.
