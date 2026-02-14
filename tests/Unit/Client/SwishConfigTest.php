<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Client;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Client\SwishConfig;
use Swish\Enum\Environment;

final class SwishConfigTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $config = new SwishConfig(
            certPath: '/cert.pem',
            keyPath: '/key.pem',
            payeeAlias: '1231181189',
            verifyCertFiles: false,
        );

        $this->assertSame('/cert.pem', $config->certPath);
        $this->assertSame('/key.pem', $config->keyPath);
        $this->assertSame('1231181189', $config->payeeAlias);
        $this->assertNull($config->caPath);
        $this->assertNull($config->passphrase);
        $this->assertSame(Environment::Production, $config->environment);
        $this->assertSame(30, $config->timeout);
        $this->assertSame(10, $config->connectTimeout);
        $this->assertSame(3, $config->maxRetries);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $config = SwishConfig::fromArray([
            'certPath' => '/cert.pem',
            'keyPath' => '/key.pem',
            'payeeAlias' => '1231181189',
            'environment' => 'test',
            'timeout' => 60,
            'verifyCertFiles' => false,
        ]);

        $this->assertSame(Environment::Test, $config->environment);
        $this->assertSame(60, $config->timeout);
    }

    #[Test]
    public function it_creates_test_config(): void
    {
        $config = SwishConfig::forTest('/cert.pem', '/key.pem', '1231181189');

        $this->assertSame(Environment::Test, $config->environment);
        $this->assertSame('https://mss.cpc.getswish.net', $config->getBaseUrl());
    }

    #[Test]
    public function it_creates_sandbox_config(): void
    {
        // Sandbox verifies files by default, so we use the direct constructor with flag
        $config = new SwishConfig(
            '/cert.pem', '/key.pem', '1231181189',
            environment: Environment::Sandbox,
            verifyCertFiles: false,
        );

        $this->assertSame(Environment::Sandbox, $config->environment);
    }

    #[Test]
    public function it_returns_correct_base_url(): void
    {
        $prod = new SwishConfig('/c.pem', '/k.pem', '1', environment: Environment::Production, verifyCertFiles: false);
        $test = SwishConfig::forTest('/c.pem', '/k.pem', '1');
        $sandbox = new SwishConfig('/c.pem', '/k.pem', '1', environment: Environment::Sandbox, verifyCertFiles: false);

        $this->assertSame('https://cpc.getswish.net', $prod->getBaseUrl());
        $this->assertSame('https://mss.cpc.getswish.net', $test->getBaseUrl());
        $this->assertSame('https://staging.getswish.pub.tds.tieto.com', $sandbox->getBaseUrl());
    }

    #[Test]
    public function it_throws_on_empty_cert_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('certPath');
        new SwishConfig('', '/key.pem', '1231181189', verifyCertFiles: false);
    }

    #[Test]
    public function it_throws_on_empty_key_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('keyPath');
        new SwishConfig('/cert.pem', '', '1231181189', verifyCertFiles: false);
    }

    #[Test]
    public function it_throws_on_empty_payee_alias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('payeeAlias');
        new SwishConfig('/cert.pem', '/key.pem', '', verifyCertFiles: false);
    }

    #[Test]
    public function it_throws_on_negative_timeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SwishConfig('/c.pem', '/k.pem', '1', timeout: 0, verifyCertFiles: false);
    }

    #[Test]
    public function it_throws_on_negative_max_retries(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SwishConfig('/c.pem', '/k.pem', '1', maxRetries: -1, verifyCertFiles: false);
    }

    #[Test]
    public function it_throws_from_array_missing_required(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SwishConfig::fromArray(['certPath' => '/cert.pem']);
    }

    #[Test]
    public function it_allows_zero_retries(): void
    {
        $config = new SwishConfig('/c.pem', '/k.pem', '1', maxRetries: 0, verifyCertFiles: false);
        $this->assertSame(0, $config->maxRetries);
    }

    #[Test]
    public function it_fails_fast_on_missing_cert_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Certificate file not found');

        // verifyCertFiles defaults to true — this should fail immediately
        new SwishConfig('/nonexistent/cert.pem', '/nonexistent/key.pem', '1231181189');
    }
}
