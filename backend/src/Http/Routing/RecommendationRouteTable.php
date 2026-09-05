<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\BorrowerRecommendationController;

final class RecommendationRouteTable
{
    /** @return list<Route> */
    public function routes(BorrowerRecommendationController $controller): array
    {
        return [
            Route::create('GET', '/api/student/recommendations', [$controller, 'index']),
            Route::create('GET', '/api/teacher/recommendations', [$controller, 'index']),
            Route::create('POST', '/api/student/search-history', [$controller, 'recordSearch']),
            Route::create('POST', '/api/teacher/search-history', [$controller, 'recordSearch']),
        ];
    }
}
