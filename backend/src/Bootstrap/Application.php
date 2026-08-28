<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Http\Controllers\PageController;
use App\Http\Exceptions\HttpException;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Http\Responses\ResponseEmitter;
use App\Http\Responses\ResponseInterface;
use App\Http\Routing\PageRouteTable;
use App\Http\Routing\Router;
use Throwable;

final readonly class Application
{
    public function __construct(
        public string $environment,
        private ?Router $apiRouter = null,
        private ?PageRouteTable $pageRoutes = null,
        private ?PageController $pageController = null,
        private ?ResponseEmitter $emitter = null,
    ) {
    }

    public function run(): void
    {
        $this->emitter?->emit($this->handle(ServerRequest::fromGlobals()));
    }

    public function handle(ServerRequest $request): ResponseInterface
    {
        try {
            if (str_starts_with($request->path(), '/api/')) {
                if ($this->apiRouter === null) {
                    return new JsonResponse(503, ['ok' => false, 'errors' => ['Application API is not configured.']]);
                }

                return $this->apiRouter->dispatch($request);
            }

            if ($this->pageRoutes === null || $this->pageController === null) {
                return new JsonResponse(503, ['ok' => false, 'errors' => ['Application pages are not configured.']]);
            }

            return $this->pageController->__invoke($request, $this->pageRoutes->forPath($request->path()));
        } catch (HttpException $exception) {
            return new JsonResponse($exception->statusCode(), ['ok' => false, 'errors' => $exception->errors()]);
        } catch (Throwable $exception) {
            return new JsonResponse(500, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }
    }
}
