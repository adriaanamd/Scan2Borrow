<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Exceptions\HttpException;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Http\Responses\ResponseInterface;
use Throwable;

final class Router
{
    /**
     * @param list<Route> $routes
     */
    public function __construct(
        private readonly array $routes,
    ) {
    }

    public function dispatch(ServerRequest $request): ResponseInterface
    {
        try {
            foreach ($this->routes as $route) {
                if ($route->matches($request)) {
                    return $route->respond($request);
                }
            }

            throw new HttpException(404, ['Route not found.']);
        } catch (HttpException $exception) {
            return new JsonResponse(
                $exception->statusCode(),
                ['ok' => false, 'errors' => $exception->errors()],
            );
        } catch (Throwable) {
            return new JsonResponse(
                500,
                ['ok' => false, 'errors' => ['Internal server error.']],
            );
        }
    }
}
