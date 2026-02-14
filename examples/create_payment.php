<?php

/**
 * Example: Create a Swish payment request.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;
use Swish\Enum\Environment;
use Swish\Exception\SwishException;
use Swish\Exception\ValidationException;

// Configure the SDK
$config = new SwishConfig(
    certPath: '/path/to/client-cert.pem',
    keyPath: '/path/to/client-key.pem',
    payeeAlias: '1231181189',
    caPath: '/path/to/swish-ca.pem',
    passphrase: null, // Set if your key is encrypted
    environment: Environment::Test,
);

$swish = new SwishClient($config);

try {
    // Create a payment request (E-Commerce — payerAlias is set)
    $payment = $swish->payments()->create([
        'payeeAlias' => '1231181189',
        'payerAlias' => '46712345678',
        'amount' => '100.00',
        'currency' => 'SEK',
        'callbackUrl' => 'https://example.com/api/swish/callback',
        'payeePaymentReference' => 'order-12345',
        'message' => 'Payment for Order #12345',
    ]);

    echo "Payment created!\n";
    echo "ID: {$payment->id}\n";
    echo "Status: {$payment->status->value}\n";
    echo "Token: {$payment->paymentRequestToken}\n";

    // Retrieve the payment
    $retrieved = $swish->payments()->get($payment->id);
    echo "Retrieved status: {$retrieved->status->value}\n";

    // Create an M-Commerce payment (no payerAlias — returns a token for the Swish app)
    $mcommerce = $swish->payments()->create([
        'payeeAlias' => '1231181189',
        'amount' => '50.00',
        'currency' => 'SEK',
        'callbackUrl' => 'https://example.com/api/swish/callback',
        'message' => 'M-Commerce payment',
    ]);

    echo "M-Commerce token: {$mcommerce->paymentRequestToken}\n";

    // Cancel a payment
    $cancelled = $swish->payments()->cancel($payment->id);
    echo "Cancelled status: {$cancelled->status->value}\n";

} catch (ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
    foreach ($e->getErrors() as $error) {
        echo "  [{$error->errorCode}] {$error->errorMessage}\n";
    }
} catch (SwishException $e) {
    echo "Swish error: {$e->getMessage()}\n";
}
