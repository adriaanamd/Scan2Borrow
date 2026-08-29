<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Controllers\BarcodePrintController;

final class BarcodePrintRouteTable
{
    /** @return list<Route> */
    public function routes(BarcodePrintController $controller): array
    {
        return [
            Route::create('GET', '/api/barcode-print-batches', [$controller, 'index']),
            Route::create('POST', '/api/barcode-print-batches', [$controller, 'create']),
        ];
    }
}
