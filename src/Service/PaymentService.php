<?php

declare(strict_types=1);

namespace Swish\Service;

use InvalidArgumentException;
use Swish\DTO\PaymentRequestData;
use Swish\DTO\PaymentResponse;
use Swish\Http\HttpClientInterface;
use Swish\Utils\IdGenerator;

/**
 * Handles Swish payment request operations.
 *
 * Endpoints:
 *  - Create: PUT  /swish-cpcapi/api/v2/paymentrequests/{uuid}
 *  - Get:    GET  /swish-cpcapi/api/v1/paymentrequests/{uuid}
 *  - Cancel: PATCH /swish-cpcapi/api/v1/paymentrequests/{uuid}
 */
final class PaymentService
{
    private const CREATE_ENDPOINT = '/swish-cpcapi/api/v2/paymentrequests/';
    private const GET_ENDPOINT = '/swish-cpcapi/api/v1/paymentrequests/';

    /**
     * @param HttpClientInterface $httpClient HTTP client for API calls
     * @param string|null $defaultPayeeAlias Default payee alias from config (auto-injected by SwishClient)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $defaultPayeeAlias = null,
    ) {
    }

    /**
     * Create a new payment request.
     *
     * The payeeAlias from config is automatically used if not specified in the request data.
     *
     * @param PaymentRequestData|array<string, mixed> $data Payment request data
     * @param string|null $instructionUUID Custom instruction UUID (auto-generated if null)
     *
     * @return PaymentResponse Contains the created payment with location info
     *
     * @throws InvalidArgumentException
     */
    public function create(PaymentRequestData|array $data, ?string $instructionUUID = null): PaymentResponse
    {
        if (is_array($data)) {
            // Inject default payeeAlias if not explicitly provided
            if (!isset($data['payeeAlias']) && $this->defaultPayeeAlias !== null) {
                $data['payeeAlias'] = $this->defaultPayeeAlias;
            }
            $data = PaymentRequestData::fromArray($data);
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

        // On 201 Created, the response typically has a Location header and possibly a token
        $token = $response->header('PaymentRequestToken');
        $body = $response->json() ?? [];

        // Build response from request data + any API-returned data (API takes precedence)
        $responseData = array_merge(
            array_filter([
                'id' => $uuid,
                'status' => 'CREATED',
                'payeeAlias' => $data->payeeAlias,
                'amount' => $data->amount,
                'currency' => $data->currency->value,
                'callbackUrl' => $data->callbackUrl,
                'payerAlias' => $data->payerAlias,
                'payeePaymentReference' => $data->payeePaymentReference,
                'message' => $data->message,
                'paymentRequestToken' => $token,
            ], static fn(mixed $v): bool => $v !== null),
            $body,
        );

        return PaymentResponse::fromArray($responseData);
    }

    /**
     * Retrieve a payment request by its ID.
     *
     * @param string $id The instruction UUID used when creating the payment
     *
     * @throws InvalidArgumentException
     */
    public function get(string $id): PaymentResponse
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Payment ID must not be empty');
        }

        $response = $this->httpClient->send('GET', self::GET_ENDPOINT . $id);

        return PaymentResponse::fromArray($response->json() ?? []);
    }

    /**
     * Cancel a payment request.
     *
     * Only possible if the payment has not yet been processed (status CREATED).
     * Uses JSON Patch format as required by the Swish API.
     *
     * @param string $id The instruction UUID of the payment to cancel
     *
     * @throws InvalidArgumentException
     */
    public function cancel(string $id): PaymentResponse
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Payment ID must not be empty');
        }

        $this->httpClient->send('PATCH', self::GET_ENDPOINT . $id, [
            'json' => [
                ['op' => 'replace', 'path' => '/status', 'value' => 'cancelled'],
            ],
            'headers' => [
                'Content-Type' => 'application/json-patch+json',
            ],
        ]);

        // Swish PATCH returns no body on success. Fetch the actual state.
        return $this->get($id);
    }
}
