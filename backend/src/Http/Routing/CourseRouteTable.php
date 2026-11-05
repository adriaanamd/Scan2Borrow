<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\CourseController;

final class CourseRouteTable
{
    /** @return list<Route> */
    public function routes(CourseController $controller): array
    {
        return [
            Route::create('GET', '/api/courses', [$controller, 'index']),
            Route::create('GET', '/api/admin/courses', [$controller, 'adminIndex']),
            Route::create('POST', '/api/admin/courses', [$controller, 'store']),
        ];
    }
}
