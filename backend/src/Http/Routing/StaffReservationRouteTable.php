<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\StaffReservationController;

final class StaffReservationRouteTable
{
    /** @return list<Route> */
    public function routes(StaffReservationController $controller): array
    {
        return [
            Route::create('GET', '/api/staff/reservations', [$controller, 'index']),
            Route::create('POST', '/api/staff/reservations/action', [$controller, 'action']),
        ];
    }
}
