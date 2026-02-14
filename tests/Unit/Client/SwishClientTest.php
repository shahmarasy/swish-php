<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Client;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;
use Swish\Enum\Environment;
use Swish\Http\HttpClientInterface;
use Swish\Service\CallbackService;
use Swish\Service\PaymentService;
use Swish\Service\PayoutService;
use Swish\Service\RefundService;

final class SwishClientTest extends TestCase
{
    private SwishClient $client;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = SwishConfig::forTest('/cert.pem', '/key.pem', '1231181189');
        $this->client = new SwishClient($config, $httpClient);
    }

    #[Test]
    public function it_returns_payment_service(): void
    {
        $this->assertInstanceOf(PaymentService::class, $this->client->payments());
    }

    #[Test]
    public function it_returns_refund_service(): void
    {
        $this->assertInstanceOf(RefundService::class, $this->client->refunds());
    }

    #[Test]
    public function it_returns_payout_service(): void
    {
        $this->assertInstanceOf(PayoutService::class, $this->client->payouts());
    }

    #[Test]
    public function it_returns_callback_service(): void
    {
        $this->assertInstanceOf(CallbackService::class, $this->client->callbacks());
    }

    #[Test]
    public function it_returns_same_service_instance(): void
    {
        $a = $this->client->payments();
        $b = $this->client->payments();
        $this->assertSame($a, $b, 'Services should be lazily cached');
    }

    #[Test]
    public function it_exposes_http_client(): void
    {
        $this->assertInstanceOf(HttpClientInterface::class, $this->client->getHttpClient());
    }

    #[Test]
    public function it_exposes_resolved_config(): void
    {
        $config = $this->client->getConfig();
        $this->assertInstanceOf(SwishConfig::class, $config);
        $this->assertSame(Environment::Test, $config->environment);
    }

    #[Test]
    public function it_accepts_array_config(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $client = new SwishClient([
            'certPath' => '/cert.pem',
            'keyPath' => '/key.pem',
            'payeeAlias' => '1231181189',
            'environment' => 'sandbox',
            'verifyCertFiles' => false,
        ], $httpClient);

        $this->assertSame(Environment::Sandbox, $client->getConfig()->environment);
    }
}
