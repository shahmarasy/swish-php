<?php

declare(strict_types=1);

namespace Swish\Security;

use Swish\Exception\SwishException;

/**
 * Handles payload signing for Swish Payout API requests.
 *
 * The payout API requires the payload to be hashed with SHA-512
 * and then signed with the private key of the signing certificate.
 *
 * Security notes:
 *  - File paths are validated to prevent path traversal attacks
 *  - OpenSSL error messages are sanitized before being exposed
 *  - Signing keys are never stored in memory beyond the sign() call
 */
final class PayoutSignature
{
    /**
     * Sign a payout payload.
     *
     * @param array<string, mixed> $payload           The payout payload to sign
     * @param string               $signingKeyPath    Path to the signing certificate private key
     * @param string|null          $signingPassphrase  Passphrase for the signing key
     *
     * @return string Base64-encoded signature
     *
     * @throws SwishException If signing fails
     */
    public static function sign(
        array $payload,
        string $signingKeyPath,
        ?string $signingPassphrase = null,
    ): string {
        self::validateFilePath($signingKeyPath, 'signing key');

        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $keyContent = file_get_contents($signingKeyPath);

        if ($keyContent === false || $keyContent === '') {
            throw new SwishException('Cannot read signing key file');
        }

        $privateKey = openssl_pkey_get_private($keyContent, $signingPassphrase ?? '');

        // Clear the raw key content from memory
        $keyContent = '';

        if ($privateKey === false) {
            // Sanitize OpenSSL error — never expose internal details
            throw new SwishException('Failed to load signing private key. Verify the key file format and passphrase.');
        }

        $signature = '';
        $result = openssl_sign($payloadJson, $signature, $privateKey, OPENSSL_ALGO_SHA512);

        if (!$result) {
            throw new SwishException('Failed to sign payout payload. Verify the signing key is valid.');
        }

        return base64_encode($signature);
    }

    /**
     * Get the serial number from a certificate file.
     *
     * @param string $certPath Path to the certificate (PEM)
     *
     * @return string Hexadecimal serial number (uppercase)
     *
     * @throws SwishException If reading the certificate fails
     */
    public static function getCertificateSerialNumber(string $certPath): string
    {
        self::validateFilePath($certPath, 'certificate');

        $certContent = file_get_contents($certPath);

        if ($certContent === false || $certContent === '') {
            throw new SwishException('Cannot read certificate file');
        }

        $certData = openssl_x509_parse($certContent);

        if ($certData === false || !isset($certData['serialNumberHex'])) {
            throw new SwishException('Failed to parse certificate. Verify the file is a valid PEM certificate.');
        }

        return strtoupper($certData['serialNumberHex']);
    }

    /**
     * Validate that a file path is safe and the file exists.
     *
     * Prevents path traversal, null byte injection, and protocol wrapper abuse.
     *
     * @throws SwishException
     */
    private static function validateFilePath(string $path, string $description): void
    {
        if (trim($path) === '') {
            throw new SwishException("The {$description} path must not be empty");
        }

        // Block null bytes (null byte injection)
        if (str_contains($path, "\0")) {
            throw new SwishException("Invalid {$description} path: contains null bytes");
        }

        // Block PHP stream wrappers (php://, http://, ftp://, data://, etc.)
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $path)) {
            throw new SwishException("Invalid {$description} path: stream wrappers are not allowed");
        }

        // Resolve the real path and verify the file exists
        $realPath = realpath($path);

        if ($realPath === false) {
            throw new SwishException("The {$description} file does not exist: path could not be resolved");
        }

        if (!is_file($realPath)) {
            throw new SwishException("The {$description} path is not a regular file");
        }

        if (!is_readable($realPath)) {
            throw new SwishException("The {$description} file is not readable");
        }
    }
}
