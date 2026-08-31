<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\CopyHistoryController;

final class CopyHistoryRouteTable
{
    /** @return list<Route> */
    public function routes(CopyHistoryController $controller): array
    {
        return [Route::create('GET', '/api/staff/copy-history', [$controller, 'index'])];
    }
}
