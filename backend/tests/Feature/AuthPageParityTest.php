<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class AuthPageParityTest extends TestCase
{
    public function testCanonicalAuthPagesExistWithFormContracts(): void
    {
        $login = (string) file_get_contents(FrontendPagePaths::path('login'));
        $staff = (string) file_get_contents(FrontendPagePaths::path('staff-login'));
        $register = (string) file_get_contents(FrontendPagePaths::path('register'));
        $otp = (string) file_get_contents(FrontendPagePaths::path('verify-otp'));

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
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'login.page.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('redirectToRegistration', $source);
        self::assertStringContainsString('/scan2borrow/register?role=', $source);
        self::assertStringNotContainsString('bindRegistration', $source);
        self::assertStringNotContainsString('bindModalCamera', $source);
        self::assertStringNotContainsString('showRegistrationModal', $source);

        $registrationPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'register' . DIRECTORY_SEPARATOR . 'register.page.js';
        $registration = (string) file_get_contents($registrationPath);

        self::assertStringContainsString('new URLSearchParams(window.location.search)', $registration);
        self::assertStringContainsString("this.showStep('details')", $registration);
    }
}
