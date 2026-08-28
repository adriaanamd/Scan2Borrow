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
            '/student/dashboard' => new PageRoute(
                '/student/dashboard',
                $pageRoot . DIRECTORY_SEPARATOR . 'student-dashboard.html',
                ['student'],
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
