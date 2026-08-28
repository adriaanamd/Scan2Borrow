<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\BorrowerController;

final class BorrowerRouteTable
{
    /** @return list<Route> */
    public function routes(BorrowerController $controller): array
    {
        return [
            Route::create('GET', '/api/student/dashboard', [$controller, 'dashboard']),
            Route::create('GET', '/api/teacher/dashboard', [$controller, 'dashboard']),
            Route::create('GET', '/api/student/history', [$controller, 'history']),
            Route::create('GET', '/api/receipt', [$controller, 'receipt']),
            Route::create('POST', '/api/student/borrow', [$controller, 'change']),
            Route::create('POST', '/api/student/return', [$controller, 'change']),
            Route::create('POST', '/api/student/dashboard', [$controller, 'change']),
            Route::create('POST', '/api/teacher/dashboard', [$controller, 'change']),
        ];
    }
}
