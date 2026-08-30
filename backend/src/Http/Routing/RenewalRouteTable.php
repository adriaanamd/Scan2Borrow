<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\RenewalController;

final class RenewalRouteTable
{
    /** @return list<Route> */
    public function routes(RenewalController $controller): array
    {
        return [Route::create('GET', '/api/student/renewals', [$controller, 'list']), Route::create('GET', '/api/teacher/renewals', [$controller, 'list']), Route::create('POST', '/api/student/renewals', [$controller, 'create']), Route::create('POST', '/api/teacher/renewals', [$controller, 'create'])];
    }
}
