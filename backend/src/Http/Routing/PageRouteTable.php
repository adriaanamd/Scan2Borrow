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

    public function __construct(string $pageRoot)
    {
        $this->routes = [
            '/login' => new PageRoute('/login', $pageRoot . DIRECTORY_SEPARATOR . 'login.html'),
            '/register' => new PageRoute('/register', $pageRoot . DIRECTORY_SEPARATOR . 'register.html'),
            '/staff/dashboard' => new PageRoute(
                '/staff/dashboard',
                $pageRoot . DIRECTORY_SEPARATOR . 'staff-dashboard.html',
                ['admin', 'librarian'],
            ),
            '/staff/books' => new PageRoute(
                '/staff/books',
                $pageRoot . DIRECTORY_SEPARATOR . 'staff-books.html',
                ['admin', 'librarian'],
            ),
            '/staff/students' => new PageRoute(
                '/staff/students',
                $pageRoot . DIRECTORY_SEPARATOR . 'staff-students.html',
                ['admin', 'librarian'],
            ),
            '/staff/overdue' => new PageRoute(
                '/staff/overdue',
                $pageRoot . DIRECTORY_SEPARATOR . 'staff-overdue.html',
                ['admin', 'librarian'],
            ),
            '/staff/reports' => new PageRoute(
                '/staff/reports',
                $pageRoot . DIRECTORY_SEPARATOR . 'staff-reports.html',
                ['admin', 'librarian'],
            ),
            '/staff/guest-requests' => new PageRoute(
                '/staff/guest-requests',
                $pageRoot . DIRECTORY_SEPARATOR . 'staff-guest-requests.html',
                ['admin', 'librarian'],
            ),
            '/student/dashboard' => new PageRoute(
                '/student/dashboard',
                $pageRoot . DIRECTORY_SEPARATOR . 'student-dashboard.html',
                ['student'],
            ),
            '/student/search' => new PageRoute(
                '/student/search',
                $pageRoot . DIRECTORY_SEPARATOR . 'student-search.html',
                ['student', 'teacher'],
            ),
            '/student/history' => new PageRoute(
                '/student/history',
                $pageRoot . DIRECTORY_SEPARATOR . 'student-history.html',
                ['student', 'teacher'],
            ),
            '/receipt' => new PageRoute(
                '/receipt',
                $pageRoot . DIRECTORY_SEPARATOR . 'receipt.html',
                ['student', 'teacher'],
            ),
            '/teacher/dashboard' => new PageRoute(
                '/teacher/dashboard',
                $pageRoot . DIRECTORY_SEPARATOR . 'teacher-dashboard.html',
                ['teacher'],
            ),
            '/guest/dashboard' => new PageRoute(
                '/guest/dashboard',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-dashboard.html',
                [],
                true,
            ),
            '/guest/profile' => new PageRoute(
                '/guest/profile',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-profile.html',
                [],
                true,
            ),
            '/guest/browse' => new PageRoute(
                '/guest/browse',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-browse-books.html',
                [],
                true,
            ),
            '/guest/borrowed' => new PageRoute(
                '/guest/borrowed',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-borrowed-books.html',
                [],
                true,
            ),
            '/guest/history' => new PageRoute(
                '/guest/history',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-borrowing-history.html',
                [],
                true,
            ),
            '/guest/borrow-request' => new PageRoute(
                '/guest/borrow-request',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-borrow-request.html',
                [],
                true,
            ),
            '/guest/return-book' => new PageRoute(
                '/guest/return-book',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-return-book.html',
                [],
                true,
            ),
            '/guest/pass' => new PageRoute(
                '/guest/pass',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-pass.html',
                [],
                true,
            ),
            '/guest/receipt' => new PageRoute(
                '/guest/receipt',
                $pageRoot . DIRECTORY_SEPARATOR . 'guest-receipt.html',
                [],
                true,
            ),
            '/admin/staff' => new PageRoute(
                '/admin/staff',
                $pageRoot . DIRECTORY_SEPARATOR . 'admin-staff.html',
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
