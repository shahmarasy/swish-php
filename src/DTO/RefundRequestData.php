<?php

declare(strict_types=1);

namespace Swish\DTO;

use InvalidArgumentException;
use Swish\Enum\Currency;

/**
 * Data required to create a Swish refund request.
 */
final readonly class RefundRequestData
{
    private const MAX_MESSAGE_LENGTH = 50;

    public function __construct(
        public string $originalPaymentReference,
        public string $callbackUrl,
        public string $payerAlias,
        public string $amount,
        public Currency $currency = Currency::SEK,
        public ?string $payerPaymentReference = null,
        public ?string $message = null,
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['originalPaymentReference'], $data['callbackUrl'], $data['payerAlias'], $data['amount'])) {
            throw new InvalidArgumentException(
                'RefundRequestData requires originalPaymentReference, callbackUrl, payerAlias, and amount',
            );
        }

        return new self(
            originalPaymentReference: (string) $data['originalPaymentReference'],
            callbackUrl: (string) $data['callbackUrl'],
            payerAlias: (string) $data['payerAlias'],
            amount: (string) $data['amount'],
            currency: isset($data['currency'])
                ? (is_string($data['currency']) ? Currency::from($data['currency']) : $data['currency'])
                : Currency::SEK,
            payerPaymentReference: isset($data['payerPaymentReference']) ? (string) $data['payerPaymentReference'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $payload = [
            'originalPaymentReference' => $this->originalPaymentReference,
            'callbackUrl' => $this->callbackUrl,
            'payerAlias' => $this->payerAlias,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
        ];

        if ($this->payerPaymentReference !== null) {
            $payload['payerPaymentReference'] = $this->payerPaymentReference;
        }

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        return $payload;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (trim($this->originalPaymentReference) === '') {
            throw new InvalidArgumentException('originalPaymentReference must not be empty');
        }

        if (!str_starts_with($this->callbackUrl, 'https://')) {
            throw new InvalidArgumentException('callbackUrl must use HTTPS scheme');
        }

        if (trim($this->payerAlias) === '') {
            throw new InvalidArgumentException('payerAlias must not be empty');
        }

        if (!is_numeric($this->amount) || (float) $this->amount <= 0) {
            throw new InvalidArgumentException(
                'amount must be a positive numeric value, got: ' . $this->amount,
            );
        }

        if ($this->message !== null && mb_strlen($this->message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException(
                'message must not exceed ' . self::MAX_MESSAGE_LENGTH . ' characters',
            );
        }
    }
}
