<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class AuthBrandContractTest extends TestCase
{
    public function testEveryAuthPageUsesTheCentralBrandMount(): void
    {
        foreach ($this->authPages() as $filename) {
            $source = $this->readPage($filename);

            self::assertStringContainsString('data-auth-brand', $source, $filename);
            self::assertStringContainsString('/scan2borrow/frontend/assets/js/core/auth-brand.js', $source, $filename);
            self::assertStringNotContainsString('auth-brand-logo', $source, $filename);
            self::assertStringNotContainsString('auth-brand-wordmark', $source, $filename);
        }
    }

    public function testCentralAuthBrandOwnsTheSharedIdentityMarkup(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth-brand.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('class AuthBrand', $source);
        self::assertStringContainsString('/scan2borrow/public/logo.png', $source);
        self::assertStringContainsString('Scan2Borrow', $source);
        self::assertStringContainsString('School Library', $source);
    }

    /**
     * @return list<string>
     */
    private function authPages(): array
    {
        return [
            'login.html',
            'register.html',
            'staff-login.html',
            'guest-registration.html',
            'verify-otp.html',
            'guest-verify-otp.html',
            'guest-profile-verify-otp.html',
        ];
    }

    private function readPage(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $filename;
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
