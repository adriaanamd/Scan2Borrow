<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AuthPageParityTest extends TestCase
{
    public function testStaticAuthPagesExistWithLegacyFormContracts(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
        $login = (string) file_get_contents($root . 'login.html');
        $staff = (string) file_get_contents($root . 'staff-login.html');
        $register = (string) file_get_contents($root . 'register.html');
        $otp = (string) file_get_contents($root . 'verify-otp.html');

        self::assertStringContainsString('Scan2Borrow', $login);
        self::assertStringContainsString('name="barcode"', $login);
        self::assertStringContainsString('studentRegisterModal', $login);
        self::assertStringContainsString('name="password"', $staff);
        self::assertStringContainsString('Staff Portal', $staff);
        self::assertStringContainsString('name="firstname"', $register);
        self::assertStringContainsString('name="photo_data"', $register);
        self::assertStringContainsString('name="otp"', $otp);
    }
}
