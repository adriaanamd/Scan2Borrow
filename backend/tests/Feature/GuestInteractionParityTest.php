<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class GuestInteractionParityTest extends TestCase
{
    private function script(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . $filename;
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    public function testCameraPreservesCaptureQualityFacingModeAndCleanup(): void
    {
        $script = $this->script('camera-capture.js');
        self::assertStringContainsString('facingMode: "user"', $script);
        self::assertStringContainsString('toDataURL("image/jpeg", 0.85)', $script);
        self::assertStringContainsString('beforeunload', $script);
        self::assertStringContainsString('getUserMedia', $script);
    }

    public function testGuestControllersPreserveOtpPurposeAndProtectedNavigationContracts(): void
    {
        $otpPage = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-verify-otp.html');
        self::assertIsString($otpPage);
        self::assertStringContainsString('Resend OTP', $otpPage);
        $registration = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-registration.html');
        self::assertIsString($registration);
        self::assertStringContainsString('Others', $registration);
        $history = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'guest-borrowing-history.html');
        self::assertIsString($history);
        self::assertStringContainsString('/guest/history', $history);
        self::assertStringContainsString('/guest/receipt', $this->script('history.js'));
        self::assertStringContainsString('window.JsBarcode', $this->script('pass.js'));
    }
}
