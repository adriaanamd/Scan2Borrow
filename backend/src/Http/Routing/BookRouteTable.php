<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\BookController;

final class BookRouteTable
{
    /** @return list<Route> */
    public function routes(BookController $controller): array
    {
        return [
            Route::create('GET', '/api/books', [$controller, 'inventory']),
            Route::create('GET', '/api/student/books', [$controller, 'studentSearch']),
            Route::create('POST', '/api/books', [$controller, 'mutate']),
        ];
    }
}
