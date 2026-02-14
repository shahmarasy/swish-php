<?php

declare(strict_types=1);

namespace Swish\Client;

use Psr\Log\LoggerInterface;
use Swish\Http\GuzzleHttpClient;
use Swish\Http\HttpClientInterface;
use Swish\Service\CallbackService;
use Swish\Service\PaymentService;
use Swish\Service\PayoutService;
use Swish\Service\RefundService;

/**
 * Main entry point for the Swish PHP SDK.
 *
 * Usage:
 *   $swish = new SwishClient($config);
 *   $payment = $swish->payments()->create(['amount' => '100.00', ...]);
 *   // payeeAlias is auto-injected from config — no need to repeat it.
 */
final class SwishClient
{
    private readonly SwishConfig $resolvedConfig;
    private readonly HttpClientInterface $httpClient;
    private ?PaymentService $paymentService = null;
    private ?RefundService $refundService = null;
    private ?PayoutService $payoutService = null;
    private ?CallbackService $callbackService = null;

    /**
     * @param SwishConfig|array<string, mixed> $config     SDK configuration
     * @param HttpClientInterface|null         $httpClient Custom HTTP client (null = default Guzzle with mTLS)
     * @param LoggerInterface|null             $logger     PSR-3 logger
     */
    public function __construct(
        SwishConfig|array $config,
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->resolvedConfig = is_array($config)
            ? SwishConfig::fromArray($config)
            : $config;

        $this->httpClient = $httpClient ?? new GuzzleHttpClient($this->resolvedConfig, $logger);
    }

    /**
     * Access the Payment service.
     *
     * The configured payeeAlias is automatically injected into payment requests.
     */
    public function payments(): PaymentService
    {
        return $this->paymentService ??= new PaymentService(
            $this->httpClient,
            $this->resolvedConfig->payeeAlias,
        );
    }

    /**
     * Access the Refund service.
     */
    public function refunds(): RefundService
    {
        return $this->refundService ??= new RefundService(
            $this->httpClient,
            $this->resolvedConfig->payeeAlias,
        );
    }

    /**
     * Access the Payout service.
     */
    public function payouts(): PayoutService
    {
        return $this->payoutService ??= new PayoutService(
            $this->httpClient,
            $this->resolvedConfig->payeeAlias,
        );
    }

    /**
     * Access the Callback service.
     */
    public function callbacks(): CallbackService
    {
        return $this->callbackService ??= new CallbackService();
    }

    /**
     * Get the resolved configuration.
     */
    public function getConfig(): SwishConfig
    {
        return $this->resolvedConfig;
    }

    /**
     * Get the underlying HTTP client (for advanced use cases).
     */
    public function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient;
    }
}
