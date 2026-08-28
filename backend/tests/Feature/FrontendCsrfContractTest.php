<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FrontendCsrfContractTest extends TestCase
{
    /** @dataProvider postControllerProvider */
    public function testStateChangingVanillaControllersSubmitGatewayToken(string $script): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . $script;
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('meta[name="csrf"]', $source);
        self::assertStringContainsString('body.append("csrf"', $source);
    }

    /** @return list<array{string}> */
    public static function postControllerProvider(): array
    {
        return [
            ['guest/registration.js'],
            ['guest/otp.js'],
            ['guest/borrow-request.js'],
            ['guest/return-book.js'],
            ['guest/profile.js'],
        ];
    }
}
