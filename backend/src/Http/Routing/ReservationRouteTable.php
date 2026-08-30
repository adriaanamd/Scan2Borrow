<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\ReservationController;

final class ReservationRouteTable
{
    /** @return list<Route> */
    public function routes(ReservationController $controller): array
    {
        return [
            Route::create('GET', '/api/student/holds', [$controller, 'list']),
            Route::create('GET', '/api/teacher/holds', [$controller, 'list']),
            Route::create('POST', '/api/student/holds', [$controller, 'create']),
            Route::create('POST', '/api/teacher/holds', [$controller, 'create']),
            Route::create('POST', '/api/student/holds/action', [$controller, 'action']),
            Route::create('POST', '/api/teacher/holds/action', [$controller, 'action']),
        ];
    }
}
