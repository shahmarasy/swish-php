<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\DTO\PaymentRequestData;
use Swish\Enum\PaymentStatus;
use Swish\Http\HttpClientInterface;
use Swish\Http\HttpResponse;
use Swish\Service\PaymentService;

final class PaymentServiceTest extends TestCase
{
    #[Test]
    public function it_creates_a_payment_request(): void
    {
        $mockResponse = new HttpResponse(
            statusCode: 201,
            headers: [
                'Location' => ['/swish-cpcapi/api/v1/paymentrequests/ABC123'],
                'PaymentRequestToken' => ['test-token-xyz'],
            ],
            body: '',
        );

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('send')
            ->with(
                'PUT',
                $this->stringStartsWith('/swish-cpcapi/api/v2/paymentrequests/'),
                $this->callback(function (array $options): bool {
                    $this->assertArrayHasKey('json', $options);
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertSame('application/json', $options['headers']['Content-Type']);
                    $json = $options['json'];
                    $this->assertSame('1231181189', $json['payeeAlias']);
                    $this->assertSame('100.00', $json['amount']);
                    $this->assertSame('SEK', $json['currency']);
                    return true;
                }),
            )
            ->willReturn($mockResponse);

        $service = new PaymentService($httpClient);

        $payment = $service->create([
            'payeeAlias' => '1231181189',
            'payerAlias' => '46712345678',
            'amount' => '100.00',
            'callbackUrl' => 'https://example.com/callback',
        ]);

        $this->assertNotEmpty($payment->id);
        $this->assertSame(PaymentStatus::CREATED, $payment->status);
        $this->assertSame('1231181189', $payment->payeeAlias);
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame('test-token-xyz', $payment->paymentRequestToken);
    }

    #[Test]
    public function it_creates_payment_with_custom_uuid(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('send')
            ->with(
                'PUT',
                '/swish-cpcapi/api/v2/paymentrequests/CUSTOM123UUID',
                $this->anything(),
            )
            ->willReturn(new HttpResponse(201, [], ''));

        $service = new PaymentService($httpClient);

        $payment = $service->create(
            new PaymentRequestData(
                payeeAlias: '1231181189',
                amount: '50.00',
                callbackUrl: 'https://example.com/cb',
            ),
            instructionUUID: 'CUSTOM123UUID',
        );

        $this->assertSame('CUSTOM123UUID', $payment->id);
    }

    #[Test]
    public function it_retrieves_a_payment(): void
    {
        $responseBody = json_encode([
            'id' => 'ABC123',
            'paymentReference' => 'PAY-REF-001',
            'payeeAlias' => '1231181189',
            'payerAlias' => '46712345678',
            'amount' => '100.00',
            'currency' => 'SEK',
            'status' => 'PAID',
            'dateCreated' => '2026-01-15T10:00:00Z',
            'datePaid' => '2026-01-15T10:01:00Z',
            'message' => 'Test',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('send')
            ->with('GET', '/swish-cpcapi/api/v1/paymentrequests/ABC123')
            ->willReturn(new HttpResponse(200, [], $responseBody));

        $service = new PaymentService($httpClient);
        $payment = $service->get('ABC123');

        $this->assertSame('ABC123', $payment->id);
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame('PAY-REF-001', $payment->paymentReference);
        $this->assertSame('100.00', $payment->amount);
        $this->assertNotNull($payment->datePaid);
    }

    #[Test]
    public function it_cancels_a_payment_with_followup_get(): void
    {
        // Cancel sends PATCH, then does a follow-up GET
        $getResponseBody = json_encode([
            'id' => 'ABC123',
            'status' => 'CANCELLED',
            'payeeAlias' => '1231181189',
            'amount' => '100.00',
            'currency' => 'SEK',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function (string $method, string $uri, array $options = []) use ($getResponseBody) {
                if ($method === 'PATCH') {
                    $this->assertArrayHasKey('json', $options);
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertSame('application/json-patch+json', $options['headers']['Content-Type']);
                    return new HttpResponse(200, [], '');
                }

                // GET follow-up
                $this->assertSame('GET', $method);
                return new HttpResponse(200, [], $getResponseBody);
            });

        $service = new PaymentService($httpClient);
        $result = $service->cancel('ABC123');

        $this->assertSame('ABC123', $result->id);
        $this->assertSame(PaymentStatus::CANCELLED, $result->status);
        $this->assertSame('1231181189', $result->payeeAlias);
    }

    #[Test]
    public function it_throws_on_empty_id_for_get(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new PaymentService($httpClient);

        $this->expectException(\InvalidArgumentException::class);
        $service->get('');
    }

    #[Test]
    public function it_throws_on_empty_id_for_cancel(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new PaymentService($httpClient);

        $this->expectException(\InvalidArgumentException::class);
        $service->cancel('  ');
    }
}
