<?php

declare(strict_types=1);

namespace Swish\Http;

/**
 * Immutable HTTP response value object.
 */
final readonly class HttpResponse
{
    /**
     * @param array<string, string[]> $headers
     */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
    }

    /**
     * Decode the response body as JSON.
     *
     * Returns null if the body is empty or not valid JSON.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        if ($this->body === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get a single header value (first value if multiple).
     * Header name lookup is case-insensitive per HTTP spec.
     */
    public function header(string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $lower && $values !== []) {
                return $values[0];
            }
        }

        return null;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
