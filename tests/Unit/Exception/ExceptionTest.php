<?php

declare(strict_types=1);

namespace Swish\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swish\DTO\SwishError;
use Swish\Exception\ApiException;
use Swish\Exception\AuthenticationException;
use Swish\Exception\NetworkException;
use Swish\Exception\SwishException;
use Swish\Exception\ValidationException;

final class ExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_base_exception_with_errors(): void
    {
        $errors = [
            new SwishError('RP01', 'Missing Merchant Swish Number'),
        ];

        $e = new SwishException('test', 422, null, $errors);

        $this->assertSame('test', $e->getMessage());
        $this->assertSame(422, $e->getCode());
        $this->assertCount(1, $e->getErrors());
        $this->assertSame('RP01', $e->getErrors()[0]->errorCode);
    }

    #[Test]
    public function it_creates_from_error_array(): void
    {
        $e = SwishException::fromErrorArray('msg', 422, [
            ['errorCode' => 'PA02', 'errorMessage' => 'Amount is not valid'],
            ['errorCode' => 'AM02', 'errorMessage' => 'Amount too large'],
        ]);

        $this->assertSame(422, $e->getCode());
        $this->assertCount(2, $e->getErrors());
        $this->assertSame('PA02', $e->getErrors()[0]->errorCode);
        $this->assertSame('AM02', $e->getErrors()[1]->errorCode);
    }

    #[Test]
    public function validation_exception_is_swish_exception(): void
    {
        $e = new ValidationException('validation failed', 422);
        $this->assertInstanceOf(SwishException::class, $e);
    }

    #[Test]
    public function authentication_exception_is_swish_exception(): void
    {
        $e = new AuthenticationException('auth failed', 401);
        $this->assertInstanceOf(SwishException::class, $e);
    }

    #[Test]
    public function api_exception_is_swish_exception(): void
    {
        $e = new ApiException('server error', 500);
        $this->assertInstanceOf(SwishException::class, $e);
    }

    #[Test]
    public function network_exception_is_swish_exception(): void
    {
        $e = new NetworkException('timeout');
        $this->assertInstanceOf(SwishException::class, $e);
    }

    #[Test]
    public function it_preserves_previous_exception(): void
    {
        $prev = new \RuntimeException('original');
        $e = ValidationException::fromErrorArray('wrapped', 422, [], $prev);

        $this->assertSame($prev, $e->getPrevious());
    }

    #[Test]
    public function child_exception_from_error_array_returns_correct_type(): void
    {
        $e = ValidationException::fromErrorArray('test', 422, [
            ['errorCode' => 'FF01', 'errorMessage' => 'test msg'],
        ]);

        $this->assertInstanceOf(ValidationException::class, $e);
        $this->assertCount(1, $e->getErrors());
    }
}
