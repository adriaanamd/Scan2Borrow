<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Bootstrap\Application;
use App\Bootstrap\ApplicationFactory;
use App\Support\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeTest extends TestCase
{
    public function testAcceptsPhp82Runtime(): void
    {
        Runtime::assertSupported('8.2.12');
        self::assertSame('8.2.0', Runtime::minimumPhpVersion());
    }

    public function testRejectsPhpBelowTarget(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHP 8.2+ is required');

        Runtime::assertSupported('8.1.99');
    }

    public function testCreatesApplicationForNamedEnvironment(): void
    {
        $application = ApplicationFactory::create('testing');

        self::assertInstanceOf(Application::class, $application);
        self::assertSame('testing', $application->environment);
    }
}
