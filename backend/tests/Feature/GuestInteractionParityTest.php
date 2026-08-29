<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class GuestInteractionParityTest extends TestCase
{
    private function script(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    public function testCameraPreservesCaptureQualityFacingModeAndCleanup(): void
    {
        $script = $this->script('app/shared/components/camera-capture/camera-capture.component.js');
        self::assertStringContainsString("facingMode: 'user'", $script);
        self::assertStringContainsString("toDataURL('image/jpeg', 0.85)", $script);
        self::assertStringContainsString('beforeunload', $script);
        self::assertStringContainsString('getUserMedia', $script);
    }

    public function testGuestControllersPreserveOtpPurposeAndProtectedNavigationContracts(): void
    {
        $otpPage = file_get_contents(FrontendPagePaths::path('guest-verify-otp'));
        self::assertIsString($otpPage);
        self::assertStringContainsString('Resend OTP', $otpPage);
        $registration = file_get_contents(FrontendPagePaths::path('guest-registration'));
        self::assertIsString($registration);
        self::assertStringContainsString('Others', $registration);
        $history = file_get_contents(FrontendPagePaths::path('guest-history'));
        self::assertIsString($history);
        self::assertStringContainsString('data-app-page="guest-history"', $history);
        self::assertStringContainsString('/guest/receipt', $this->script('features/guest/pages/history/guest-history.page.js'));
        self::assertStringContainsString('window.JsBarcode', $this->script('features/guest/pages/pass/guest-pass.page.js'));
    }

    public function testCanonicalOtpPagesKeepSeparateFormAndRedirectContracts(): void
    {
        $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages';
        $guest = file_get_contents($base . DIRECTORY_SEPARATOR . 'guest-otp' . DIRECTORY_SEPARATOR . 'guest-otp.page.js');
        $profile = file_get_contents($base . DIRECTORY_SEPARATOR . 'profile-otp' . DIRECTORY_SEPARATOR . 'profile-otp.page.js');
        self::assertIsString($guest);
        self::assertIsString($profile);
        self::assertStringContainsString("guest-otp-form", $guest);
        self::assertStringContainsString("/scan2borrow/guest/dashboard", $guest);
        self::assertStringContainsString("profile-otp-form", $profile);
        self::assertStringContainsString("/scan2borrow/guest/profile", $profile);
    }
}
