<?php

declare(strict_types=1);

namespace Swish\Http;

use Swish\Exception\SwishException;

/**
 * Interface for HTTP clients used by the Swish SDK.
 *
 * Allows replacing the default Guzzle client with any implementation.
 */
interface HttpClientInterface
{
    /**
     * Send an HTTP request.
     *
     * @param string               $method  HTTP method (GET, PUT, POST, PATCH, DELETE)
     * @param string               $uri     Relative URI path (base URL is prepended by the implementation)
     * @param array<string, mixed> $options Request options (json, headers, etc.)
     *
     * @throws SwishException On any API or network error
     */
    public function send(string $method, string $uri, array $options = []): HttpResponse;
}
