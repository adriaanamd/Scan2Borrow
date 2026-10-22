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
    public function testAcceptsTheFirstPatchedReleaseOfEverySupportedBranch(): void
    {
        foreach (['8.2.35', '8.3.36', '8.4.27', '8.5.12'] as $version) {
            Runtime::assertSupported($version);
        }

        self::assertSame('8.2.35', Runtime::minimumPhpVersion());
    }

    public function testRejectsVulnerableAndUnknownPhpBranches(): void
    {
        foreach (['8.2.34', '8.3.35', '8.4.26', '8.5.11', '8.6.0'] as $version) {
            try {
                Runtime::assertSupported($version);
                self::fail('A vulnerable or unknown PHP runtime was accepted: ' . $version);
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('patched PHP release is required', $exception->getMessage());
            }
        }

        Runtime::assertSupported('8.2.35');
    }

    public function testCreatesApplicationForNamedEnvironment(): void
    {
        $application = ApplicationFactory::create('testing');

        self::assertInstanceOf(Application::class, $application);
        self::assertSame('testing', $application->environment);
    }
}
