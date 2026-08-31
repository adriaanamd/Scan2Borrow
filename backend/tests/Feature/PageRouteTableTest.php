<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Routing\PageRouteTable;
use PHPUnit\Framework\TestCase;

final class PageRouteTableTest extends TestCase
{
    public function testDefinesPublicAndProtectedPagePolicies(): void
    {
        $table = new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend');

        self::assertTrue($table->forPath('/login')->isPublic());
        self::assertSame([], $table->forPath('/login')->allowedRoles());

        self::assertSame(['admin', 'librarian'], $table->forPath('/staff/dashboard')->allowedRoles());
        self::assertSame(['student'], $table->forPath('/student/dashboard')->allowedRoles());
        self::assertSame(['teacher'], $table->forPath('/teacher/dashboard')->allowedRoles());
        self::assertSame(['teacher'], $table->forPath('/teacher/borrow')->allowedRoles());
        self::assertSame(['teacher'], $table->forPath('/teacher/history')->allowedRoles());
        self::assertSame(['teacher'], $table->forPath('/teacher/settings')->allowedRoles());
        self::assertTrue($table->forPath('/guest/dashboard')->requiresGuest());
        self::assertSame(['admin', 'librarian'], $table->forPath('/staff/books')->allowedRoles());
        self::assertSame(['admin', 'librarian'], $table->forPath('/staff/copy-history')->allowedRoles());
        self::assertSame(['admin', 'librarian'], $table->forPath('/staff/barcodes/print')->allowedRoles());
        self::assertSame(['admin'], $table->forPath('/admin/staff')->allowedRoles());
        self::assertSame(['admin'], $table->forPath('/admin/api-docs')->allowedRoles());
    }

    public function testEveryPageRouteResolvesToFeatureOwnedTemplate(): void
    {
        $table = new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend');
        foreach (['/login', '/register', '/verify-otp', '/student/dashboard', '/student/search', '/teacher/dashboard', '/teacher/borrow', '/teacher/history', '/staff/dashboard', '/staff/books', '/staff/copy-history', '/staff/barcodes/print', '/guest/dashboard', '/guest/registration', '/guest/receipt', '/admin/api-docs'] as $path) {
            $template = $table->forPath($path)->templatePath();
            self::assertStringContainsString(DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR, $template, $path);
            self::assertFileExists($template, $path);
        }
    }
}
