<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\GuestBorrowingController;
use App\Http\Controllers\GuestAuthController;
use App\Http\Controllers\GuestDetailsController;

final class GuestRouteTable
{
    /**
     * @return list<Route>
     */
    public function routes(
        GuestBorrowingController $controller,
        ?GuestDetailsController $details = null,
        ?GuestAuthController $auth = null,
    ): array
    {
        $routes = [
            Route::create('GET', '/api/guest/dashboard', [$controller, 'dashboard']),
            Route::create('GET', '/api/guest/books', [$controller, 'browse']),
            Route::create('GET', '/api/guest/history', [$controller, 'history']),
            Route::create('GET', '/api/guest/borrowed', [$controller, 'borrowed']),
            Route::create('GET', '/api/guest/receipt', [$controller, 'receipt']),
            Route::create('POST', '/api/guest/borrow', [$controller, 'borrow']),
            Route::create('POST', '/api/guest/return', [$controller, 'returnBook']),
        ];

        if ($details !== null) {
            $routes[] = Route::create('GET', '/api/guest/profile', [$details, 'profile']);
            $routes[] = Route::create('POST', '/api/guest/profile', [$details, 'profile']);
            $routes[] = Route::create('GET', '/api/guest/pass', [$details, 'pass']);
        }

        if ($auth !== null) {
            $routes[] = Route::create('POST', '/api/auth/guest/register', [$auth, 'register']);
            $routes[] = Route::create('POST', '/api/auth/guest/otp', [$auth, 'verify']);
            $routes[] = Route::create('POST', '/api/auth/guest/otp/resend', [$auth, 'resend']);
        }

        return $routes;
    }
}
