<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Exceptions\HttpException;

final class PageRouteTable
{
    /**
     * @var array<string, PageRoute>
     */
    private readonly array $routes;

    public function __construct(string $frontendRoot)
    {
        $featurePath = static fn (string $relative): string => $frontendRoot
            . DIRECTORY_SEPARATOR . 'features'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        $this->routes = [
            '/' => new PageRoute('/', $featurePath('auth/pages/login/login.html'), [], false, true),
            '/login' => new PageRoute('/login', $featurePath('auth/pages/login/login.html'), [], false, true),
            '/staff/login' => new PageRoute('/staff/login', $featurePath('auth/pages/staff-login.html'), [], false, true),
            '/register' => new PageRoute('/register', $featurePath('auth/pages/register/register.html')),
            '/verify-otp' => new PageRoute('/verify-otp', $featurePath('auth/pages/otp/otp.html')),
            '/guest/registration' => new PageRoute('/guest/registration', $featurePath('auth/pages/guest-registration/guest-registration.html')),
            '/guest/verify-otp' => new PageRoute('/guest/verify-otp', $featurePath('auth/pages/guest-otp/guest-otp.html')),
            '/settings' => new PageRoute(
                '/settings',
                $featurePath('guest/pages/profile/profile.html'),
                [],
                true,
            ),
            '/student/settings' => new PageRoute(
                '/student/settings',
                $featurePath('student/pages/settings/settings.html'),
                ['student'],
            ),
            '/teacher/settings' => new PageRoute(
                '/teacher/settings',
                $featurePath('teacher/pages/settings/settings.html'),
                ['teacher'],
            ),
            '/staff/dashboard' => new PageRoute(
                '/staff/dashboard',
                $featurePath('staff/pages/dashboard/dashboard.html'),
                ['admin', 'librarian'],
            ),
            '/staff/books' => new PageRoute(
                '/staff/books',
                $featurePath('staff/pages/inventory/inventory.html'),
                ['admin', 'librarian'],
            ),
            '/staff/barcodes/print' => new PageRoute(
                '/staff/barcodes/print',
                $featurePath('staff/pages/barcodes/barcodes.html'),
                ['admin', 'librarian'],
            ),
            '/staff/students' => new PageRoute(
                '/staff/students',
                $featurePath('staff/pages/borrowers/borrowers.html'),
                ['admin', 'librarian'],
            ),
            '/staff/reservations' => new PageRoute(
                '/staff/reservations',
                $featurePath('staff/pages/reservations/reservations.html'),
                ['admin', 'librarian'],
            ),
            '/staff/renewals' => new PageRoute(
                '/staff/renewals',
                $featurePath('staff/pages/renewals/renewals.html'),
                ['admin', 'librarian'],
            ),
            '/staff/borrower' => new PageRoute(
                '/staff/borrower',
                $featurePath('staff/pages/borrower-detail/borrower-detail.html'),
                ['admin', 'librarian'],
            ),
            '/staff/notify' => new PageRoute(
                '/staff/notify',
                $featurePath('staff/pages/notify/notify.html'),
                ['admin', 'librarian'],
            ),
            '/staff/overdue' => new PageRoute(
                '/staff/overdue',
                $featurePath('staff/pages/overdue/overdue.html'),
                ['admin', 'librarian'],
            ),
            '/staff/reports' => new PageRoute(
                '/staff/reports',
                $featurePath('staff/pages/reports/reports.html'),
                ['admin', 'librarian'],
            ),
            '/staff/guest-requests' => new PageRoute(
                '/staff/guest-requests',
                $featurePath('staff/pages/guest-requests/guest-requests.html'),
                ['admin', 'librarian'],
            ),
            '/student/dashboard' => new PageRoute(
                '/student/dashboard',
                $featurePath('student/pages/dashboard/dashboard.html'),
                ['student'],
            ),
            '/student/search' => new PageRoute(
                '/student/search',
                $featurePath('student/pages/search/search.html'),
                ['student', 'teacher'],
            ),
            '/student/history' => new PageRoute(
                '/student/history',
                $featurePath('student/pages/history/history.html'),
                ['student', 'teacher'],
            ),
            '/receipt' => new PageRoute(
                '/receipt',
                $featurePath('student/pages/receipt/receipt.html'),
                ['student', 'teacher'],
            ),
            '/teacher/dashboard' => new PageRoute(
                '/teacher/dashboard',
                $featurePath('teacher/pages/dashboard/dashboard.html'),
                ['teacher'],
            ),
            '/teacher/borrow' => new PageRoute(
                '/teacher/borrow',
                $featurePath('student/pages/search/search.html'),
                ['teacher'],
            ),
            '/teacher/history' => new PageRoute(
                '/teacher/history',
                $featurePath('student/pages/history/history.html'),
                ['teacher'],
            ),
            '/guest/dashboard' => new PageRoute(
                '/guest/dashboard',
                $featurePath('guest/pages/dashboard/dashboard.html'),
                [],
                true,
            ),
            '/guest/profile' => new PageRoute(
                '/guest/profile',
                $featurePath('guest/pages/profile/profile.html'),
                [],
                true,
            ),
            '/guest/profile-verify-otp' => new PageRoute(
                '/guest/profile-verify-otp',
                $featurePath('auth/pages/profile-otp/profile-otp.html'),
                [],
                true,
            ),
            '/guest/browse' => new PageRoute(
                '/guest/browse',
                $featurePath('guest/pages/browse/browse.html'),
                [],
                true,
            ),
            '/guest/borrowed' => new PageRoute(
                '/guest/borrowed',
                $featurePath('guest/pages/borrowed/borrowed.html'),
                [],
                true,
            ),
            '/guest/history' => new PageRoute(
                '/guest/history',
                $featurePath('guest/pages/history/history.html'),
                [],
                true,
            ),
            '/guest/borrow-request' => new PageRoute(
                '/guest/borrow-request',
                $featurePath('guest/pages/borrow-request/borrow-request.html'),
                [],
                true,
            ),
            '/guest/return-book' => new PageRoute(
                '/guest/return-book',
                $featurePath('guest/pages/return/return.html'),
                [],
                true,
            ),
            '/guest/pass' => new PageRoute(
                '/guest/pass',
                $featurePath('guest/pages/pass/pass.html'),
                [],
                true,
            ),
            '/guest/receipt' => new PageRoute(
                '/guest/receipt',
                $featurePath('guest/pages/receipt/receipt.html'),
                [],
                true,
            ),
            '/admin/staff' => new PageRoute(
                '/admin/staff',
                $featurePath('staff/pages/admin-staff/admin-staff.html'),
                ['admin'],
            ),
            '/admin/api-docs' => new PageRoute(
                '/admin/api-docs',
                $featurePath('staff/pages/api-docs/api-docs.html'),
                ['admin'],
            ),
        ];
    }

    public function forPath(string $path): PageRoute
    {
        $normalizedPath = rtrim($path, '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        if (!isset($this->routes[$normalizedPath])) {
            throw new HttpException(404, ['Page not found.']);
        }

        return $this->routes[$normalizedPath];
    }
}
