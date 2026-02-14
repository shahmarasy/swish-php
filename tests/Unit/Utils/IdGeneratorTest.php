<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\Utils\IdGenerator;

final class IdGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_32_char_hex_string(): void
    {
        $id = IdGenerator::generate();

        $this->assertSame(32, strlen($id));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{32}$/', $id);
    }

    #[Test]
    public function it_generates_unique_ids(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = IdGenerator::generate();
        }

        $this->assertCount(100, array_unique($ids));
    }

    #[Test]
    public function it_generates_uppercase_only(): void
    {
        $id = IdGenerator::generate();
        $this->assertSame($id, strtoupper($id));
    }
}
