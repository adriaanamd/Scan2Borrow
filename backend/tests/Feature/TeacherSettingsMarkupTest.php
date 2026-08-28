<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TeacherSettingsMarkupTest extends TestCase
{
    public function testTeacherDashboardKeepsATeacherSettingsLink(): void
    {
        $dashboard = $this->read('frontend/pages/teacher-dashboard.html');

        self::assertStringContainsString('href="/scan2borrow/teacher/settings"', $dashboard);
    }

    public function testTeacherSettingsKeepsTeacherScopedNavigationAndAccountSurface(): void
    {
        $settings = $this->read('frontend/pages/teacher-settings.html');

        self::assertStringContainsString('<title>Settings | Scan2Borrow</title>', $settings);
        self::assertStringContainsString('data-page="teacher-settings"', $settings);
        self::assertStringContainsString('href="/scan2borrow/teacher/settings"', $settings);
        self::assertStringContainsString('href="/scan2borrow/teacher/dashboard"', $settings);
        self::assertStringContainsString('frontend/assets/js/pages/student-settings.js', $settings);
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
