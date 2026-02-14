<?php

declare(strict_types=1);

namespace Swish\DTO;

use DateTimeImmutable;

/**
 * Represents parsed callback/webhook data received from Swish.
 *
 * This is the payload Swish POSTs to your callbackUrl.
 */
final readonly class CallbackData
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $paymentReference,
        public ?string $payeePaymentReference,
        public ?string $payerPaymentReference,
        public ?string $payerAlias,
        public ?string $payeeAlias,
        public ?string $amount,
        public ?string $currency,
        public ?string $message,
        public ?string $errorCode,
        public ?string $errorMessage,
        public ?string $originalPaymentReference,
        public ?DateTimeImmutable $dateCreated,
        public ?DateTimeImmutable $datePaid,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? $data['instructionUUID'] ?? $data['payoutInstructionUUID'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            paymentReference: $data['paymentReference'] ?? null,
            payeePaymentReference: $data['payeePaymentReference'] ?? null,
            payerPaymentReference: $data['payerPaymentReference'] ?? null,
            payerAlias: $data['payerAlias'] ?? null,
            payeeAlias: $data['payeeAlias'] ?? null,
            amount: isset($data['amount']) ? (string) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            message: $data['message'] ?? null,
            errorCode: $data['errorCode'] ?? null,
            errorMessage: $data['errorMessage'] ?? null,
            originalPaymentReference: $data['originalPaymentReference'] ?? null,
            dateCreated: isset($data['dateCreated'])
                ? new DateTimeImmutable($data['dateCreated'])
                : null,
            datePaid: isset($data['datePaid'])
                ? new DateTimeImmutable($data['datePaid'])
                : null,
        );
    }

    public function isPaid(): bool
    {
        return $this->status === 'PAID';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'DECLINED';
    }

    public function isError(): bool
    {
        return $this->status === 'ERROR';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    public function isRefund(): bool
    {
        return $this->originalPaymentReference !== null;
    }
}
