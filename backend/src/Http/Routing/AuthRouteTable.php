<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegistrationController;

final class AuthRouteTable
{
    /**
     * @return list<Route>
     */
    public function routes(AuthController $controller, ?RegistrationController $registration = null): array
    {
        $routes = [
            Route::create('GET', '/api/auth/session', [$controller, 'session']),
            Route::create('POST', '/api/auth/logout', [$controller, 'logout']),
            Route::create('GET', '/logout', [$controller, 'logoutLegacy']),
            Route::create('POST', '/api/auth/borrower/login', [$controller, 'loginBorrower']),
            Route::create('POST', '/api/auth/student/login', [$controller, 'loginBorrower']),
            Route::create('POST', '/api/auth/staff/login', [$controller, 'loginStaff']),
        ];

        if ($registration !== null) {
            $routes[] = Route::create('POST', '/api/auth/register', [$registration, 'begin']);
            $routes[] = Route::create('POST', '/api/auth/otp', [$registration, 'verify']);
            $routes[] = Route::create('POST', '/api/auth/otp/resend', [$registration, 'resend']);
        }

        return $routes;
    }
}
