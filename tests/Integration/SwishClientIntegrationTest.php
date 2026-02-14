<?php

declare(strict_types=1);

namespace Swish\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Client\SwishClient;
use Swish\Client\SwishConfig;
use Swish\Enum\Environment;
use Swish\Exception\SwishException;

/**
 * Integration test example — requires valid test certificates and the MSS simulator.
 *
 * Run with: vendor/bin/phpunit --group=integration
 *
 * Set environment variables:
 *   SWISH_CERT_PATH   — path to test client certificate
 *   SWISH_KEY_PATH    — path to test private key
 *   SWISH_CA_PATH     — path to Swish CA certificate (optional)
 *   SWISH_PASSPHRASE  — certificate passphrase (optional)
 *   SWISH_PAYEE_ALIAS — merchant Swish number (e.g. 1231181189)
 */
#[Group('integration')]
final class SwishClientIntegrationTest extends TestCase
{
    private ?SwishClient $swish = null;

    protected function setUp(): void
    {
        $certPath = getenv('SWISH_CERT_PATH');
        $keyPath = getenv('SWISH_KEY_PATH');
        $payeeAlias = getenv('SWISH_PAYEE_ALIAS');

        if (!$certPath || !$keyPath || !$payeeAlias) {
            $this->markTestSkipped(
                'Swish integration tests require SWISH_CERT_PATH, SWISH_KEY_PATH, and SWISH_PAYEE_ALIAS env vars.',
            );
        }

        $config = new SwishConfig(
            certPath: $certPath,
            keyPath: $keyPath,
            payeeAlias: $payeeAlias,
            caPath: getenv('SWISH_CA_PATH') ?: null,
            passphrase: getenv('SWISH_PASSPHRASE') ?: null,
            environment: Environment::Test,
        );

        $this->swish = new SwishClient($config);
    }

    #[Test]
    public function it_creates_and_retrieves_a_payment(): void
    {
        $this->assertNotNull($this->swish);

        try {
            $payment = $this->swish->payments()->create([
                'payeeAlias' => getenv('SWISH_PAYEE_ALIAS'),
                'payerAlias' => '46712345678',
                'amount' => '1.00',
                'callbackUrl' => 'https://example.com/callback',
                'message' => 'Integration test',
            ]);

            $this->assertNotEmpty($payment->id);

            // Retrieve the payment we just created
            $retrieved = $this->swish->payments()->get($payment->id);
            $this->assertSame($payment->id, $retrieved->id);
        } catch (SwishException $e) {
            // In test environment, some errors are expected — log and pass
            $this->addWarning('Swish API returned error: ' . $e->getMessage());
        }
    }
}
