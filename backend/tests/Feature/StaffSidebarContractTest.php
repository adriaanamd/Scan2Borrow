<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class StaffSidebarContractTest extends TestCase
{
    public function testEveryStaffPageUsesTheCentralStaffSidebarMount(): void
    {
        foreach ($this->staffPages() as $filename) {
            $source = $this->readPage($filename);

            self::assertStringContainsString('data-staff-sidebar', $source, $filename);
            self::assertStringContainsString('/scan2borrow/frontend/assets/js/core/staff-sidebar.js', $source, $filename);
        }
    }

    public function testCentralStaffSidebarOwnsTheAdminOnlyApiDocsLink(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'staff-sidebar.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('class StaffSidebar', $source);
        self::assertStringContainsString('/scan2borrow/admin/api-docs', $source);
        self::assertStringContainsString('/scan2borrow/api/auth/session', $source);
        self::assertStringContainsString("role === 'admin'", $source);
    }

    /**
     * @return list<string>
     */
    private function staffPages(): array
    {
        return [
            'staff-books.html',
            'staff-borrower.html',
            'staff-dashboard.html',
            'staff-guest-requests.html',
            'staff-notify.html',
            'staff-overdue.html',
            'staff-reports.html',
            'staff-students.html',
            'admin-staff.html',
            'admin-api-docs.html',
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
