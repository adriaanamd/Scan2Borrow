<?php

declare(strict_types=1);

namespace App\Http\Routing;

use App\Http\Requests\ServerRequest;
use App\Http\Responses\ResponseInterface;
use Closure;

final readonly class Route
{
    public function __construct(
        private string $method,
        private string $path,
        private Closure $handler,
    ) {
    }

    /**
     * @param callable(ServerRequest): ResponseInterface $handler
     */
    public static function create(
        string $method,
        string $path,
        callable $handler,
    ): self {
        return new self(
            strtoupper($method),
            self::normalizePath($path),
            Closure::fromCallable($handler),
        );
    }

    public function matches(ServerRequest $request): bool
    {
        return $this->method === $request->method() && $this->path === $request->path();
    }

    public function respond(ServerRequest $request): ResponseInterface
    {
        return ($this->handler)($request);
    }

    private static function normalizePath(string $path): string
    {
        $normalizedPath = rtrim($path, '/');

        return $normalizedPath === '' ? '/' : $normalizedPath;
    }
}
