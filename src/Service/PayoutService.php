<?php

declare(strict_types=1);

namespace Swish\Service;

use InvalidArgumentException;
use Swish\DTO\PayoutRequestData;
use Swish\DTO\PayoutResponse;
use Swish\Http\HttpClientInterface;
use Swish\Security\PayoutSignature;

/**
 * Handles Swish payout operations.
 *
 * Payouts require a signed payload (SHA-512 + RSA).
 *
 * Endpoints:
 *  - Create: POST /swish-cpcapi/api/v1/payouts/
 *  - Get:    GET  /swish-cpcapi/api/v1/payouts/{uuid}
 */
final class PayoutService
{
    private const CREATE_ENDPOINT = '/swish-cpcapi/api/v1/payouts/';
    private const GET_ENDPOINT = '/swish-cpcapi/api/v1/payouts/';

    /**
     * @param HttpClientInterface $httpClient HTTP client for API calls
     * @param string|null $defaultPayerAlias Default payer alias from config (for payouts, the merchant is the payer)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $defaultPayerAlias = null,
    ) {
    }

    /**
     * Create a payout.
     *
     * @param PayoutRequestData|array<string, mixed> $data           Payout request data
     * @param string                                 $signature      Base64-encoded signature of the payload
     * @param string                                 $callbackUrl    HTTPS callback URL for payout result
     *
     * @return PayoutResponse The created payout
     *
     * @throws InvalidArgumentException
     */
    public function create(
        PayoutRequestData|array $data,
        string $signature,
        string $callbackUrl,
    ): PayoutResponse {
        if (is_array($data)) {
            if (!isset($data['payerAlias']) && $this->defaultPayerAlias !== null) {
                $data['payerAlias'] = $this->defaultPayerAlias;
            }
            $data = PayoutRequestData::fromArray($data);
        }

        if (trim($signature) === '') {
            throw new InvalidArgumentException('signature must not be empty');
        }

        if (trim($callbackUrl) === '') {
            throw new InvalidArgumentException('callbackUrl must not be empty');
        }

        $payload = $data->toApiPayload();

        $requestBody = [
            'payload' => $payload,
            'signature' => $signature,
            'callbackUrl' => $callbackUrl,
        ];

        $response = $this->httpClient->send('POST', self::CREATE_ENDPOINT, [
            'json' => $requestBody,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $body = $response->json() ?? [];

        $responseData = array_merge(
            array_filter([
                'payoutInstructionUUID' => $data->payoutInstructionUUID,
                'status' => 'CREATED',
                'payerAlias' => $data->payerAlias,
                'payeeAlias' => $data->payeeAlias,
                'payeeSSN' => $data->payeeSSN,
                'amount' => $data->amount,
                'currency' => $data->currency->value,
                'message' => $data->message,
                'callbackUrl' => $callbackUrl,
            ], static fn(mixed $v): bool => $v !== null),
            $body,
        );

        return PayoutResponse::fromArray($responseData);
    }

    /**
     * Retrieve a payout by its instruction UUID.
     *
     * @param string $id The payout instruction UUID
     *
     * @throws InvalidArgumentException
     */
    public function get(string $id): PayoutResponse
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Payout ID must not be empty');
        }

        $response = $this->httpClient->send('GET', self::GET_ENDPOINT . $id);

        return PayoutResponse::fromArray($response->json() ?? []);
    }

    /**
     * Convenience method to create a payout with automatic signing.
     *
     * @param PayoutRequestData|array<string, mixed> $data
     * @param string                                 $callbackUrl
     * @param string                                 $signingKeyPath    Path to the signing key
     * @param string|null                            $signingPassphrase Passphrase for the signing key
     */
    public function createSigned(
        PayoutRequestData|array $data,
        string $callbackUrl,
        string $signingKeyPath,
        ?string $signingPassphrase = null,
    ): PayoutResponse {
        if (is_array($data)) {
            if (!isset($data['payerAlias']) && $this->defaultPayerAlias !== null) {
                $data['payerAlias'] = $this->defaultPayerAlias;
            }
            $data = PayoutRequestData::fromArray($data);
        }

        $signature = PayoutSignature::sign(
            $data->toApiPayload(),
            $signingKeyPath,
            $signingPassphrase,
        );

        return $this->create($data, $signature, $callbackUrl);
    }
}
