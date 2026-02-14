<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\DTO;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\DTO\PaymentRequestData;
use Swish\Enum\Currency;

final class PaymentRequestDataTest extends TestCase
{
    #[Test]
    public function it_creates_ecommerce_payment(): void
    {
        $data = new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '100.00',
            callbackUrl: 'https://example.com/callback',
            payerAlias: '46712345678',
        );

        $this->assertSame('1231181189', $data->payeeAlias);
        $this->assertSame('100.00', $data->amount);
        $this->assertSame(Currency::SEK, $data->currency);
        $this->assertSame('46712345678', $data->payerAlias);
    }

    #[Test]
    public function it_creates_mcommerce_payment(): void
    {
        $data = new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '50.00',
            callbackUrl: 'https://example.com/cb',
        );

        $this->assertNull($data->payerAlias);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = PaymentRequestData::fromArray([
            'payeeAlias' => '1231181189',
            'amount' => '200.00',
            'callbackUrl' => 'https://example.com/cb',
            'message' => 'Test',
        ]);

        $this->assertSame('200.00', $data->amount);
        $this->assertSame('Test', $data->message);
    }

    #[Test]
    public function it_generates_correct_api_payload(): void
    {
        $data = new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '100.00',
            callbackUrl: 'https://example.com/cb',
            payerAlias: '46712345678',
            message: 'Test payment',
        );

        $payload = $data->toApiPayload();

        $this->assertSame('1231181189', $payload['payeeAlias']);
        $this->assertSame('100.00', $payload['amount']);
        $this->assertSame('SEK', $payload['currency']);
        $this->assertSame('46712345678', $payload['payerAlias']);
        $this->assertSame('Test payment', $payload['message']);
    }

    #[Test]
    public function it_omits_null_optional_fields_from_payload(): void
    {
        $data = new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '50.00',
            callbackUrl: 'https://example.com/cb',
        );

        $payload = $data->toApiPayload();

        $this->assertArrayNotHasKey('payerAlias', $payload);
        $this->assertArrayNotHasKey('message', $payload);
        $this->assertArrayNotHasKey('payeePaymentReference', $payload);
    }

    // --- Security validation tests ---

    #[Test]
    public function it_rejects_http_callback_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTPS');

        new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '100.00',
            callbackUrl: 'http://example.com/callback',
        );
    }

    #[Test]
    public function it_rejects_non_numeric_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('amount');

        new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: 'abc',
            callbackUrl: 'https://example.com/cb',
        );
    }

    #[Test]
    public function it_rejects_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '-50.00',
            callbackUrl: 'https://example.com/cb',
        );
    }

    #[Test]
    public function it_rejects_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '0',
            callbackUrl: 'https://example.com/cb',
        );
    }

    #[Test]
    public function it_rejects_empty_payee_alias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('payeeAlias');

        new PaymentRequestData(
            payeeAlias: '',
            amount: '100.00',
            callbackUrl: 'https://example.com/cb',
        );
    }

    #[Test]
    public function it_rejects_message_over_50_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('message');

        new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '100.00',
            callbackUrl: 'https://example.com/cb',
            message: str_repeat('A', 51),
        );
    }

    #[Test]
    public function it_rejects_invalid_age_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ageLimit');

        new PaymentRequestData(
            payeeAlias: '1231181189',
            amount: '100.00',
            callbackUrl: 'https://example.com/cb',
            ageLimit: 0,
        );
    }

    #[Test]
    public function it_rejects_from_array_missing_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PaymentRequestData::fromArray(['amount' => '100.00']);
    }
}
