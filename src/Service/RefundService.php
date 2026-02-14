<?php

declare(strict_types=1);

namespace Swish\Service;

use InvalidArgumentException;
use Swish\DTO\RefundRequestData;
use Swish\DTO\RefundResponse;
use Swish\Http\HttpClientInterface;
use Swish\Utils\IdGenerator;

/**
 * Handles Swish refund operations.
 *
 * Endpoints:
 *  - Create: PUT /swish-cpcapi/api/v2/refunds/{uuid}
 *  - Get:    GET /swish-cpcapi/api/v1/refunds/{uuid}
 */
final class RefundService
{
    private const CREATE_ENDPOINT = '/swish-cpcapi/api/v2/refunds/';
    private const GET_ENDPOINT = '/swish-cpcapi/api/v1/refunds/';

    /**
     * @param HttpClientInterface $httpClient HTTP client for API calls
     * @param string|null $defaultPayerAlias Default payer alias from config (for refunds, the merchant is the payer)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $defaultPayerAlias = null,
    ) {
    }

    /**
     * Create a refund for a previous payment.
     *
     * The payerAlias (your merchant number) is automatically injected from config if not specified.
     *
     * @param RefundRequestData|array<string, mixed> $data Refund request data
     * @param string|null $instructionUUID Custom instruction UUID (auto-generated if null)
     *
     * @return RefundResponse The created refund
     *
     * @throws InvalidArgumentException
     */
    public function create(RefundRequestData|array $data, ?string $instructionUUID = null): RefundResponse
    {
        if (is_array($data)) {
            // For refunds, the merchant is the payer (refunding back to the customer)
            if (!isset($data['payerAlias']) && $this->defaultPayerAlias !== null) {
                $data['payerAlias'] = $this->defaultPayerAlias;
            }
            $data = RefundRequestData::fromArray($data);
        }

        $uuid = $instructionUUID ?? IdGenerator::generate();

        if (trim($uuid) === '') {
            throw new InvalidArgumentException('instructionUUID must not be empty');
        }

        $uri = self::CREATE_ENDPOINT . $uuid;

        $response = $this->httpClient->send('PUT', $uri, [
            'json' => $data->toApiPayload(),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $body = $response->json() ?? [];

        $responseData = array_merge(
            array_filter([
                'id' => $uuid,
                'status' => 'CREATED',
                'originalPaymentReference' => $data->originalPaymentReference,
                'payerAlias' => $data->payerAlias,
                'amount' => $data->amount,
                'currency' => $data->currency->value,
                'callbackUrl' => $data->callbackUrl,
                'payerPaymentReference' => $data->payerPaymentReference,
                'message' => $data->message,
            ], static fn(mixed $v): bool => $v !== null),
            $body,
        );

        return RefundResponse::fromArray($responseData);
    }

    /**
     * Retrieve a refund by its instruction UUID.
     *
     * @param string $id The instruction UUID used when creating the refund
     *
     * @throws InvalidArgumentException
     */
    public function get(string $id): RefundResponse
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Refund ID must not be empty');
        }

        $response = $this->httpClient->send('GET', self::GET_ENDPOINT . $id);

        return RefundResponse::fromArray($response->json() ?? []);
    }
}
