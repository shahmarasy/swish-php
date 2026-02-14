<?php

/**
 * Example: Handle Swish callback/webhook.
 *
 * Place this at your callbackUrl endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;
use Swish\Exception\ValidationException;

$config = new SwishConfig(
    certPath: '/path/to/client-cert.pem',
    keyPath: '/path/to/client-key.pem',
    payeeAlias: '1231181189',
);

$swish = new SwishClient($config);

try {
    // Parse the callback from the request body
    $callback = $swish->callbacks()->parseFromRequest();

    // Check the type and status
    if ($callback->isRefund()) {
        // This is a refund callback
        echo "Refund {$callback->id}: {$callback->status}\n";
    } elseif ($callback->isPaid()) {
        // Payment successful
        $orderId = $callback->payeePaymentReference;
        $paymentRef = $callback->paymentReference;
        echo "Payment {$callback->id} succeeded! Reference: {$paymentRef}\n";

        // TODO: Update your order status in the database
    } elseif ($callback->isDeclined()) {
        echo "Payment {$callback->id} was declined\n";
    } elseif ($callback->isError()) {
        echo "Payment {$callback->id} error: [{$callback->errorCode}] {$callback->errorMessage}\n";
    } elseif ($callback->isCancelled()) {
        echo "Payment {$callback->id} was cancelled\n";
    }

    // Respond with 200 OK so Swish stops retrying
    http_response_code(200);
    echo 'OK';

} catch (ValidationException $e) {
    http_response_code(400);
    echo "Invalid callback: {$e->getMessage()}";
}
