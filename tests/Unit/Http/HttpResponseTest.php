<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Http\HttpResponse;

final class HttpResponseTest extends TestCase
{
    #[Test]
    public function it_parses_json_body(): void
    {
        $response = new HttpResponse(
            statusCode: 200,
            headers: [],
            body: '{"id":"123","status":"PAID"}',
        );

        $json = $response->json();

        $this->assertIsArray($json);
        $this->assertSame('123', $json['id']);
        $this->assertSame('PAID', $json['status']);
    }

    #[Test]
    public function it_returns_null_for_empty_body(): void
    {
        $response = new HttpResponse(200, [], '');
        $this->assertNull($response->json());
    }

    #[Test]
    public function it_returns_null_for_invalid_json(): void
    {
        $response = new HttpResponse(200, [], 'not-json{{{');
        $this->assertNull($response->json());
    }

    #[Test]
    public function it_returns_null_for_scalar_json(): void
    {
        $response = new HttpResponse(200, [], '"just a string"');
        $this->assertNull($response->json());
    }

    #[Test]
    public function it_gets_header_case_insensitive(): void
    {
        $response = new HttpResponse(
            statusCode: 201,
            headers: [
                'Location' => ['/some/url'],
                'Content-Type' => ['application/json'],
            ],
            body: '',
        );

        $this->assertSame('/some/url', $response->header('location'));
        $this->assertSame('/some/url', $response->header('Location'));
        $this->assertSame('application/json', $response->header('content-type'));
        $this->assertNull($response->header('X-Missing'));
    }

    #[Test]
    public function it_checks_success_status(): void
    {
        $this->assertTrue((new HttpResponse(200, [], ''))->isSuccessful());
        $this->assertTrue((new HttpResponse(201, [], ''))->isSuccessful());
        $this->assertTrue((new HttpResponse(204, [], ''))->isSuccessful());
        $this->assertFalse((new HttpResponse(301, [], ''))->isSuccessful());
        $this->assertFalse((new HttpResponse(400, [], ''))->isSuccessful());
        $this->assertFalse((new HttpResponse(500, [], ''))->isSuccessful());
    }
}
