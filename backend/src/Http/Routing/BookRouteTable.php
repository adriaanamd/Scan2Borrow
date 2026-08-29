<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCopyController;

final class BookRouteTable
{
    /** @return list<Route> */
    public function routes(BookController $controller, BookCopyController $copyController): array
    {
        return [
            Route::create('GET', '/api/books', [$controller, 'inventory']),
            Route::create('GET', '/api/student/books', [$controller, 'studentSearch']),
            Route::create('GET', '/api/student/borrow/lookup', [$controller, 'borrowLookup']),
            Route::create('GET', '/api/teacher/borrow/lookup', [$controller, 'borrowLookup']),
            Route::create('POST', '/api/books', [$controller, 'mutate']),
            Route::create('GET', '/api/book-copies', [$copyController, 'index']),
            Route::create('POST', '/api/book-copies', [$copyController, 'mutate']),
        ];
    }
}
