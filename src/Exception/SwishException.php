<?php

declare(strict_types=1);

namespace Swish\Exception;

use RuntimeException;
use Swish\DTO\SwishError;
use Throwable;

/**
 * Base exception for all Swish SDK errors.
 */
class SwishException extends RuntimeException
{
    /** @var SwishError[] */
    private readonly array $errors;

    /**
     * @param SwishError[] $errors
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $errors = [],
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @return SwishError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create from an array of raw error data returned by the Swish API.
     *
     * @param array<int, array{errorCode?: string, errorMessage?: string, additionalInformation?: string}> $errorData
     */
    public static function fromErrorArray(
        string $message,
        int $httpStatusCode,
        array $errorData = [],
        ?Throwable $previous = null,
    ): static {
        $errors = array_map(
            static fn(array $item): SwishError => SwishError::fromArray($item),
            $errorData,
        );

        return new static($message, $httpStatusCode, $previous, $errors);
    }
}
