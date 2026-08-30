<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class TeacherBorrowHistoryRoutingTest extends TestCase
{
    public function testTeacherPageAliasesAreTeacherProtectedAndResolveToSharedTemplates(): void
    {
        $table = new \App\Http\Routing\PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend');

        self::assertSame(['teacher'], $table->forPath('/teacher/borrow')->allowedRoles());
        self::assertSame(['teacher'], $table->forPath('/teacher/history')->allowedRoles());
        self::assertStringContainsString('features' . DIRECTORY_SEPARATOR . 'student' . DIRECTORY_SEPARATOR . 'pages', $table->forPath('/teacher/borrow')->templatePath());
        self::assertStringContainsString('features' . DIRECTORY_SEPARATOR . 'student' . DIRECTORY_SEPARATOR . 'pages', $table->forPath('/teacher/history')->templatePath());
    }

    public function testTeacherApiAliasesReuseBorrowerHandlers(): void
    {
        $bookRoutes = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'BookRouteTable.php');
        $borrowerRoutes = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'BorrowerRouteTable.php');

        self::assertStringContainsString("'/api/teacher/books'", $bookRoutes);
        self::assertStringContainsString("'/api/teacher/borrow/lookup'", $bookRoutes);
        self::assertStringContainsString("'/api/teacher/borrow'", $borrowerRoutes);
        self::assertStringContainsString("'/api/teacher/history'", $borrowerRoutes);
    }
}
