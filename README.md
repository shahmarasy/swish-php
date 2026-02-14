# Swish PHP SDK

A **production-grade PHP SDK** for the [Swish Payment Gateway](https://developer.swish.nu/) with full mTLS support, comprehensive API coverage, and clean architecture.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## Features

- **Full API Coverage** — Payment Requests (v2), Refunds (v2), Payouts (v1)
- **mTLS Security** — Client certificate authentication with TLS 1.2+
- **Immutable DTOs** — Type-safe, readonly data objects
- **Typed Enums** — `PaymentStatus`, `RefundStatus`, `PayoutStatus`, `Currency`, `Environment`
- **Robust Error Handling** — Exception hierarchy mapping HTTP codes to meaningful errors
- **Retry Strategy** — Configurable exponential backoff for transient failures
- **PSR-3 Logging** — Inject any PSR-3 logger
- **DI Friendly** — Interface-driven, no static anti-patterns
- **Idempotency** — Auto-generated instruction UUIDs (or bring your own)
- **Callback Parsing** — Parse Swish webhooks into typed DTOs
- **Payout Signing** — SHA-512 + RSA payload signing built-in

---

## Installation

```bash
composer require swish/swish-php
```

Requires **PHP 8.2+** and the following extensions: `curl`, `openssl`, `json`.

---

## Quick Start

```php
<?php

use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;
use Swish\Enum\Environment;

$config = new SwishConfig(
    certPath: '/path/to/client-cert.pem',
    keyPath: '/path/to/client-key.pem',
    payeeAlias: '1231181189',
    caPath: '/path/to/swish-ca.pem',
    environment: Environment::Production,
);

$swish = new SwishClient($config);
```

---

## Configuration

### Constructor

```php
$config = new SwishConfig(
    certPath: '/certs/client.pem',    // Required: client certificate
    keyPath: '/certs/client-key.pem', // Required: private key
    payeeAlias: '1231181189',         // Required: your Swish number
    caPath: '/certs/swish-ca.pem',    // Optional: CA bundle (null = system default)
    passphrase: 'secret',             // Optional: key passphrase
    environment: Environment::Test,   // Optional: Production (default), Test, Sandbox
    timeout: 30,                      // Optional: request timeout (seconds)
    connectTimeout: 10,               // Optional: connection timeout (seconds)
    maxRetries: 3,                    // Optional: retry count for transient failures
);
```

### From Array

```php
$config = SwishConfig::fromArray([
    'certPath' => '/certs/client.pem',
    'keyPath' => '/certs/client-key.pem',
    'payeeAlias' => '1231181189',
    'environment' => 'test',
]);
```

### Environment Presets

```php
$config = SwishConfig::forTest('/cert.pem', '/key.pem', '1231181189');
$config = SwishConfig::forSandbox('/cert.pem', '/key.pem', '1231181189');
```

### Environments

| Environment | Base URL |
|---|---|
| `Production` | `https://cpc.getswish.net` |
| `Test` (MSS) | `https://mss.cpc.getswish.net` |
| `Sandbox` | `https://staging.getswish.pub.tds.tieto.com` |

---

## Payments

### Create Payment Request (E-Commerce)

```php
$payment = $swish->payments()->create([
    'payeeAlias' => '1231181189',
    'payerAlias' => '46712345678',  // Payer's phone → E-Commerce
    'amount' => '100.00',
    'currency' => 'SEK',
    'callbackUrl' => 'https://example.com/callback',
    'payeePaymentReference' => 'order-123',
    'message' => 'Payment for Order #123',
]);

echo $payment->id;     // Instruction UUID
echo $payment->status; // PaymentStatus::CREATED
```

### Create Payment Request (M-Commerce)

Omit `payerAlias` to get a token for the Swish app:

```php
$payment = $swish->payments()->create([
    'payeeAlias' => '1231181189',
    'amount' => '50.00',
    'callbackUrl' => 'https://example.com/callback',
]);

echo $payment->paymentRequestToken; // Use to open the Swish app
```

### Retrieve Payment

```php
$payment = $swish->payments()->get('INSTRUCTION-UUID');
echo $payment->status->value; // "PAID", "DECLINED", etc.
```

### Cancel Payment

```php
$cancelled = $swish->payments()->cancel('INSTRUCTION-UUID');
```

### Custom Instruction UUID

```php
$payment = $swish->payments()->create($data, instructionUUID: 'YOUR-CUSTOM-UUID');
```

---

## Refunds

### Create Refund

```php
$refund = $swish->refunds()->create([
    'originalPaymentReference' => 'PAYMENT-REF-FROM-CALLBACK',
    'callbackUrl' => 'https://example.com/refund-callback',
    'payerAlias' => '1231181189',
    'amount' => '50.00',
    'currency' => 'SEK',
    'message' => 'Refund for Order #123',
]);
```

### Retrieve Refund

```php
$refund = $swish->refunds()->get('REFUND-UUID');
echo $refund->status->value; // "DEBITED", "PAID", etc.
```

---

## Payouts

### Create Payout (with auto-signing)

```php
use Swish\DTO\PayoutRequestData;
use Swish\Utils\IdGenerator;
use Swish\Security\PayoutSignature;

$data = new PayoutRequestData(
    payoutInstructionUUID: IdGenerator::generate(),
    payerPaymentReference: 'payout-ref-001',
    payerAlias: '1231181189',
    payeeAlias: '46712345678',
    payeeSSN: '199001011234',
    amount: '500.00',
    instructionDate: '2026-02-10',
    signingCertificateSerialNumber: PayoutSignature::getCertificateSerialNumber('/certs/signing.pem'),
);

$payout = $swish->payouts()->createSigned(
    data: $data,
    callbackUrl: 'https://example.com/payout-callback',
    signingKeyPath: '/certs/signing-key.pem',
);
```

### Retrieve Payout

```php
$payout = $swish->payouts()->get('PAYOUT-UUID');
```

---

## Callback Handling

Parse incoming Swish webhooks:

```php
// From raw request body
$callback = $swish->callbacks()->parseFromRequest();

// From JSON string
$callback = $swish->callbacks()->parse($jsonString);

// From decoded array (e.g. Laravel/Symfony)
$callback = $swish->callbacks()->parseFromArray($request->all());

// Check status
if ($callback->isPaid()) {
    // Process successful payment
    $paymentRef = $callback->paymentReference;
}

if ($callback->isRefund()) {
    // This callback is for a refund
}
```

---

## Error Handling

```php
use Swish\Exception\SwishException;
use Swish\Exception\AuthenticationException;
use Swish\Exception\ValidationException;
use Swish\Exception\ApiException;
use Swish\Exception\NetworkException;

try {
    $payment = $swish->payments()->create($data);
} catch (ValidationException $e) {
    // HTTP 422 — validation errors
    foreach ($e->getErrors() as $error) {
        echo "[{$error->errorCode}] {$error->errorMessage}\n";
    }
} catch (AuthenticationException $e) {
    // HTTP 401/403 — certificate or enrollment issues
} catch (NetworkException $e) {
    // Connection failure, timeout, DNS error
} catch (ApiException $e) {
    // HTTP 400, 415, 429, 500, 504
} catch (SwishException $e) {
    // Catch-all for any Swish SDK error
}
```

### Common Error Codes

| Code | Description |
|---|---|
| `RP01` | Missing Merchant Swish Number |
| `PA02` | Invalid amount |
| `AM02` | Amount too large |
| `AM06` | Amount below minimum |
| `RP03` | Invalid callback URL |
| `BE18` | Invalid payer alias |
| `ACMT03` | Payer not enrolled |
| `RF07` | Transaction declined |
| `TM01` | Timeout |

---

## Security Notes

- **mTLS is mandatory** — Swish enforces mutual TLS. The SDK configures client certificates automatically.
- **TLS 1.2+ enforced** — The SDK uses `CURL_SSLVERSION_TLSv1_2` minimum.
- **SSL verification is never disabled** — The `verify` option always points to a CA bundle or system defaults.
- **Certificates** — Obtain test certificates from [Swish Developer Portal](https://developer.swish.nu/). Production certificates come from Swish Certificate Management.

---

## PSR-3 Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('swish');
$logger->pushHandler(new StreamHandler('/var/log/swish.log'));

$swish = new SwishClient($config, logger: $logger);
```

---

## Laravel Integration

### Service Provider

```php
// app/Providers/SwishServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;

class SwishServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SwishClient::class, function ($app) {
            return new SwishClient(
                SwishConfig::fromArray(config('services.swish')),
            );
        });
    }
}
```

### Config

```php
// config/services.php
'swish' => [
    'certPath' => storage_path('certs/swish-client.pem'),
    'keyPath' => storage_path('certs/swish-key.pem'),
    'payeeAlias' => env('SWISH_PAYEE_ALIAS'),
    'environment' => env('SWISH_ENVIRONMENT', 'production'),
],
```

### Controller Usage

```php
class PaymentController extends Controller
{
    public function __construct(private SwishClient $swish) {}

    public function create(Request $request)
    {
        $payment = $this->swish->payments()->create([
            'payeeAlias' => config('services.swish.payeeAlias'),
            'amount' => $request->input('amount'),
            'callbackUrl' => route('swish.callback'),
        ]);

        return response()->json(['id' => $payment->id]);
    }
}
```

---

## Symfony Integration

### Service Definition

```yaml
# config/services.yaml
services:
    Swish\Client\SwishConfig:
        arguments:
            $certPath: '%env(SWISH_CERT_PATH)%'
            $keyPath: '%env(SWISH_KEY_PATH)%'
            $payeeAlias: '%env(SWISH_PAYEE_ALIAS)%'
            $environment: !enum Swish\Enum\Environment::Production

    Swish\Client\SwishClient:
        arguments:
            $config: '@Swish\Client\SwishConfig'
```

### Controller

```php
class PaymentController extends AbstractController
{
    public function __construct(private SwishClient $swish) {}

    #[Route('/payment', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payment = $this->swish->payments()->create([...]);
        return $this->json(['id' => $payment->id]);
    }
}
```

---

## Testing

### Run Tests

```bash
composer install
vendor/bin/phpunit
```

### Using a Mock HTTP Client

```php
use Swish\Http\HttpClientInterface;
use Swish\Http\HttpResponse;

$mockClient = new class implements HttpClientInterface {
    public function send(string $method, string $uri, array $options = []): HttpResponse {
        return new HttpResponse(201, [], '');
    }
};

$swish = new SwishClient($config, httpClient: $mockClient);
```

### Integration Tests

Set environment variables and run:

```bash
SWISH_CERT_PATH=/path/to/cert.pem \
SWISH_KEY_PATH=/path/to/key.pem \
SWISH_PAYEE_ALIAS=1231181189 \
vendor/bin/phpunit --group=integration
```

---

## Architecture

```
src/
├── Client/
│   ├── SwishClient.php         # Main entry point
│   └── SwishConfig.php         # Immutable configuration
├── DTO/
│   ├── CallbackData.php        # Webhook payload
│   ├── PaymentRequestData.php  # Payment creation data
│   ├── PaymentResponse.php     # Payment API response
│   ├── PayoutRequestData.php   # Payout creation data
│   ├── PayoutResponse.php      # Payout API response
│   ├── RefundRequestData.php   # Refund creation data
│   ├── RefundResponse.php      # Refund API response
│   └── SwishError.php          # Error object
├── Enum/
│   ├── Currency.php            # SEK
│   ├── Environment.php         # Production, Test, Sandbox
│   ├── PaymentStatus.php       # CREATED, PAID, DECLINED, ERROR, CANCELLED
│   ├── PayoutStatus.php        # CREATED, DEBITED, PAID, ERROR
│   ├── PayoutType.php          # PAYOUT
│   └── RefundStatus.php        # CREATED, DEBITED, PAID, ERROR
├── Exception/
│   ├── ApiException.php        # 400, 415, 429, 500, 504
│   ├── AuthenticationException.php  # 401, 403
│   ├── NetworkException.php    # Connection failures
│   ├── SwishException.php      # Base exception
│   └── ValidationException.php # 422
├── Http/
│   ├── GuzzleHttpClient.php    # mTLS Guzzle implementation
│   ├── HttpClientInterface.php # Abstraction
│   └── HttpResponse.php        # Response value object
├── Security/
│   └── PayoutSignature.php     # SHA-512 + RSA signing
├── Service/
│   ├── CallbackService.php     # Webhook parsing
│   ├── PaymentService.php      # Payment CRUD
│   ├── PayoutService.php       # Payout operations
│   └── RefundService.php       # Refund operations
└── Utils/
    └── IdGenerator.php         # UUID generation
```

---

## License

MIT License — see [LICENSE](LICENSE).
