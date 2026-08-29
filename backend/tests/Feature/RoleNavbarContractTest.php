<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class RoleNavbarContractTest extends TestCase
{
    public function testEveryApplicationShellUsesTheCentralRoleNavbar(): void
    {
        foreach ($this->shellPages() as $filename) {
            $source = $this->readPage($filename);

            self::assertStringContainsString('data-app-navbar', $source, $filename);
            self::assertStringContainsString('/scan2borrow/frontend/assets/js/core/app-navbar.js', $source, $filename);
            self::assertStringNotContainsString('<nav class="sidebar-nav">', $source, $filename . ' still owns a duplicate navbar.');
            self::assertStringNotContainsString('<div class="sidebar-brand">', $source, $filename . ' still owns a duplicate navbar brand.');
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

    /**
     * @return list<string>
     */
    private function shellPages(): array
    {
        return [
            'admin-api-docs.html',
            'admin-staff.html',
            'guest-borrowed-books.html',
            'guest-borrowing-history.html',
            'guest-browse-books.html',
            'guest-dashboard.html',
            'guest-profile.html',
            'staff-books.html',
            'staff-borrower.html',
            'staff-dashboard.html',
            'staff-guest-requests.html',
            'staff-notify.html',
            'staff-overdue.html',
            'staff-reports.html',
            'staff-students.html',
            'student-dashboard.html',
            'student-history.html',
            'student-search.html',
            'student-settings.html',
            'teacher-dashboard.html',
            'teacher-settings.html',
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
