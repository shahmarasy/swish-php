<?php

declare(strict_types=1);

namespace Swish\DTO;

use Swish\Enum\Currency;
use Swish\Enum\PayoutType;

/**
 * Data required to create a Swish payout.
 *
 * Payouts require a signed payload — see PayoutSignature.
 */
final readonly class PayoutRequestData
{
    public function __construct(
        public string $payoutInstructionUUID,
        public string $payerPaymentReference,
        public string $payerAlias,
        public string $payeeAlias,
        public string $payeeSSN,
        public string $amount,
        public string $instructionDate,
        public string $signingCertificateSerialNumber,
        public Currency $currency = Currency::SEK,
        public PayoutType $payoutType = PayoutType::PAYOUT,
        public ?string $message = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            payoutInstructionUUID: (string) $data['payoutInstructionUUID'],
            payerPaymentReference: (string) $data['payerPaymentReference'],
            payerAlias: (string) $data['payerAlias'],
            payeeAlias: (string) $data['payeeAlias'],
            payeeSSN: (string) $data['payeeSSN'],
            amount: (string) $data['amount'],
            instructionDate: (string) $data['instructionDate'],
            signingCertificateSerialNumber: (string) $data['signingCertificateSerialNumber'],
            currency: isset($data['currency'])
                ? (is_string($data['currency']) ? Currency::from($data['currency']) : $data['currency'])
                : Currency::SEK,
            payoutType: isset($data['payoutType'])
                ? (is_string($data['payoutType']) ? PayoutType::from($data['payoutType']) : $data['payoutType'])
                : PayoutType::PAYOUT,
            message: $data['message'] ?? null,
        );
    }

    /**
     * Convert to the payload format for signing and sending.
     *
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        $payload = [
            'payoutInstructionUUID' => $this->payoutInstructionUUID,
            'payerPaymentReference' => $this->payerPaymentReference,
            'payerAlias' => $this->payerAlias,
            'payeeAlias' => $this->payeeAlias,
            'payeeSSN' => $this->payeeSSN,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'payoutType' => $this->payoutType->value,
            'instructionDate' => $this->instructionDate,
            'signingCertificateSerialNumber' => $this->signingCertificateSerialNumber,
        ];

        if ($this->message !== null) {
            $payload['message'] = $this->message;
        }

        return $payload;
    }
}
