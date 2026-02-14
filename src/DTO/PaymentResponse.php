<?php

declare(strict_types=1);

namespace Swish\DTO;

use DateTimeImmutable;
use Swish\Enum\PaymentStatus;

/**
 * Represents a Swish payment response / payment object.
 */
final readonly class PaymentResponse
{
    public function __construct(
        public string $id,
        public ?string $paymentReference,
        public ?string $payeePaymentReference,
        public ?string $callbackUrl,
        public ?string $payerAlias,
        public string $payeeAlias,
        public string $amount,
        public string $currency,
        public ?string $message,
        public PaymentStatus $status,
        public ?DateTimeImmutable $dateCreated,
        public ?DateTimeImmutable $datePaid,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?string $paymentRequestToken,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['instructionUUID'] ?? ''),
            paymentReference: $data['paymentReference'] ?? null,
            payeePaymentReference: $data['payeePaymentReference'] ?? null,
            callbackUrl: $data['callbackUrl'] ?? null,
            payerAlias: $data['payerAlias'] ?? null,
            payeeAlias: (string) ($data['payeeAlias'] ?? ''),
            amount: (string) ($data['amount'] ?? '0'),
            currency: (string) ($data['currency'] ?? 'SEK'),
            message: $data['message'] ?? null,
            status: PaymentStatus::from((string) ($data['status'] ?? 'CREATED')),
            dateCreated: isset($data['dateCreated'])
                ? new DateTimeImmutable($data['dateCreated'])
                : null,
            datePaid: isset($data['datePaid'])
                ? new DateTimeImmutable($data['datePaid'])
                : null,
            errorCode: $data['errorCode'] ?? null,
            errorMessage: $data['errorMessage'] ?? null,
            paymentRequestToken: $data['paymentRequestToken'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'paymentReference' => $this->paymentReference,
            'payeePaymentReference' => $this->payeePaymentReference,
            'callbackUrl' => $this->callbackUrl,
            'payerAlias' => $this->payerAlias,
            'payeeAlias' => $this->payeeAlias,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'status' => $this->status->value,
            'dateCreated' => $this->dateCreated?->format('c'),
            'datePaid' => $this->datePaid?->format('c'),
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'paymentRequestToken' => $this->paymentRequestToken,
        ];
    }
}
