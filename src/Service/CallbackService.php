<?php

declare(strict_types=1);

namespace Swish\Service;

use Swish\DTO\CallbackData;
use Swish\Exception\ValidationException;

/**
 * Handles incoming Swish callback/webhook payloads.
 *
 * SECURITY WARNING: This service parses callback data but does NOT verify
 * the authenticity of the sender. Swish callbacks are authenticated via mTLS
 * at the transport layer — your web server (nginx/Apache) must be configured
 * to require and verify the Swish client certificate on the callback endpoint.
 *
 * Recommended server-side protections:
 *  1. Configure mTLS on the callback endpoint (verify Swish's client certificate)
 *  2. Restrict the callback endpoint to Swish IP ranges if available
 *  3. Always verify the callback payment ID matches a payment you initiated
 *  4. Never trust the callback amount/status without cross-checking via get()
 *
 * @see https://developer.swish.nu/documentation/guides/verify-callback
 */
final class CallbackService
{
    /**
     * Maximum allowed callback body size (64 KB).
     * Prevents memory exhaustion from oversized payloads.
     */
    private const MAX_BODY_SIZE = 65536;

    /**
     * Parse a callback payload from a JSON string.
     *
     * @param string $json The raw JSON body from the Swish callback
     *
     * @throws ValidationException If the JSON is invalid, too large, or missing required fields
     */
    public function parse(string $json): CallbackData
    {
        if (strlen($json) > self::MAX_BODY_SIZE) {
            throw new ValidationException(
                'Callback payload exceeds maximum allowed size of ' . self::MAX_BODY_SIZE . ' bytes',
            );
        }

        try {
            $data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ValidationException(
                'Invalid callback JSON: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        if (!is_array($data)) {
            throw new ValidationException('Callback payload must be a JSON object');
        }

        return $this->validateAndParse($data);
    }

    /**
     * Parse from the PHP input stream (convenience for webhook endpoints).
     *
     * Reads at most MAX_BODY_SIZE bytes from php://input.
     *
     * @throws ValidationException
     */
    public function parseFromRequest(): CallbackData
    {
        $input = file_get_contents('php://input', false, null, 0, self::MAX_BODY_SIZE + 1);

        if ($input === false || $input === '') {
            throw new ValidationException('Empty callback request body');
        }

        return $this->parse($input);
    }

    /**
     * Parse from a pre-decoded array (useful when framework already decoded JSON).
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    public function parseFromArray(array $data): CallbackData
    {
        return $this->validateAndParse($data);
    }

    /**
     * Validate required fields and parse callback data.
     *
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function validateAndParse(array $data): CallbackData
    {
        $id = $data['id'] ?? $data['instructionUUID'] ?? $data['payoutInstructionUUID'] ?? null;

        if ($id === null || (string) $id === '') {
            throw new ValidationException(
                'Callback payload missing required field: id (or instructionUUID/payoutInstructionUUID)',
            );
        }

        if (!isset($data['status']) || (string) $data['status'] === '') {
            throw new ValidationException('Callback payload missing required field: status');
        }

        return CallbackData::fromArray($data);
    }
}
