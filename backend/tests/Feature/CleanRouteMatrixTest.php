<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Routing\PageRouteTable;
use PHPUnit\Framework\TestCase;

final class CleanRouteMatrixTest extends TestCase
{
    public function testMatrixRecordsCanonicalOwnershipAndRetiredTrees(): void
    {
        $matrixPath = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'frontend'
            . DIRECTORY_SEPARATOR . 'parity'
            . DIRECTORY_SEPARATOR . 'page-matrix.md';
        $matrix = (string) file_get_contents($matrixPath);

        foreach (['features/staff/pages/dashboard/dashboard.html', 'features/student/pages/search/search.html', 'canonical', 'frontend/assets/js/core/'] as $marker) {
            self::assertStringContainsString($marker, $matrix, $marker);
        }
        foreach (['frontend/pages/', 'frontend/assets/js/pages/', 'frontend/assets/js/guest/'] as $retiredTree) {
            self::assertStringContainsString($retiredTree, $matrix, $retiredTree);
        }
    }

    public function testEveryCurrentRouteIsListedInTheFrontendMatrix(): void
    {
        $matrixPath = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'frontend'
            . DIRECTORY_SEPARATOR . 'parity'
            . DIRECTORY_SEPARATOR . 'page-matrix.md';
        self::assertFileExists($matrixPath);

        $matrix = (string) file_get_contents($matrixPath);
        foreach ([
            '/', '/login', '/staff/login', '/register', '/verify-otp',
            '/guest/registration', '/guest/verify-otp', '/settings',
            '/student/settings', '/teacher/settings', '/staff/dashboard',
            '/staff/books', '/staff/students', '/staff/borrower', '/staff/notify',
            '/staff/overdue', '/staff/reports', '/staff/guest-requests', '/staff/copy-history',
            '/student/dashboard', '/student/search', '/student/history', '/receipt',
            '/teacher/dashboard', '/teacher/borrow', '/teacher/history', '/guest/dashboard', '/guest/profile',
            '/guest/profile-verify-otp', '/guest/browse', '/guest/borrowed',
            '/guest/history', '/guest/borrow-request', '/guest/return-book',
            '/guest/pass', '/guest/receipt', '/admin/staff', '/admin/api-docs',
        ] as $route) {
            self::assertStringContainsString('| ' . $route . ' |', $matrix, $route);
        }
    }

    public function testAllExtractedPageRoutesHaveExplicitPolicies(): void
    {
        $table = new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend');

        foreach (['/login', '/register'] as $path) {
            self::assertTrue($table->forPath($path)->isPublic());
        }
        self::assertSame(['student'], $table->forPath('/student/settings')->allowedRoles());
        self::assertSame(['student'], $table->forPath('/student/dashboard')->allowedRoles());
        foreach (['/student/search', '/student/history'] as $path) {
            self::assertSame(['student'], $table->forPath($path)->allowedRoles(), $path);
        }
        foreach (['/teacher/dashboard', '/teacher/borrow', '/teacher/history'] as $path) {
            self::assertSame(['teacher'], $table->forPath($path)->allowedRoles(), $path);
        }
        self::assertSame(['teacher'], $table->forPath('/teacher/settings')->allowedRoles());
        foreach (['/guest/dashboard', '/guest/profile', '/guest/browse', '/guest/borrowed', '/guest/history', '/guest/borrow-request', '/guest/return-book', '/guest/pass', '/guest/receipt'] as $path) {
            self::assertTrue($table->forPath($path)->requiresGuest(), $path);
        }
        foreach (['/staff/dashboard', '/staff/books', '/staff/students', '/staff/borrower', '/staff/notify', '/staff/overdue', '/staff/reports', '/staff/guest-requests', '/staff/copy-history'] as $path) {
            self::assertSame(['admin', 'librarian'], $table->forPath($path)->allowedRoles(), $path);
        }
        self::assertSame(['admin'], $table->forPath('/admin/staff')->allowedRoles());
    }
}
