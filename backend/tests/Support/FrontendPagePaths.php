<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

final class FrontendPagePaths
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        'login' => 'features/auth/pages/login/login.html',
        'staff-login' => 'features/auth/pages/staff-login.html',
        'register' => 'features/auth/pages/register/register.html',
        'verify-otp' => 'features/auth/pages/otp/otp.html',
        'guest-registration' => 'features/auth/pages/guest-registration/guest-registration.html',
        'guest-verify-otp' => 'features/auth/pages/guest-otp/guest-otp.html',
        'guest-profile-verify-otp' => 'features/auth/pages/profile-otp/profile-otp.html',
        'guest-profile' => 'features/guest/pages/profile/profile.html',
        'student-settings' => 'features/student/pages/settings/settings.html',
        'teacher-settings' => 'features/teacher/pages/settings/settings.html',
        'staff-dashboard' => 'features/staff/pages/dashboard/dashboard.html',
        'staff-books' => 'features/staff/pages/inventory/inventory.html',
        'staff-students' => 'features/staff/pages/borrowers/borrowers.html',
        'staff-borrower' => 'features/staff/pages/borrower-detail/borrower-detail.html',
        'staff-notify' => 'features/staff/pages/notify/notify.html',
        'staff-overdue' => 'features/staff/pages/overdue/overdue.html',
        'staff-reports' => 'features/staff/pages/reports/reports.html',
        'staff-guest-requests' => 'features/staff/pages/guest-requests/guest-requests.html',
        'student-dashboard' => 'features/student/pages/dashboard/dashboard.html',
        'student-search' => 'features/student/pages/search/search.html',
        'student-history' => 'features/student/pages/history/history.html',
        'receipt' => 'features/student/pages/receipt/receipt.html',
        'teacher-dashboard' => 'features/teacher/pages/dashboard/dashboard.html',
        'guest-dashboard' => 'features/guest/pages/dashboard/dashboard.html',
        'guest-browse' => 'features/guest/pages/browse/browse.html',
        'guest-borrowed' => 'features/guest/pages/borrowed/borrowed.html',
        'guest-history' => 'features/guest/pages/history/history.html',
        'guest-borrow-request' => 'features/guest/pages/borrow-request/borrow-request.html',
        'guest-return' => 'features/guest/pages/return/return.html',
        'guest-pass' => 'features/guest/pages/pass/pass.html',
        'guest-receipt' => 'features/guest/pages/receipt/receipt.html',
        'admin-staff' => 'features/staff/pages/admin-staff/admin-staff.html',
        'admin-api-docs' => 'features/staff/pages/api-docs/api-docs.html',
    ];

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::MAP;
    }

    public static function path(string $name): string
    {
        if (!isset(self::MAP[$name])) {
            throw new InvalidArgumentException('Unknown frontend page: ' . $name);
        }

        return dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'frontend'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::MAP[$name]);
    }
}
