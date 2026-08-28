<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Requests\ServerRequest;

interface PageAccessAuthorizerInterface
{
    public function allows(ServerRequest $request, PageRoute $route): bool;
}
