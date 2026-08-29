<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class RoleNavbarContractTest extends TestCase
{
    public function testEveryApplicationShellUsesTheCentralRoleNavbar(): void
    {
        foreach ($this->shellPages() as $pageName) {
            $source = $this->readPage($pageName);

            self::assertStringContainsString('data-app-navbar', $source, $pageName);
            self::assertStringContainsString('/scan2borrow/frontend/assets/js/core/app-navbar.js', $source, $pageName);
            self::assertStringNotContainsString('<nav class="sidebar-nav">', $source, $pageName . ' still owns a duplicate navbar.');
            self::assertStringNotContainsString('<div class="sidebar-brand">', $source, $pageName . ' still owns a duplicate navbar brand.');
        }
    }

    public function testNavbarSwitchOwnsEachRoleNavigation(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'app-navbar.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('class AppNavbar', $source);
        self::assertStringContainsString('switch (role)', $source);
        self::assertStringContainsString('case "student":', $source);
        self::assertStringContainsString('case "teacher":', $source);
        self::assertStringContainsString('case "admin":', $source);
        self::assertStringContainsString('case "librarian":', $source);
        self::assertStringContainsString('case "guest":', $source);
        self::assertStringContainsString('/scan2borrow/admin/api-docs', $source);
    }

    public function testIconSystemReprocessesDynamicallyRenderedNavbarContent(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'icons.js';
        $source = (string) file_get_contents($path);

        self::assertSame(2, substr_count($source, 'this.replaceNavigationIcons();'));
        self::assertSame(2, substr_count($source, 'this.replaceBrandMarks();'));
    }

    /**
     * @return list<string>
     */
    private function shellPages(): array
    {
        return [
            'admin-api-docs', 'admin-staff', 'guest-borrowed', 'guest-history',
            'guest-browse', 'guest-dashboard', 'guest-profile', 'staff-books',
            'staff-borrower', 'staff-dashboard', 'staff-guest-requests', 'staff-notify',
            'staff-overdue', 'staff-reports', 'staff-students', 'student-dashboard',
            'student-history', 'student-search', 'student-settings', 'teacher-dashboard',
            'teacher-settings',
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
