<?php

/**
 * Example: Create a Swish refund.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;
use Swish\Enum\Environment;
use Swish\Exception\SwishException;

$config = new SwishConfig(
    certPath: '/path/to/client-cert.pem',
    keyPath: '/path/to/client-key.pem',
    payeeAlias: '1231181189',
    environment: Environment::Test,
);

$swish = new SwishClient($config);

try {
    // Create a refund for a previous payment
    $refund = $swish->refunds()->create([
        'originalPaymentReference' => 'ORIGINAL_PAYMENT_REFERENCE_FROM_CALLBACK',
        'callbackUrl' => 'https://example.com/api/swish/refund-callback',
        'payerAlias' => '1231181189',
        'amount' => '50.00',
        'currency' => 'SEK',
        'payerPaymentReference' => 'refund-order-12345',
        'message' => 'Refund for Order #12345',
    ]);

    echo "Refund created!\n";
    echo "ID: {$refund->id}\n";
    echo "Status: {$refund->status->value}\n";

    // Retrieve refund status
    $retrieved = $swish->refunds()->get($refund->id);
    echo "Refund status: {$retrieved->status->value}\n";

} catch (SwishException $e) {
    echo "Error: {$e->getMessage()}\n";
    foreach ($e->getErrors() as $error) {
        echo "  [{$error->errorCode}] {$error->errorMessage}\n";
    }
}
