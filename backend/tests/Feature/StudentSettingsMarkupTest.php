<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class StudentSettingsMarkupTest extends TestCase
{
    public function testStudentSettingsKeepsAccountDetailsInStudentContext(): void
    {
        $path = FrontendPagePaths::path('student-settings');
        self::assertFileExists($path);
        $html = (string) file_get_contents($path);

        self::assertStringContainsString('<title>Settings | Scan2Borrow</title>', $html);
        self::assertStringContainsString('data-page="student-settings"', $html);
        self::assertStringContainsString('id="student-settings-form"', $html);
        self::assertStringContainsString('frontend/features/student/pages/settings/student-settings.page.js', $html);
        self::assertStringContainsString('data-navbar-role="student"', $html);
        $navbar = (string) file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'app-navbar.js');
        self::assertStringContainsString('/scan2borrow/student/settings', $navbar);
        self::assertStringNotContainsString('/scan2borrow/settings', $html);
        self::assertStringNotContainsString('/scan2borrow/guest/registration', $html);
    }

    public function testStudentSearchSettingsLinkStaysInStudentContext(): void
    {
        $path = FrontendPagePaths::path('student-search');
        self::assertFileExists($path);
        $html = (string) file_get_contents($path);
        $navbar = (string) file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'app-navbar.js');

        self::assertStringContainsString('data-app-navbar', $html);
        self::assertStringContainsString('/scan2borrow/student/settings', $navbar);
        self::assertStringNotContainsString('href="/scan2borrow/settings"', $html);
    }

    public function testCanonicalStudentSettingsUsesFeatureOwnedModule(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'student' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'settings.html';
        self::assertFileExists($path);
        $html = file_get_contents($path);
        self::assertIsString($html);
        self::assertStringContainsString('data-app-page="student-settings"', $html);
        self::assertStringContainsString('frontend/features/student/pages/settings/student-settings.page.js', $html);
        foreach (['id="student-settings-form"', 'data-navbar-role="student"', 'student-settings-error'] as $marker) {
            self::assertStringContainsString($marker, $html);
        }
    }
}
