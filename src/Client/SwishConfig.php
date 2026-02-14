<?php

declare(strict_types=1);

namespace Swish\Client;

use InvalidArgumentException;
use Swish\Enum\Environment;

/**
 * Immutable configuration for the Swish SDK client.
 *
 * Certificate file existence is verified at construction time (fail-fast).
 * This ensures misconfigurations surface immediately rather than on the
 * first API call, which might be minutes after startup.
 */
final readonly class SwishConfig
{
    /**
     * @param string      $certPath       Path to the client certificate (PEM or P12)
     * @param string      $keyPath        Path to the private key file
     * @param string      $payeeAlias     Your Swish merchant number
     * @param string|null $caPath         Path to CA bundle for verification (null = system default)
     * @param string|null $passphrase     Passphrase for the client certificate/key
     * @param Environment $environment    API environment (production, test, sandbox)
     * @param int         $timeout        Request timeout in seconds
     * @param int         $connectTimeout Connection timeout in seconds
     * @param int         $maxRetries     Maximum number of retries for transient failures
     * @param bool        $verifyCertFiles Whether to verify cert files exist at construction (disable for testing)
     *
     * @throws InvalidArgumentException If required parameters are missing or invalid
     */
    public function __construct(
        public string $certPath,
        public string $keyPath,
        public string $payeeAlias,
        public ?string $caPath = null,
        public ?string $passphrase = null,
        public Environment $environment = Environment::Production,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public int $maxRetries = 3,
        private bool $verifyCertFiles = true,
    ) {
        $this->validate();
    }

    /**
     * Create from an associative array (useful for config files).
     *
     * @param array<string, mixed> $config
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $config): self
    {
        if (!isset($config['certPath'], $config['keyPath'], $config['payeeAlias'])) {
            throw new InvalidArgumentException(
                'SwishConfig requires certPath, keyPath, and payeeAlias',
            );
        }

        return new self(
            certPath: (string) $config['certPath'],
            keyPath: (string) $config['keyPath'],
            payeeAlias: (string) $config['payeeAlias'],
            caPath: isset($config['caPath']) ? (string) $config['caPath'] : null,
            passphrase: isset($config['passphrase']) ? (string) $config['passphrase'] : null,
            environment: isset($config['environment'])
                ? (is_string($config['environment'])
                    ? Environment::from($config['environment'])
                    : $config['environment'])
                : Environment::Production,
            timeout: (int) ($config['timeout'] ?? 30),
            connectTimeout: (int) ($config['connectTimeout'] ?? 10),
            maxRetries: (int) ($config['maxRetries'] ?? 3),
            verifyCertFiles: (bool) ($config['verifyCertFiles'] ?? true),
        );
    }

    /**
     * Create a config pre-set for the test MSS environment.
     */
    public static function forTest(
        string $certPath,
        string $keyPath,
        string $payeeAlias,
        ?string $caPath = null,
        ?string $passphrase = null,
    ): self {
        return new self(
            certPath: $certPath,
            keyPath: $keyPath,
            payeeAlias: $payeeAlias,
            caPath: $caPath,
            passphrase: $passphrase,
            environment: Environment::Test,
            verifyCertFiles: false, // Test configs often use placeholder paths
        );
    }

    /**
     * Create a config pre-set for the sandbox environment.
     */
    public static function forSandbox(
        string $certPath,
        string $keyPath,
        string $payeeAlias,
        ?string $caPath = null,
        ?string $passphrase = null,
    ): self {
        return new self(
            certPath: $certPath,
            keyPath: $keyPath,
            payeeAlias: $payeeAlias,
            caPath: $caPath,
            passphrase: $passphrase,
            environment: Environment::Sandbox,
        );
    }

    /**
     * Get the base URL for the configured environment.
     */
    public function getBaseUrl(): string
    {
        return $this->environment->baseUrl();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (trim($this->certPath) === '') {
            throw new InvalidArgumentException('certPath must not be empty');
        }

        if (trim($this->keyPath) === '') {
            throw new InvalidArgumentException('keyPath must not be empty');
        }

        if (trim($this->payeeAlias) === '') {
            throw new InvalidArgumentException('payeeAlias must not be empty');
        }

        if ($this->timeout < 1) {
            throw new InvalidArgumentException('timeout must be at least 1 second');
        }

        if ($this->connectTimeout < 1) {
            throw new InvalidArgumentException('connectTimeout must be at least 1 second');
        }

        if ($this->maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must be >= 0');
        }

        // Fail-fast: verify certificate files exist at startup, not on first request
        if ($this->verifyCertFiles) {
            if (!is_file($this->certPath) || !is_readable($this->certPath)) {
                throw new InvalidArgumentException(
                    "Certificate file not found or not readable: {$this->certPath}",
                );
            }

            if (!is_file($this->keyPath) || !is_readable($this->keyPath)) {
                throw new InvalidArgumentException(
                    "Key file not found or not readable: {$this->keyPath}",
                );
            }

            if ($this->caPath !== null && (!is_file($this->caPath) || !is_readable($this->caPath))) {
                throw new InvalidArgumentException(
                    "CA bundle file not found or not readable: {$this->caPath}",
                );
            }
        }
    }
}
