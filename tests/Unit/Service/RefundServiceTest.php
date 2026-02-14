<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Enum\RefundStatus;
use Swish\Http\HttpClientInterface;
use Swish\Http\HttpResponse;
use Swish\Service\RefundService;

final class RefundServiceTest extends TestCase
{
    #[Test]
    public function it_creates_a_refund(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('send')
            ->with(
                'PUT',
                $this->stringStartsWith('/swish-cpcapi/api/v2/refunds/'),
                $this->callback(function (array $options): bool {
                    $this->assertSame('application/json', $options['headers']['Content-Type']);
                    $json = $options['json'];
                    $this->assertSame('ORIG-PAY-REF', $json['originalPaymentReference']);
                    $this->assertSame('50.00', $json['amount']);
                    $this->assertSame('SEK', $json['currency']);
                    return true;
                }),
            )
            ->willReturn(new HttpResponse(201, [], ''));

        $service = new RefundService($httpClient);

        $refund = $service->create([
            'originalPaymentReference' => 'ORIG-PAY-REF',
            'callbackUrl' => 'https://example.com/refund-cb',
            'payerAlias' => '1231181189',
            'amount' => '50.00',
        ]);

        $this->assertNotEmpty($refund->id);
        $this->assertSame(RefundStatus::CREATED, $refund->status);
        $this->assertSame('ORIG-PAY-REF', $refund->originalPaymentReference);
        $this->assertSame('50.00', $refund->amount);
    }

    #[Test]
    public function it_retrieves_a_refund(): void
    {
        $responseBody = json_encode([
            'id' => 'REFUND-UUID-001',
            'paymentReference' => 'REF-PAY-001',
            'originalPaymentReference' => 'ORIG-PAY-001',
            'payerAlias' => '1231181189',
            'payeeAlias' => '46712345678',
            'amount' => '25.00',
            'currency' => 'SEK',
            'status' => 'PAID',
            'dateCreated' => '2026-01-15T10:00:00Z',
            'datePaid' => '2026-01-15T10:02:00Z',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects($this->once())
            ->method('send')
            ->with('GET', '/swish-cpcapi/api/v1/refunds/REFUND-UUID-001')
            ->willReturn(new HttpResponse(200, [], $responseBody));

        $service = new RefundService($httpClient);
        $refund = $service->get('REFUND-UUID-001');

        $this->assertSame('REFUND-UUID-001', $refund->id);
        $this->assertSame(RefundStatus::PAID, $refund->status);
        $this->assertSame('25.00', $refund->amount);
        $this->assertSame('ORIG-PAY-001', $refund->originalPaymentReference);
    }

    #[Test]
    public function it_throws_on_empty_id(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new RefundService($httpClient);

        $this->expectException(\InvalidArgumentException::class);
        $service->get('');
    }
}
