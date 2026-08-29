<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class AuthBrandContractTest extends TestCase
{
    public function testEveryAuthPageUsesTheCentralBrandMount(): void
    {
        foreach ($this->authPages() as $pageName) {
            $source = $this->readPage($pageName);

            self::assertStringContainsString('data-auth-brand', $source, $pageName);
            self::assertStringContainsString('/scan2borrow/frontend/assets/js/core/auth-brand.js', $source, $pageName);
            self::assertStringNotContainsString('auth-brand-logo', $source, $pageName);
            self::assertStringNotContainsString('auth-brand-wordmark', $source, $pageName);
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
            'login',
            'register',
            'staff-login',
            'guest-registration',
            'verify-otp',
            'guest-verify-otp',
            'guest-profile-verify-otp',
        ];
    }

    private function readPage(string $pageName): string
    {
        $path = FrontendPagePaths::path($pageName);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
