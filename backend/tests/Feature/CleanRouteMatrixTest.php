<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Routing\PageRouteTable;
use PHPUnit\Framework\TestCase;

final class CleanRouteMatrixTest extends TestCase
{
    public function testAllExtractedPageRoutesHaveExplicitPolicies(): void
    {
        $table = new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages');

        foreach (['/login', '/register'] as $path) {
            self::assertTrue($table->forPath($path)->isPublic());
        }
        self::assertSame(['student'], $table->forPath('/student/dashboard')->allowedRoles());
        foreach (['/student/search', '/student/history'] as $path) {
            self::assertSame(['student', 'teacher'], $table->forPath($path)->allowedRoles(), $path);
        }
        foreach (['/teacher/dashboard'] as $path) {
            self::assertSame(['teacher'], $table->forPath($path)->allowedRoles(), $path);
        }
        foreach (['/guest/dashboard', '/guest/profile', '/guest/browse', '/guest/borrowed', '/guest/history', '/guest/borrow-request', '/guest/return-book', '/guest/pass', '/guest/receipt'] as $path) {
            self::assertTrue($table->forPath($path)->requiresGuest(), $path);
        }
        foreach (['/staff/dashboard', '/staff/books', '/staff/students', '/staff/borrower', '/staff/notify', '/staff/overdue', '/staff/reports', '/staff/guest-requests'] as $path) {
            self::assertSame(['admin', 'librarian'], $table->forPath($path)->allowedRoles(), $path);
        }
        self::assertSame(['admin'], $table->forPath('/admin/staff')->allowedRoles());
    }
}
