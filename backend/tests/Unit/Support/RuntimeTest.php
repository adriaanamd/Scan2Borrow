<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeTest extends TestCase
{
    public function testRejectsPhpBelowTarget(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHP 8.3+ is required');

        Runtime::assertSupported('8.2.12');
    }
}
