<?php

declare(strict_types=1);

namespace Swish\DTO;

use InvalidArgumentException;
use Swish\Enum\Currency;

/**
 * Data required to create a Swish payment request.
 *
 * If payerAlias is set, the payment is treated as E-Commerce.
 * If payerAlias is omitted, the payment is treated as M-Commerce.
 */
final readonly class PaymentRequestData
{
    /**
     * Maximum allowed message length per Swish API spec.
     */
    private const MAX_MESSAGE_LENGTH = 50;

    public function __construct(
        public string $payeeAlias,
        public string $amount,
        public string $callbackUrl,
        public Currency $currency = Currency::SEK,
        public ?string $payerAlias = null,
        public ?string $payeePaymentReference = null,
        public ?string $message = null,
        public ?string $payerSSN = null,
        public ?int $ageLimit = null,
    ) {
        $this->validate();
    }

    /**
     * Create from an associative array (developer convenience).
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['payeeAlias'], $data['amount'], $data['callbackUrl'])) {
            throw new InvalidArgumentException(
                'PaymentRequestData requires payeeAlias, amount, and callbackUrl',
            );
        }

        return new self(
            payeeAlias: (string) $data['payeeAlias'],
            amount: (string) $data['amount'],
            callbackUrl: (string) $data['callbackUrl'],
            currency: isset($data['currency'])
                ? (is_string($data['currency']) ? Currency::from($data['currency']) : $data['currency'])
                : Currency::SEK,
            payerAlias: isset($data['payerAlias']) ? (string) $data['payerAlias'] : null,
            payeePaymentReference: isset($data['payeePaymentReference']) ? (string) $data['payeePaymentReference'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
            payerSSN: isset($data['payerSSN']) ? (string) $data['payerSSN'] : null,
            ageLimit: isset($data['ageLimit']) ? (int) $data['ageLimit'] : null,
        );
    }

    /**
     * Convert to the array format expected by the Swish API.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $payload = [
            'payeeAlias' => $this->payeeAlias,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'callbackUrl' => $this->callbackUrl,
        ];

        if ($this->payerAlias !== null) {
            $payload['payerAlias'] = $this->payerAlias;
        }

        if ($this->payeePaymentReference !== null) {
            $payload['payeePaymentReference'] = $this->payeePaymentReference;
        }

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        if ($this->payerSSN !== null) {
            $payload['payerSSN'] = $this->payerSSN;
        }

        if ($this->ageLimit !== null) {
            $payload['ageLimit'] = $this->ageLimit;
        }

        return $payload;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (trim($this->payeeAlias) === '') {
            throw new InvalidArgumentException('payeeAlias must not be empty');
        }

        // Validate amount is a positive numeric value
        if (!is_numeric($this->amount) || (float) $this->amount <= 0) {
            throw new InvalidArgumentException(
                'amount must be a positive numeric value, got: ' . $this->amount,
            );
        }

        // Validate callbackUrl is HTTPS (Swish requires HTTPS)
        if (!str_starts_with($this->callbackUrl, 'https://')) {
            throw new InvalidArgumentException(
                'callbackUrl must use HTTPS scheme',
            );
        }

        // Validate message length (Swish has a 50-char limit)
        if ($this->message !== null && mb_strlen($this->message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException(
                'message must not exceed ' . self::MAX_MESSAGE_LENGTH . ' characters',
            );
        }

        // Validate age limit if set
        if ($this->ageLimit !== null && ($this->ageLimit < 1 || $this->ageLimit > 99)) {
            throw new InvalidArgumentException('ageLimit must be between 1 and 99');
        }
    }
}
