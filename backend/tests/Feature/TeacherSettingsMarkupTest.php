<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TeacherSettingsMarkupTest extends TestCase
{
    public function testTeacherDashboardKeepsATeacherSettingsLink(): void
    {
        $dashboard = $this->read('frontend/pages/teacher-dashboard.html');
        $navbar = $this->read('frontend/assets/js/core/app-navbar.js');

        self::assertStringContainsString('data-navbar-role="teacher"', $dashboard);
        self::assertStringContainsString('/scan2borrow/teacher/settings', $navbar);
    }

    public function testTeacherSettingsKeepsTeacherScopedNavigationAndAccountSurface(): void
    {
        $settings = $this->read('frontend/pages/teacher-settings.html');

        self::assertStringContainsString('<title>Settings | Scan2Borrow</title>', $settings);
        self::assertStringContainsString('data-page="teacher-settings"', $settings);
        self::assertStringContainsString('data-navbar-role="teacher"', $settings);
        self::assertStringContainsString('frontend/assets/js/pages/student-settings.js', $settings);

        $navbar = $this->read('frontend/assets/js/core/app-navbar.js');
        self::assertStringContainsString('/scan2borrow/teacher/settings', $navbar);
        self::assertStringContainsString('/scan2borrow/teacher/dashboard', $navbar);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
