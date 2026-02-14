<?php

declare(strict_types=1);

namespace Swish\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swish\Client\SwishConfig;
use Swish\Exception\ApiException;
use Swish\Exception\AuthenticationException;
use Swish\Exception\NetworkException;
use Swish\Exception\SwishException;
use Swish\Exception\ValidationException;

/**
 * Guzzle-based HTTP client with mTLS, retry logic, and PSR-3 logging.
 *
 * Security notes:
 *  - TLS 1.2+ is enforced via CURLOPT_SSLVERSION
 *  - SSL verification is always enabled (never disabled)
 *  - Request/response bodies are never logged (PII protection)
 */
final class GuzzleHttpClient implements HttpClientInterface
{
    private readonly Client $client;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly SwishConfig $config,
        ?LoggerInterface $logger = null,
        ?Client $client = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->client = $client ?? $this->createClient();
    }

    public function send(string $method, string $uri, array $options = []): HttpResponse
    {
        $fullUri = $this->config->getBaseUrl() . $uri;

        $this->logger->debug('Swish API request', [
            'method' => $method,
            'uri' => $fullUri,
        ]);

        try {
            $response = $this->client->request($method, $fullUri, $options);

            $httpResponse = new HttpResponse(
                statusCode: $response->getStatusCode(),
                headers: $response->getHeaders(),
                body: (string) $response->getBody(),
            );

            $this->logger->debug('Swish API response', [
                'status' => $httpResponse->statusCode,
            ]);

            return $httpResponse;
        } catch (ConnectException $e) {
            $this->logger->error('Swish API connection failure', [
                'uri' => $fullUri,
                'error' => $e->getMessage(),
            ]);

            throw new NetworkException(
                'Failed to connect to Swish API: ' . $e->getMessage(),
                0,
                $e,
            );
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response === null) {
                throw new NetworkException(
                    'Swish API request failed: ' . $e->getMessage(),
                    0,
                    $e,
                );
            }

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $errorData = [];

            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                // Swish returns errors as an array of error objects
                $errorData = is_array($decoded) ? (isset($decoded[0]) ? $decoded : [$decoded]) : [];
            } catch (\JsonException) {
                // Body is not valid JSON — use raw status code only
            }

            // Log status code only, never the response body (may contain PII)
            $this->logger->warning('Swish API error', [
                'status' => $statusCode,
                'uri' => $fullUri,
            ]);

            throw $this->mapException($statusCode, $errorData, $e);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $errorData
     */
    private function mapException(
        int $statusCode,
        array $errorData,
        \Throwable $previous,
    ): SwishException {
        $message = "Swish API error (HTTP {$statusCode})";

        if ($errorData !== []) {
            $firstError = $errorData[0];
            $errorMsg = $firstError['errorMessage'] ?? $firstError['errorCode'] ?? '';
            if ($errorMsg !== '') {
                $message .= ': ' . $errorMsg;
            }
        }

        return match (true) {
            $statusCode === 401, $statusCode === 403
                => AuthenticationException::fromErrorArray($message, $statusCode, $errorData, $previous),
            $statusCode === 422
                => ValidationException::fromErrorArray($message, $statusCode, $errorData, $previous),
            default
                => ApiException::fromErrorArray($message, $statusCode, $errorData, $previous),
        };
    }

    private function createClient(): Client
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        if ($this->config->maxRetries > 0) {
            $stack->push(Middleware::retry(
                $this->retryDecider(),
                $this->retryDelay(),
            ));
        }

        // Guzzle 'cert' handles PEM cert (optionally with passphrase)
        // 'ssl_key' handles the private key separately
        $certOption = $this->config->passphrase !== null
            ? [$this->config->certPath, $this->config->passphrase]
            : $this->config->certPath;

        return new Client([
            'handler' => $stack,
            'cert' => $certOption,
            'ssl_key' => $this->config->keyPath,
            'verify' => $this->config->caPath ?? true,
            'timeout' => $this->config->timeout,
            'connect_timeout' => $this->config->connectTimeout,
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
            'http_errors' => true,
            // Do NOT set default Content-Type here — let each request specify it
            // PATCH requests use application/json-patch+json, while PUT/POST use application/json
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    private function retryDecider(): callable
    {
        $maxRetries = $this->config->maxRetries;
        $logger = $this->logger;

        return static function (
            int $retries,
            Request $request,
            ?Response $response = null,
            ?\Throwable $exception = null,
        ) use ($maxRetries, $logger): bool {
            if ($retries >= $maxRetries) {
                return false;
            }

            // Retry on connection errors
            if ($exception instanceof ConnectException) {
                $logger->info('Swish retry on connection error', ['attempt' => $retries + 1]);
                return true;
            }

            // Retry on server errors (500, 502, 503, 504) and rate limit (429)
            if ($response !== null) {
                $status = $response->getStatusCode();
                if (in_array($status, [429, 500, 502, 503, 504], true)) {
                    $logger->info('Swish retry on HTTP ' . $status, ['attempt' => $retries + 1]);
                    return true;
                }
            }

            return false;
        };
    }

    private function retryDelay(): callable
    {
        return static function (int $retries, Response $response = null): int {
            // Respect Retry-After header on 429 responses, capped at 30s
            // to prevent a malicious server from holding client threads
            if ($response !== null && $response->getStatusCode() === 429) {
                $retryAfter = $response->getHeaderLine('Retry-After');
                if ($retryAfter !== '' && is_numeric($retryAfter)) {
                    $delaySeconds = min((float) $retryAfter, 30.0);
                    return (int) ($delaySeconds * 1000);
                }
            }

            // Exponential backoff with jitter: base * 2^retries + random(0-500ms)
            $baseDelayMs = 1000 * (2 ** $retries);
            $jitterMs = random_int(0, 500);

            return $baseDelayMs + $jitterMs;
        };
    }
}
