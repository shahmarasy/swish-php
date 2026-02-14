<?php

declare(strict_types=1);

namespace Swish\DTO;

/**
 * Represents a single Swish API error.
 */
final readonly class SwishError
{
    public function __construct(
        public string $errorCode,
        public string $errorMessage,
        public string $additionalInformation = '',
    ) {
    }

    /**
     * @param array{errorCode?: string, errorMessage?: string, additionalInformation?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            errorCode: $data['errorCode'] ?? '',
            errorMessage: $data['errorMessage'] ?? '',
            additionalInformation: $data['additionalInformation'] ?? '',
        );
    }

    /**
     * @return array{errorCode: string, errorMessage: string, additionalInformation: string}
     */
    public function toArray(): array
    {
        return [
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'additionalInformation' => $this->additionalInformation,
        ];
    }
}
