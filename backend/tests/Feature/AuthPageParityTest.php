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
        self::assertStringContainsString('href="/scan2borrow/register"', $login);
        self::assertStringNotContainsString('studentRegisterModal', $login);
        self::assertStringNotContainsString('teacherRegisterModal', $login);
        self::assertStringContainsString('name="password"', $staff);
        self::assertStringContainsString('Staff Portal', $staff);
        self::assertStringContainsString('name="firstname"', $register);
        self::assertStringContainsString('name="photo_data"', $register);
        self::assertStringContainsString('name="otp"', $otp);
    }

    public function testLoginRedirectsRegistrationRequiredUsersToTheSharedRegistrationFlow(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'auth.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('redirectToRegistration', $source);
        self::assertStringContainsString('/scan2borrow/register?role=', $source);
        self::assertStringNotContainsString('bindRegistration', $source);
        self::assertStringNotContainsString('bindModalCamera', $source);
        self::assertStringNotContainsString('showRegistrationModal', $source);

        $registrationPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'registration.js';
        $registration = (string) file_get_contents($registrationPath);

        self::assertStringContainsString('new URLSearchParams(window.location.search)', $registration);
        self::assertStringContainsString('this.showStep("details")', $registration);
    }
}
