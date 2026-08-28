<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\AuthController;

final class AuthRouteTable
{
    /**
     * @return list<Route>
     */
    public function routes(AuthController $controller): array
    {
        return [
            Route::create('GET', '/api/auth/session', [$controller, 'session']),
            Route::create('POST', '/api/auth/logout', [$controller, 'logout']),
            Route::create('POST', '/api/auth/borrower/login', [$controller, 'loginBorrower']),
            Route::create('POST', '/api/auth/staff/login', [$controller, 'loginStaff']),
        ];
    }
}
