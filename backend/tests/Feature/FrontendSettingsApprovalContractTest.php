<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FrontendSettingsApprovalContractTest extends TestCase
{
    public function testSettingsStylesExposeScopedSwissApprovalSurface(): void
    {
        foreach (['student', 'teacher'] as $role) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . $role . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'settings.css';
            self::assertFileExists($path);
            $css = (string) file_get_contents($path);
            foreach (['background: #fff', '--profile-blue: #002fa7', '.profile-request-diff', '@media (prefers-reduced-motion: reduce)'] as $marker) {
                self::assertStringContainsString($marker, $css, $role . ' settings style marker: ' . $marker);
            }
            self::assertStringNotContainsString('.sidebar', $css);
            self::assertStringNotContainsString('box-shadow:', $css);
        }
    }
}
