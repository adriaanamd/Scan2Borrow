<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class PageTemplateCompletenessTest extends TestCase
{
    public function testEveryCleanRouteTemplateExistsAsStaticHtml(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR;
        foreach ([
            'login.html', 'staff-login.html', 'register.html', 'verify-otp.html',
            'staff-dashboard.html', 'staff-books.html', 'staff-students.html', 'staff-overdue.html',
            'staff-reports.html', 'staff-guest-requests.html', 'staff-borrower.html', 'staff-notify.html', 'admin-staff.html',
            'admin-api-docs.html',
            'student-dashboard.html', 'student-settings.html', 'student-search.html', 'student-history.html', 'teacher-dashboard.html',
            'receipt.html', 'guest-dashboard.html', 'guest-profile.html', 'guest-browse-books.html',
            'guest-borrowed-books.html', 'guest-borrowing-history.html', 'guest-borrow-request.html',
            'guest-return-book.html', 'guest-pass.html', 'guest-receipt.html',
        ] as $filename) {
            $path = $root . $filename;
            self::assertFileExists($path, $filename . ' is missing from the static frontend.');
            $source = (string) file_get_contents($path);
            self::assertStringNotContainsString('<?php', $source, $filename . ' still contains PHP.');
            self::assertStringContainsString('<html', strtolower($source), $filename . ' is not a complete document.');
        }
    }
}
