<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\DTO\CallbackData;
use Swish\Exception\ValidationException;
use Swish\Service\CallbackService;

final class CallbackServiceTest extends TestCase
{
    private CallbackService $service;

    protected function setUp(): void
    {
        $this->service = new CallbackService();
    }

    #[Test]
    public function it_parses_payment_callback(): void
    {
        $json = json_encode([
            'id' => 'ABC123',
            'status' => 'PAID',
            'payeeAlias' => '1231181189',
            'payerAlias' => '46712345678',
            'amount' => '100.00',
            'currency' => 'SEK',
            'dateCreated' => '2026-01-15T10:00:00Z',
            'datePaid' => '2026-01-15T10:01:00Z',
        ]);

        $callback = $this->service->parse($json);

        $this->assertSame('ABC123', $callback->id);
        $this->assertSame('PAID', $callback->status);
        $this->assertTrue($callback->isPaid());
        $this->assertFalse($callback->isRefund());
    }

    #[Test]
    public function it_parses_refund_callback(): void
    {
        $json = json_encode([
            'id' => 'REFUND123',
            'status' => 'PAID',
            'originalPaymentReference' => 'ORIG-PAY-001',
            'amount' => '50.00',
            'currency' => 'SEK',
        ]);

        $callback = $this->service->parse($json);

        $this->assertTrue($callback->isRefund());
        $this->assertSame('ORIG-PAY-001', $callback->originalPaymentReference);
    }

    #[Test]
    public function it_parses_error_callback(): void
    {
        $json = json_encode([
            'id' => 'ERR123',
            'status' => 'ERROR',
            'errorCode' => 'PA01',
            'errorMessage' => 'Something failed',
        ]);

        $callback = $this->service->parse($json);

        $this->assertTrue($callback->isError());
        $this->assertSame('PA01', $callback->errorCode);
    }

    #[Test]
    public function it_rejects_invalid_json(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid callback JSON');
        $this->service->parse('not json{{{');
    }

    #[Test]
    public function it_parses_from_array(): void
    {
        $callback = $this->service->parseFromArray([
            'id' => 'ARRAY123',
            'status' => 'CANCELLED',
        ]);

        $this->assertSame('ARRAY123', $callback->id);
        $this->assertTrue($callback->isCancelled());
    }

    // --- Security tests ---

    #[Test]
    public function it_rejects_oversized_payload(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds maximum');

        $oversized = str_repeat('A', 70000);
        $this->service->parse($oversized);
    }

    #[Test]
    public function it_rejects_payload_without_id(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('id');

        $this->service->parse(json_encode(['status' => 'PAID']));
    }

    #[Test]
    public function it_rejects_payload_without_status(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('status');

        $this->service->parse(json_encode(['id' => 'ABC123']));
    }

    #[Test]
    public function it_rejects_empty_id(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->parse(json_encode(['id' => '', 'status' => 'PAID']));
    }

    #[Test]
    public function it_rejects_empty_status(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->parse(json_encode(['id' => 'ABC', 'status' => '']));
    }
}
