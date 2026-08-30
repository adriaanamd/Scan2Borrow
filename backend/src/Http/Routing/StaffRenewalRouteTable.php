<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\StaffRenewalController;

final class StaffRenewalRouteTable
{
    /** @return list<Route> */
    public function routes(StaffRenewalController $controller): array
    {
        return [Route::create('GET', '/api/staff/renewals', [$controller, 'index']), Route::create('POST', '/api/staff/renewals/action', [$controller, 'action'])];
    }
}
