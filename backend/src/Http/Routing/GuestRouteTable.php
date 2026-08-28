<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\GuestBorrowingController;

final class GuestRouteTable
{
    /**
     * @return list<Route>
     */
    public function routes(GuestBorrowingController $controller): array
    {
        return [
            Route::create('GET', '/api/guest/dashboard', [$controller, 'dashboard']),
            Route::create('GET', '/api/guest/books', [$controller, 'browse']),
            Route::create('GET', '/api/guest/history', [$controller, 'history']),
            Route::create('GET', '/api/guest/receipt', [$controller, 'receipt']),
            Route::create('POST', '/api/guest/borrow', [$controller, 'borrow']),
            Route::create('POST', '/api/guest/return', [$controller, 'returnBook']),
        ];
    }
}
