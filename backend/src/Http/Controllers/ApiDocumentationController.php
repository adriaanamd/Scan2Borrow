<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SessionService;
use App\Domain\Auth\StaffAccessPolicy;
use App\Http\Documentation\ApiEndpointCatalog;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;

final readonly class ApiDocumentationController
{
    public function __construct(
        private SessionService $sessions,
        private ApiEndpointCatalog $catalog,
        private ?StaffAccessPolicy $access = null,
    ) {
    }

    public function index(ServerRequest $request): JsonResponse
    {
        $identity = $this->sessions->current();
        if ($identity === null) {
            return new JsonResponse(401, ['ok' => false, 'errors' => ['Authentication required.']]);
        }
        if (!($this->access ?? new StaffAccessPolicy())->isSystemAdmin($identity)) {
            return new JsonResponse(403, ['ok' => false, 'errors' => ['Administrator access required.']]);
        }

        return new JsonResponse(200, [
            'ok' => true,
            'data' => [
                'openapi' => '3.0.3',
                'title' => 'Scan2Borrow API',
                'endpoints' => $this->catalog->all(),
            ],
        ]);
    }
}
