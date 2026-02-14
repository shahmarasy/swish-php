<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Enum\PaymentStatus;

final class PaymentStatusTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_values(): void
    {
        $expected = ['CREATED', 'PAID', 'DECLINED', 'ERROR', 'CANCELLED'];
        $actual = array_map(fn(PaymentStatus $s) => $s->value, PaymentStatus::cases());

        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(PaymentStatus::PAID, PaymentStatus::from('PAID'));
        $this->assertSame(PaymentStatus::CANCELLED, PaymentStatus::from('CANCELLED'));
    }

    #[Test]
    public function it_throws_on_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        PaymentStatus::from('INVALID');
    }
}
