<?php

declare(strict_types=1);

namespace Swish\DTO;

use DateTimeImmutable;
use Swish\Enum\PayoutStatus;

/**
 * Represents a Swish payout response / payout object.
 */
final readonly class PayoutResponse
{
    public function __construct(
        public string $payoutInstructionUUID,
        public ?string $paymentReference,
        public ?string $payerPaymentReference,
        public ?string $callbackUrl,
        public string $payerAlias,
        public string $payeeAlias,
        public ?string $payeeSSN,
        public string $amount,
        public string $currency,
        public ?string $message,
        public PayoutStatus $status,
        public ?DateTimeImmutable $dateCreated,
        public ?DateTimeImmutable $datePaid,
        public ?string $errorCode,
        public ?string $errorMessage,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            payoutInstructionUUID: (string) ($data['payoutInstructionUUID'] ?? ''),
            paymentReference: $data['paymentReference'] ?? null,
            payerPaymentReference: $data['payerPaymentReference'] ?? null,
            callbackUrl: $data['callbackUrl'] ?? null,
            payerAlias: (string) ($data['payerAlias'] ?? ''),
            payeeAlias: (string) ($data['payeeAlias'] ?? ''),
            payeeSSN: $data['payeeSSN'] ?? null,
            amount: (string) ($data['amount'] ?? '0'),
            currency: (string) ($data['currency'] ?? 'SEK'),
            message: $data['message'] ?? null,
            status: PayoutStatus::from((string) ($data['status'] ?? 'CREATED')),
            dateCreated: isset($data['dateCreated'])
                ? new DateTimeImmutable($data['dateCreated'])
                : null,
            datePaid: isset($data['datePaid'])
                ? new DateTimeImmutable($data['datePaid'])
                : null,
            errorCode: $data['errorCode'] ?? null,
            errorMessage: $data['errorMessage'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payoutInstructionUUID' => $this->payoutInstructionUUID,
            'paymentReference' => $this->paymentReference,
            'payerPaymentReference' => $this->payerPaymentReference,
            'callbackUrl' => $this->callbackUrl,
            'payerAlias' => $this->payerAlias,
            'payeeAlias' => $this->payeeAlias,
            'payeeSSN' => $this->payeeSSN,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'status' => $this->status->value,
            'dateCreated' => $this->dateCreated?->format('c'),
            'datePaid' => $this->datePaid?->format('c'),
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
